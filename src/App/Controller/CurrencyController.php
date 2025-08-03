<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CurrencyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CurrencyController extends AbstractController
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}

    public function getCurrentRates(): JsonResponse
    {
        try {
            $rates = $this->currencyService->getCurrentRates();
            return $this->json($rates);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getHistoricalRates(Request $request): JsonResponse
    {
        try {
            $date = $request->query->get('date', date('Y-m-d'));
            $rates = $this->currencyService->getHistoricalRates($date);
            return $this->json($rates);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
