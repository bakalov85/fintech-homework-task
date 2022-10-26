<?php

declare(strict_types=1);

namespace Fintech\CommissionTask\Service;

use Fintech\CommissionTask\Config\Config;

class CommissionFee
{
    private const ENTITY_TYPE_PRIVATE = 'private';
    private const ENTITY_TYPE_BUSINESS = 'business';

    private const ACTION_DEPOSIT = 'deposit';
    private const ACTION_WITHDRAW = 'withdraw';

    /**
     * [0] => operationDate, [1] => userId, [2] => entityType (business, private), [3] => action (deposit, withdraw), [4] => amount, [5] => currency.
     */
    private array $records;
    private array $exchangeRates = []; // Load later if needed. We'll perform only one API request per script run

    public function __construct(array $records)
    {
        $this->records = $records;
    }

    public function getFees(): array
    {
        $fees = [];

        foreach ($this->records as $key => $record) {
            list($operationDate, $userId, $entityType, $action, $amount, $currency) = $record;
            $currentFee = 0;

            // use scale 10 in order not to lose data in the multiple chained calculations, later we'll round it to each currency's default scale.
            if ($action === self::ACTION_DEPOSIT) {
                if ($entityType === self::ENTITY_TYPE_PRIVATE) {
                    $currentFee = bcmul(bcdiv((string) Config::DEPOSIT_PRIVATE_FEE, '100', 10), $amount, 10);
                } elseif ($entityType === self::ENTITY_TYPE_BUSINESS) {
                    $currentFee = bcmul(bcdiv((string) Config::DEPOSIT_BUSINESS_FEE, '100', 10), $amount, 10);
                }
            } elseif ($action === self::ACTION_WITHDRAW) {
                if ($entityType === self::ENTITY_TYPE_PRIVATE) {
                    $currentFee = $this->calcPrivateWithdraw($userId, $operationDate, $amount, $currency, $key);
                } if ($entityType === self::ENTITY_TYPE_BUSINESS) {
                    $currentFee = bcmul(bcdiv((string) Config::WITHDRAW_BUSINESS_FEE, '100', 10), $amount, 10);
                }
            }

            $fees[$key] = $this->roundCurrency($currentFee, $this->countDigistAfterDecimalPoint($amount));
        }

        return $fees;
    }

    /**
     * Do the more complicated math in case of private user withdrawal.
     */
    private function calcPrivateWithdraw(string $userId, string $operationDateStr, string $amount, string $currency, int $key): string
    {
        // Get the Monday of operation date's week.
        // Example: in the week of 2022-09-27, the Monday is on 2022-09-26. Use this date as a start date
        $startDate = new \DateTime($operationDateStr.' Monday this week');
        $operationDate = \DateTime::createFromFormat('Y-m-d', $operationDateStr);

        // check this user's withdrawals throughout that week
        $withdrawals = 0;
        $withdrawalAmountInEur = $withdrawalAmountInKind = '0';

        for ($i = $key - 1; $i >= 0; --$i) {
            if ($this->records[$i][1] === $userId && $this->records[$i][2] === self::ENTITY_TYPE_PRIVATE && $this->records[$i][3] === self::ACTION_WITHDRAW) {
                $recordDate = new \DateTime($this->records[$i][0]);

                if ($recordDate >= $startDate && $recordDate <= $operationDate) {
                    ++$withdrawals;
                    $withdrawalAmountInEur = bcadd($withdrawalAmountInEur, $this->convertToEuro($this->records[$i][5], $this->records[$i][4]), 10);
                }
            }
        }

        if ($withdrawals >= 3) {
            return bcmul(bcdiv((string) Config::WITHDRAW_PRIVATE_FEE, '100', 10), $amount, 10);
        }

        $euro1000WorthInKind = $this->convertFromEuro($currency, '1000');
        $withdrawalAmountInKind = $this->convertFromEuro($currency, $withdrawalAmountInEur);
        $freeWithdrawInKind = bcsub($euro1000WorthInKind, $withdrawalAmountInKind, 10);

        if ((float) $freeWithdrawInKind < 0) {
            $freeWithdrawInKind = '0';
        }

        $taxableAmountInKind = bcsub($amount, $freeWithdrawInKind, 10);
        if ((float) $taxableAmountInKind < 0) {
            $taxableAmountInKind = '0';
        }

        $currentAmountInEuro = $this->convertToEuro($currency, $amount);
        $freeWithdrawInEuro = bcsub('1000', $withdrawalAmountInEur, 10);

        if ((float) $freeWithdrawInEuro < 0) {
            $freeWithdrawInEuro = '0';
        }

        $taxableAmountInEuro = bcsub($currentAmountInEuro, $freeWithdrawInEuro, 10);

        if ((float) $taxableAmountInEuro < 0) {
            $taxableAmountInEuro = '0';
        }

        return bcmul(bcdiv((string) Config::WITHDRAW_PRIVATE_FEE, '100', 10), $taxableAmountInKind, 10);
    }

    private function convertToEuro(string $fromCurrency, string $amount): string
    {
        if ($fromCurrency === 'EUR') {
            return $amount;
        }

        $rate = $this->getEuroExchangeRate($fromCurrency);

        // get inverse rate (1 / rate)
        $inverseRate = bcdiv('1', (string) $rate, 10);

        return bcmul($amount, $inverseRate, 10);
    }

    private function convertFromEuro(string $toCurrency, string $amount)
    {
        if ($toCurrency === 'EUR') {
            return $amount;
        }

        $rate = $this->getEuroExchangeRate($toCurrency);

        return bcmul($amount, (string) $rate, 10);
    }

    private function getEuroExchangeRate($currency): float
    {
        if (!$this->exchangeRates) {
            $this->exchangeRates = json_decode(Api::get(Config::EXCHANGE_RATES_URL), true)['rates'];
        }

        if (!isset($this->exchangeRates[$currency])) {
            throw new \Exception('Currency '.$currency.' is not supported by the exchange rates API');
        }

        // test with rates from the task description
        if ($currency === 'USD') {
            // return 1.1497;
        }
        if ($currency === 'JPY') {
            // return 129.53;
        }

        return $this->exchangeRates[$currency];
    }

    private function countDigistAfterDecimalPoint(string $amount): int
    {
        if (strpos($amount, '.') === false) {
            return 0;
        } else {
            return strlen(substr(strrchr($amount, '.'), 1));
        }
    }

    private function roundCurrency(string $amount, int $numDecimalDigits): string
    {
        // for this you need php-intl extension
        $formatter = new \NumberFormatter('en-US', \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $numDecimalDigits);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_UP);
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);

        return $formatter->format((float) $amount);
    }
}
