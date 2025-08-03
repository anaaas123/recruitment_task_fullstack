<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CurrencyController;
use App\Service\CurrencyService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CurrencyControllerTest extends TestCase
{
    private function buildControllerWithMockedContainer(CurrencyService $service): CurrencyController
    {
        $controller = new CurrencyController($service);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $controller->setContainer($container);

        return $controller;
    }

    public function testGetCurrentRatesReturnsJsonResponse(): void
    {
        $mockService = $this->createMock(CurrencyService::class);
        $mockService->method('getCurrentRates')->willReturn([
            'EUR' => [
                'code'        => 'EUR',
                'currency'    => 'euro',
                'averageRate' => 5.00,
                'buyingRate'  => 4.85,
                'sellingRate' => 5.11,
            ],
        ]);

        $controller = $this->buildControllerWithMockedContainer($mockService);
        $response   = $controller->getCurrentRates();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('EUR', $data);
        $this->assertSame(5.00, (float) $data['EUR']['averageRate']);

    }

    public function testGetHistoricalRatesReturnsJsonResponse(): void
    {
        $mockService = $this->createMock(CurrencyService::class);
        $mockService->method('getHistoricalRates')->willReturn([
            'EUR' => [
                [
                    'date'        => '2023-07-20',
                    'averageRate' => 4.95,
                    'buyingRate'  => 4.80,
                    'sellingRate' => 5.06,
                ],
            ],
        ]);

        $controller = $this->buildControllerWithMockedContainer($mockService);

        $request  = new Request(['date' => '2023-07-20']);
        $response = $controller->getHistoricalRates($request);

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('EUR', $data);
        $this->assertSame('2023-07-20', $data['EUR'][0]['date']);
        $this->assertSame(4.95, $data['EUR'][0]['averageRate']);
    }

    public function testGetHistoricalRatesReturnsEmptyArray(): void
    {
        $mockService = $this->createMock(CurrencyService::class);
        $mockService->method('getHistoricalRates')->willReturn([]);

        $controller = $this->buildControllerWithMockedContainer($mockService);

        $request  = new Request(['date' => '2023-07-20']);
        $response = $controller->getHistoricalRates($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEmpty($data);
    }
}
