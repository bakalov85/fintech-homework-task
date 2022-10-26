<?php
declare(strict_types=1);

namespace Fintech\CommissionTask\Tests\Service;

use PHPUnit\Framework\TestCase;
use Fintech\CommissionTask\Service\CommissionFee;

class CommissionFeeTest extends TestCase
{
    private object $commissionFee;

    public function setUp(): void
    {
        $input = [
            ['2014-12-31', '4', 'private', 'withdraw', '1200.00', 'EUR'],
            ['2015-01-01', '4', 'private', 'withdraw', '1000.00', 'EUR'],
            ['2016-01-05', '4', 'private', 'withdraw', '1000.00', 'EUR'],
            ['2016-01-05', '1', 'private', 'deposit', '200.00', 'EUR'],
            ['2016-01-06', '2', 'business', 'withdraw', '300.00', 'EUR'],
            ['2016-01-06', '1', 'private', 'withdraw', '30000', 'JPY'],
            ['2016-01-07', '1', 'private', 'withdraw', '1000.00', 'EUR'],
            ['2016-01-07', '1', 'private', 'withdraw', '100.00', 'USD'],
            ['2016-01-10', '1', 'private', 'withdraw', '100.00', 'EUR'],
            ['2016-01-10', '2', 'business', 'deposit', '10000.00', 'EUR'],
            ['2016-01-10', '3', 'private', 'withdraw', '1000.00', 'EUR'],
            ['2016-02-15', '1', 'private', 'withdraw', '300.00', 'EUR'],
            ['2016-02-19', '5', 'private', 'withdraw', '3000000', 'JPY'],
        ];        

        $this->commissionFee = new CommissionFee($input);
    }

    public function testGetFee()
    {
        $exchangeRates = [
            'JPY' => 129.53,
            'USD' => 1.1497,
        ];

        $expectation = [
            "0.60",
            "3.00",
            "0.00",
            "0.06",
            "1.50",
            "0",
            "0.70",
            "0.30",
            "0.30",
            "3.00",
            "0.00",
            "0.00",
            "8612",
        ];

        // set private property 'exchangeRates'
        $reflection = new \ReflectionClass($this->commissionFee);
        $reflection_property = $reflection->getProperty('exchangeRates');
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($this->commissionFee, $exchangeRates);

        $this->assertEquals(
            $expectation,
            $this->commissionFee->getFees()
        );
    }
}
