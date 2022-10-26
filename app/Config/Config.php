<?php

declare(strict_types=1);

namespace Fintech\CommissionTask\Config;

class Config
{
    public const DEPOSIT_PRIVATE_FEE = 0.03;
    public const DEPOSIT_BUSINESS_FEE = 0.03;
    public const WITHDRAW_PRIVATE_FEE = 0.3;
    public const WITHDRAW_BUSINESS_FEE = 0.5;

    public const CSV_DELIMITER = ',';

    public const EXCHANGE_RATES_URL = 'https://developers.paysera.com/tasks/api/currency-exchange-rates';
}
