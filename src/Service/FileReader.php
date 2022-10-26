<?php

declare(strict_types=1);

namespace Fintech\CommissionTask\Service;

use Fintech\CommissionTask\Config\Config;

class FileReader
{
    private string $fileName;

    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * Read file.
     *
     * @throws \Exception
     */
    public function read(): array
    {
        $returnArr = [];

        if (!file_exists($this->fileName)) {
            throw new \Exception('File '.$this->fileName.' not found');
        }

        $contents = file($this->fileName);

        foreach ($contents as $key => $row) {
            $row = trim($row); // remove the line ending after each row
            $returnArr[$key] = explode(Config::CSV_DELIMITER, $row);
        }

        return $returnArr;
    }
}
