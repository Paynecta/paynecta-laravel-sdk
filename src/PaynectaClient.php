<?php

namespace Paynecta\LaravelSdk;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paynecta\LaravelSdk\Exceptions\PaynectaException;
use Paynecta\LaravelSdk\Exceptions\AuthenticationException;
use Paynecta\LaravelSdk\Exceptions\ValidationException;
use Paynecta\LaravelSdk\Exceptions\NotFoundException;
use Paynecta\LaravelSdk\Exceptions\RateLimitException;

class PaynectaClient
{
    protected string $apiKey;
    protected string $email;
    protected string $baseUrl;
    protected int $timeout;
    protected bool $logging;

    public function __construct(?string $apiKey = null, ?string $email = null)
    {
        $this->apiKey = $apiKey ?? config('paynecta.api_key');
        $this->email = $email ?? config('paynecta.email');
        $this->baseUrl = config('paynecta.base_url', 'https://paynecta.co.ke/api/v1');
        $this->timeout = config('paynecta.timeout', 30);
        $this->logging = config('paynecta.logging', false);

        if (empty($this->apiKey) || empty($this->email)) {
            throw new AuthenticationException(
                'API Key and Email are required. Set PAYNECTA_API_KEY and PAYNECTA_EMAIL in your .env file.'
            );
        }
    }

    /**
     * Verify authentication and get user information
     * 
     * @return array
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws PaynectaException
     */
    public function verifyAuth(): array
    {
        return $this->get('/auth/verify');
    }

    /**
     * Make a GET request
     * 
     * @param string $endpoint
     * @param array $params
     * @return array
     * @throws PaynectaException
     */
    protected function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Make a POST request
     * 
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws PaynectaException
     */
    protected function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Make a PUT request
     * 
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws PaynectaException
     */
    protected function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * Make a DELETE request
     * 
     * @param string $endpoint
     * @return array
     * @throws PaynectaException
     */
    protected function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Core request method
     * 
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws PaynectaException
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        if ($this->logging) {
            $this->log('info', "Paynecta API Request: {$method} {$url}", [
                'data' => $data
            ]);
        }

        try {
            $request = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-User-Email' => $this->email,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            $response = match(strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'DELETE' => $request->delete($url),
                default => throw new PaynectaException("Unsupported HTTP method: {$method}")
            };

            if ($this->logging) {
                $this->log('info', "Paynecta API Response: {$response->status()}", [
                    'body' => $response->json()
                ]);
            }

            return $this->handleResponse($response);

        } catch (PaynectaException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->logging) {
                $this->log('error', "Paynecta API Error: {$e->getMessage()}");
            }
            throw new PaynectaException("Request failed: {$e->getMessage()}", 0, null, null, $e);
        }
    }

    /**
     * Handle API response and throw appropriate exceptions
     * 
     * @param \Illuminate\Http\Client\Response $response
     * @return array
     * @throws PaynectaException
     */
    protected function handleResponse($response): array
    {
        $statusCode = $response->status();
        $body = $response->json();

        if ($response->successful()) {
            return $body;
        }

        $message = $body['message'] ?? 'An error occurred';
        $errorCode = $body['error_code'] ?? null;

        throw match($statusCode) {
            400 => new ValidationException($message, $errorCode, $body),
            401 => new AuthenticationException($message, $errorCode, $body),
            404 => new NotFoundException($message, $errorCode, $body),
            429 => new RateLimitException($message, $errorCode, $body),
            default => new PaynectaException($message, $statusCode, $errorCode, $body)
        };
    }

    /**
     * Set custom timeout in seconds
     * 
     * @param int $seconds
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set custom base URL (useful for testing)
     * 
     * @param string $url
     * @return self
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    /**
     * Enable or disable logging
     * 
     * @param bool $enabled
     * @return self
     */
    public function setLogging(bool $enabled): self
    {
        $this->logging = $enabled;
        return $this;
    }

    /**
     * Log a message
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $channel = config('paynecta.log_channel', 'stack');
        Log::channel($channel)->$level($message, $context);
    }
}