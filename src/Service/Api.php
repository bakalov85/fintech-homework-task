<?php

declare(strict_types=1);

namespace Fintech\CommissionTask\Service;

class Api
{
    /**
     * Perform GET request to specified URL.
     *
     * @throws \Exception
     */
    public static function get(string $url): string
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Accept: application/json',
        ];

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);
        curl_close($curl);

        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if (!$resp || $statusCode !== 200) {
            throw new \Exception('Error getting exchange rates. Check the URL in app\Config.php');
        }

        return $resp;
    }
}
