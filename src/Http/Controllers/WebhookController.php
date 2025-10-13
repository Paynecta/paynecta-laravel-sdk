<?php

namespace Paynecta\LaravelSdk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Paynecta\LaravelSdk\Services\WebhookService;

class WebhookController extends Controller
{
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle incoming Paynecta webhook
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->handleWebhook($request);
            
            return response()->json($result, 200);
            
        } catch (\Exception $e) {
            // Always return 200 to acknowledge receipt
            // Log the error for debugging
            if (config('paynecta.logging')) {
                Log::error('Paynecta Webhook Error: ' . $e->getMessage(), [
                    'payload' => $request->all()
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook received'
            ], 200);
        }
    }
}