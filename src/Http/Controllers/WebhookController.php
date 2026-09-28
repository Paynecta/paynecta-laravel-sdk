<?php

namespace Paynecta\LaravelSdk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Paynecta\LaravelSdk\Exceptions\WebhookException;
use Paynecta\LaravelSdk\Services\WebhookService;

class WebhookController extends Controller
{
    public function __construct(protected WebhookService $webhooks) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $result = $this->webhooks->handle($request);
        } catch (WebhookException $e) {
            // Logged, because an unverifiable delivery is either a
            // misconfiguration worth fixing or somebody probing, and both
            // are worth seeing.
            Log::channel(config('paynecta.log_channel'))->warning(
                'Paynecta webhook refused', ['reason' => $e->getMessage()]
            );

            return response()->json(['message' => 'Could not verify this delivery.'], 401);
        } catch (\Throwable $e) {
            // A listener threw. 500 on purpose: Paynecta retries, and this
            // is a delivery we failed to process rather than one we refused.
            // Answering 200 would lose it silently.
            Log::channel(config('paynecta.log_channel'))->error(
                'Paynecta webhook failed while being handled',
                ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]
            );

            return response()->json(['message' => 'Could not process this delivery.'], 500);
        }

        // 200 for a duplicate and for an event we do not handle. Both are
        // correctly addressed and correctly signed; answering with an error
        // would put them in the merchant's failed list and eventually get
        // the address switched off.
        return response()->json($result, 200);
    }
}
