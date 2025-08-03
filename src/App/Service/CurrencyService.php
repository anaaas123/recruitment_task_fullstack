<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CurrencyService
{
    private const SUPPORTED_CURRENCIES = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];
    private const NBP_API_BASE_URL = 'https://api.nbp.pl/api';
    private const CACHE_TTL = 3600; 

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache
    ) {}

    public function getCurrentRates(): array
    {
        return $this->cache->get('current_rates', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);

            $response = $this->httpClient->request(
                'GET',
                self::NBP_API_BASE_URL . '/exchangerates/tables/A/?format=json'
            );

            $dataArray = json_decode($response->getContent(), true);
            
            if (!is_array($dataArray) || !isset($dataArray[0]['rates'])) {
                return [];
            }

            $data = $dataArray[0];
            return $this->transformRates($data['rates']);
        });
    }

    public function getHistoricalRates(string $date): array
    {
        $cacheKey = 'historical_rates_' . $date;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($date) {
            $item->expiresAfter(self::CACHE_TTL);

            $endDate = new \DateTime($date);
            $startDate = (clone $endDate)->modify('-14 days');

            $rates = [];
            foreach (self::SUPPORTED_CURRENCIES as $currency) {
                $response = $this->httpClient->request(
                    'GET',
                    sprintf(
                        '%s/exchangerates/rates/A/%s/%s/%s/?format=json',
                        self::NBP_API_BASE_URL,
                        $currency,
                        $startDate->format('Y-m-d'),
                        $endDate->format('Y-m-d')
                    )
                );

                $data = json_decode($response->getContent(), true);

                if (!is_array($data) || !isset($data['rates'])) {
                    $rates[$currency] = [];
                } else {
                    $rates[$currency] = $this->transformHistoricalRates($data['rates'], $currency);
                }
            }

            return $rates;
        });
    }

    private function transformRates(array $rates): array
    {
        $transformedRates = [];

        foreach ($rates as $rate) {
            if (!in_array($rate['code'], self::SUPPORTED_CURRENCIES)) {
                continue;
            }

            $avgRate = $rate['mid'];
            $transformedRates[$rate['code']] = [
                'code' => $rate['code'],
                'currency' => $rate['currency'],
                'averageRate' => $avgRate,
                'buyingRate' => $this->calculateBuyingRate($rate['code'], $avgRate),
                'sellingRate' => $this->calculateSellingRate($rate['code'], $avgRate)
            ];
        }

        return $transformedRates;
    }

    private function transformHistoricalRates(array $rates, string $currency): array
    {
        return array_map(function($rate) use ($currency) {
            $avgRate = $rate['mid'];
            return [
                'date' => $rate['effectiveDate'],
                'averageRate' => $avgRate,
                'buyingRate' => $this->calculateBuyingRate($currency, $avgRate),
                'sellingRate' => $this->calculateSellingRate($currency, $avgRate)
            ];
        }, $rates);
    }

    private function calculateBuyingRate(string $currency, float $averageRate): ?float
    {
        if (in_array($currency, ['EUR', 'USD'])) {
            return round($averageRate - 0.15, 4);
        }

        return null; // Other currencies are not bought
    }

    private function calculateSellingRate(string $currency, float $averageRate): float
    {
        if (in_array($currency, ['EUR', 'USD'])) {
            return round($averageRate + 0.11, 4);
        }

        return round($averageRate + 0.20, 4);
    }
}
