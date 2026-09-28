<?php

namespace Paynecta\LaravelSdk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paynecta\LaravelSdk\Exceptions\AuthenticationException;
use Paynecta\LaravelSdk\Exceptions\NotFoundException;
use Paynecta\LaravelSdk\Exceptions\PaynectaException;
use Paynecta\LaravelSdk\Exceptions\RateLimitException;
use Paynecta\LaravelSdk\Exceptions\ValidationException;
use Paynecta\LaravelSdk\Services\BalanceService;
use Paynecta\LaravelSdk\Services\BankService;
use Paynecta\LaravelSdk\Services\CheckoutService;
use Paynecta\LaravelSdk\Services\IntegrationService;
use Paynecta\LaravelSdk\Services\TapFlowService;
use Paynecta\LaravelSdk\Services\TransactionService;
use Paynecta\LaravelSdk\Services\WebhookEndpointService;

/**
 * The connection to Paynecta.
 *
 * Authentication is two steps and this hides the first. The key pair goes
 * once to POST /v1/auth/token as a Basic credential, and everything
 * afterwards carries the access token it returns. The secret key is never a
 * header on an ordinary call, which is the point of the exchange: one
 * endpoint sees it, and checking it is that endpoint's whole job.
 *
 * The token is cached, because it lasts an hour and that endpoint allows ten
 * requests a minute on purpose — it is where somebody would sit and guess at
 * a key. The cache entry is keyed on the public key, so changing the pair
 * cannot serve a token belonging to the account configured before it.
 */
class PaynectaClient
{
    protected string $publicKey;
    protected string $secretKey;
    protected string $baseUrl;
    protected int $timeout;
    protected bool $logging;

    protected ?CheckoutService $checkouts = null;
    protected ?TransactionService $transactions = null;
    protected ?TapFlowService $tapflows = null;
    protected ?WebhookEndpointService $webhooks = null;
    protected ?IntegrationService $integrations = null;
    protected ?BankService $banks = null;
    protected ?BalanceService $balance = null;

    public function __construct(?string $publicKey = null, ?string $secretKey = null)
    {
        $this->publicKey = (string) ($publicKey ?? config('paynecta.public_key'));
        $this->secretKey = (string) ($secretKey ?? config('paynecta.secret_key'));
        $this->baseUrl = rtrim((string) config('paynecta.base_url', 'https://api.paynecta.co.ke'), '/');
        $this->timeout = (int) config('paynecta.timeout', 30);
        $this->logging = (bool) config('paynecta.logging', false);

        if ($this->publicKey === '' || $this->secretKey === '') {
            throw new AuthenticationException(
                'Paynecta needs a key pair. Set PAYNECTA_PUBLIC_KEY and PAYNECTA_SECRET_KEY '
                . 'in your .env, from Developers, API keys in your dashboard.'
            );
        }
    }

    /** Opening a checkout for a customer, and asking what became of it. */
    public function checkouts(): CheckoutService
    {
        return $this->checkouts ??= new CheckoutService($this);
    }

    /** The payments this business has taken. */
    public function transactions(): TransactionService
    {
        return $this->transactions ??= new TransactionService($this);
    }

    /** The pages this business collects through. */
    public function tapflows(): TapFlowService
    {
        return $this->tapflows ??= new TapFlowService($this);
    }

    /** Where Paynecta should tell you what happened. */
    public function webhooks(): WebhookEndpointService
    {
        return $this->webhooks ??= new WebhookEndpointService($this);
    }

    /** This application, as it appears in the dashboard. */
    public function integrations(): IntegrationService
    {
        return $this->integrations ??= new IntegrationService($this);
    }

    /** The banks Paynecta can settle to. */
    public function banks(): BankService
    {
        return $this->banks ??= new BankService($this);
    }

    /** What is left to spend on fees and verification messages. */
    public function balance(): BalanceService
    {
        return $this->balance ??= new BalanceService($this);
    }

    /**
     * Which business this key belongs to, and which world it reaches.
     *
     * The first call worth making: it answers "are my keys right, and am I
     * in test or live" in one request, which are the two questions behind
     * most of the time lost at the start of an integration.
     */
    public function whoami(): array
    {
        return $this->get('/v1/whoami');
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->send('POST', $path, $body);
    }

    public function put(string $path, array $body = []): array
    {
        return $this->send('PUT', $path, $body);
    }

    public function delete(string $path, array $body = []): array
    {
        return $this->send('DELETE', $path, $body);
    }

    /**
     * An access token, from the key pair.
     *
     * A minute is taken off whatever we are told, so a token expiring
     * mid-request is not how we find out it was close.
     */
    public function accessToken(bool $fresh = false): string
    {
        $key = $this->tokenCacheKey();

        if ($fresh) {
            Cache::forget($key);
        }

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Accept' => 'application/json'])
            ->withBasicAuth($this->publicKey, $this->secretKey)
            ->post($this->baseUrl . '/v1/auth/token');

        if ($response->status() === 401 || $response->status() === 403) {
            // The API refuses a wrong public key and a wrong secret
            // identically on purpose, so there is nothing more specific to
            // say here either.
            throw new AuthenticationException(
                $response->json('message') ?: 'Paynecta did not accept those keys.'
            );
        }

        $token = $response->json('data.access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new PaynectaException(
                $response->json('message') ?: 'Could not get an access token from Paynecta.'
            );
        }

        $ttl = (int) ($response->json('data.expires_in') ?: 3600);
        Cache::put($key, $token, max(60, $ttl - 60));

        return $token;
    }

    /** Throws the token away, so the next call fetches a new one. */
    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    protected function tokenCacheKey(): string
    {
        return 'paynecta.token.' . sha1($this->publicKey);
    }

    protected function send(string $method, string $path, array $payload): array
    {
        $url = $this->baseUrl . $path;

        $this->log('Paynecta request', [
            'method' => $method,
            'url' => $url,
            // The body, scrubbed. Never the token, and never a key.
            'payload' => $this->scrub($payload),
        ]);

        $response = $this->dispatch($method, $url, $payload, $this->accessToken());

        // One retry, and only on a 401.
        //
        // A token can expire between being handed out and being used: a
        // queued job may sit for an hour, and a cache shared by several
        // processes can hold one that another process has already replaced.
        // Retrying once with a fresh token turns that into nothing, and
        // limiting it to 401 means genuinely wrong keys fail immediately
        // rather than being tried twice against a rate-limited endpoint.
        if ($response->status() === 401) {
            $response = $this->dispatch($method, $url, $payload, $this->accessToken(true));
        }

        $this->log('Paynecta response', [
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
        ]);

        return $this->interpret($response);
    }

    protected function dispatch(string $method, string $url, array $payload, string $token): Response
    {
        /** @var PendingRequest $request */
        $request = Http::timeout($this->timeout)
            ->withToken($token)
            ->withHeaders(['Accept' => 'application/json']);

        return match (strtoupper($method)) {
            'GET' => $request->get($url, $payload),
            'POST' => $request->post($url, $payload),
            'PUT' => $request->put($url, $payload),
            'DELETE' => $request->delete($url, $payload),
            default => throw new PaynectaException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Turn an answer into data, or into the right exception.
     *
     * Paynecta's refusals carry a message to show and a next step to take,
     * and both are kept: an integrator reading "That page charges a set
     * amount" knows what to do, where "422 Unprocessable Entity" starts a
     * support conversation.
     */
    protected function interpret(Response $response): array
    {
        $body = $response->json() ?? [];
        $message = $body['message'] ?? 'Paynecta could not complete that request.';
        $next = $body['next_step'] ?? null;
        $full = $next ? rtrim($message, '.') . '. ' . $next : $message;

        if ($response->successful()) {
            return is_array($body['data'] ?? null) ? $body['data'] : $body;
        }

        throw match ($response->status()) {
            401, 403 => new AuthenticationException($full, $response->status()),
            404 => new NotFoundException($full, 404),
            422 => new ValidationException($full, 422),
            429 => new RateLimitException($full, 429),
            default => new PaynectaException($full, $response->status()),
        };
    }

    /**
     * Anything that should not end up in a log file.
     *
     * A payload is logged to help somebody work out what a call sent, and a
     * log file is read by more people and kept longer than anybody intends.
     */
    protected function scrub(array $payload): array
    {
        foreach (['secret', 'secret_key', 'public_key', 'client_secret', 'token', 'access_token'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }

    protected function log(string $message, array $context): void
    {
        if (! $this->logging) {
            return;
        }

        Log::channel(config('paynecta.log_channel'))->info($message, $context);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
