<?php

declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';

use Fintech\CommissionTask\Service\CommissionFee;
use Fintech\CommissionTask\Service\FileReader;

try {
    $fileReader = new FileReader($argv[1]); // $argv[1] is the first command line argument passed to this script
    $records = $fileReader->read();
    $commissionFee = new CommissionFee($records);
    $fees = $commissionFee->getFees();

    foreach ($fees as $fee) {
        echo $fee.PHP_EOL;
    }
} catch (\Exception $e) {
    echo $e->getMessage().PHP_EOL;
}
