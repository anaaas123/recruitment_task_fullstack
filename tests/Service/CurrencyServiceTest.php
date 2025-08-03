<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CurrencyService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CurrencyServiceTest extends TestCase
{
    private CurrencyService $currencyService;

    protected function setUp(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $cache = $this->createMock(CacheInterface::class);

        $this->currencyService = new CurrencyService($httpClient, $cache);
    }

    public function testCalculateBuyingRate(): void
    {
        $reflection = new \ReflectionClass($this->currencyService);
        $method = $reflection->getMethod('calculateBuyingRate');
        $method->setAccessible(true);

        $this->assertSame(4.85, $method->invoke($this->currencyService, 'EUR', 5.00));
        $this->assertSame(3.35, $method->invoke($this->currencyService, 'USD', 3.50));

        $this->assertNull($method->invoke($this->currencyService, 'BRL', 1.00));
    }

    public function testCalculateSellingRate(): void
    {
        $reflection = new \ReflectionClass($this->currencyService);
        $method = $reflection->getMethod('calculateSellingRate');
        $method->setAccessible(true);

        // Test for EUR and USD
        $this->assertSame(5.11, $method->invoke($this->currencyService, 'EUR', 5.00));
        $this->assertSame(3.61, $method->invoke($this->currencyService, 'USD', 3.50));

        // Test for other currencies (with +0.20)
        $this->assertSame(1.20, $method->invoke($this->currencyService, 'BRL', 1.00));
        $this->assertSame(2.20, $method->invoke($this->currencyService, 'CZK', 2.00));
    }

    public function testGetCurrentRatesReturnsTransformedData(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockResponse = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);

        $mockResponse->method('getContent')->willReturn(json_encode([[ 
            'table' => 'A',
            'rates' => [
                ['currency' => 'euro', 'code' => 'EUR', 'mid' => 5.00],
                ['currency' => 'dolar amerykański', 'code' => 'USD', 'mid' => 3.50],
                ['currency' => 'real brazylijski', 'code' => 'BRL', 'mid' => 1.00],
            ],
        ]]));

        $mockHttpClient->method('request')->willReturn($mockResponse);

        $mockCache = $this->createMock(CacheInterface::class);
        $mockCache->method('get')->willReturnCallback(function ($key, $callback) {
            return $callback(new class implements ItemInterface {
                public function isHit(): bool { return false; }
                public function get() {}
                public function set($value): ItemInterface { return $this; }
                public function expiresAfter($time): ItemInterface { return $this; }
                public function expiresAt($expiration): ItemInterface { return $this; }
                public function tag($tags): ItemInterface { return $this; }
                public function getMetadata(): array { return []; }
                public function getKey(): string { return 'current_rates'; }
            });
        });

        $service = new CurrencyService($mockHttpClient, $mockCache);
        $rates = $service->getCurrentRates();

        $this->assertArrayHasKey('EUR', $rates);
        $this->assertEquals(4.85, $rates['EUR']['buyingRate']);  // buyingRate = avgRate - 0.15
        $this->assertEquals(5.11, $rates['EUR']['sellingRate']); // sellingRate = avgRate + 0.11
        $this->assertNull($rates['BRL']['buyingRate']); // BRL buying rate is null, as expected
    }

    public function testGetHistoricalRatesReturnsTransformedData(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockCache = $this->createMock(CacheInterface::class);

        $mockResponse = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(json_encode([
            'rates' => [
                ['effectiveDate' => '2023-07-20', 'mid' => 4.95],
                ['effectiveDate' => '2023-07-21', 'mid' => 4.97],
            ],
        ]));

        $mockHttpClient->method('request')->willReturn($mockResponse);

        $mockCache->method('get')->willReturnCallback(function ($key, $callback) {
            return $callback(new class implements ItemInterface {
                public function isHit(): bool { return false; }
                public function get() {}
                public function set($value): ItemInterface { return $this; }
                public function expiresAfter($time): ItemInterface { return $this; }
                public function expiresAt($expiration): ItemInterface { return $this; }
                public function tag($tags): ItemInterface { return $this; }
                public function getMetadata(): array { return []; }
                public function getKey(): string { return 'historical_rates'; }
            });
        });

        $service = new CurrencyService($mockHttpClient, $mockCache);

        $date = '2023-07-21';
        $historicalRates = $service->getHistoricalRates($date);

        $this->assertArrayHasKey('EUR', $historicalRates);
        $this->assertIsArray($historicalRates['EUR']);
        $this->assertCount(2, $historicalRates['EUR']);

        $firstRate = $historicalRates['EUR'][0];
        $this->assertSame('2023-07-20', $firstRate['date']);
        $this->assertEquals(4.80, $firstRate['buyingRate']);  // 4.95 - 0.15 = 4.80
        $this->assertEquals(5.06, $firstRate['sellingRate']); // 4.95 + 0.11 = 5.06
    }

    public function testGetCurrentRatesWithInvalidApiResponse(): void
{
    $mockHttpClient = $this->createMock(HttpClientInterface::class);
    $mockResponse = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
    $mockResponse->method('getContent')->willReturn(''); 
    $mockHttpClient->method('request')->willReturn($mockResponse);

    $mockCache = $this->createMock(CacheInterface::class);
    $mockCache->method('get')->willReturnCallback(fn($key, $callback) => $callback(new class implements ItemInterface {
        public function isHit(): bool { return false; }
        public function get() {}
        public function set($value): ItemInterface { return $this; }
        public function expiresAfter($time): ItemInterface { return $this; }
        public function expiresAt($expiration): ItemInterface { return $this; }
        public function tag($tags): ItemInterface { return $this; }
        public function getMetadata(): array { return []; }
        public function getKey(): string { return 'current_rates'; }
    }));

    $service = new CurrencyService($mockHttpClient, $mockCache);

    $rates = $service->getCurrentRates();

    $this->assertIsArray($rates);
    $this->assertEmpty($rates, 'Expected empty array on invalid API response');
}
}
