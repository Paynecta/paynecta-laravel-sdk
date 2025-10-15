<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

class PaymentService
{
    public function __construct(
        protected PaynectaClient $client
    ) {}

    /**
     * Initialize an M-Pesa STK Push payment
     * 
     * Triggers an STK Push prompt on the customer's phone to complete payment.
     * 
     * @param string $code Payment link unique code
     * @param string $mobileNumber Safaricom mobile number (without + prefix)
     * @param float|int $amount Payment amount in KES (min: 1, max: 250000)
     * @return array
     * @throws \Paynecta\LaravelSdk\Exceptions\ValidationException
     * @throws \Paynecta\LaravelSdk\Exceptions\NotFoundException
     * @throws \Paynecta\LaravelSdk\Exceptions\PaynectaException
     * 
     * @example
     * $payment = $paymentService->initialize('ABC123', '254700000000', 100);
     * 
     * Response:
     * [
     *     'success' => true,
     *     'message' => 'Payment initiated successfully. Check your phone for the STK push.',
     *     'data' => [
     *         'transaction_reference' => 'ABCP20240803123456ABCD',
     *         'CheckoutRequestID' => 'ws_CO_03082024123456789'
     *     ]
     * ]
     */
    public function initialize(string $code, string $mobileNumber, float|int $amount): array
    {
return $this->client->post('/payment/initialize', [
            'code' => $code,
            'mobile_number' => $this->formatMobileNumber($mobileNumber),
            'amount' => $amount
        ]);
    }

    /**
     * Initialize payment with additional validation
     * 
     * @param string $code Payment link unique code
     * @param string $mobileNumber Safaricom mobile number
     * @param float|int $amount Payment amount in KES
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Paynecta\LaravelSdk\Exceptions\PaynectaException
     */
    public function initializeWithValidation(string $code, string $mobileNumber, float|int $amount): array
    {
        // Validate amount
        if ($amount < 1 || $amount > 250000) {
            throw new \InvalidArgumentException('Amount must be between 1 and 250,000 KES');
        }

        // Validate mobile number
        $formatted = $this->formatMobileNumber($mobileNumber);
        if (!$this->isValidSafaricomNumber($formatted)) {
            throw new \InvalidArgumentException('Invalid Safaricom mobile number. Must be 07XX, 01XX, or 254XXX format');
        }

        return $this->initialize($code, $formatted, $amount);
    }

    /**
     * Format mobile number to standard format (254XXXXXXXXX)
     * 
     * @param string $mobileNumber
     * @return string
     */
    protected function formatMobileNumber(string $mobileNumber): string
    {
        // Remove any spaces, dashes, or plus signs
        $number = preg_replace('/[\s\-\+]/', '', $mobileNumber);

        // Convert 07XX or 01XX to 2547XX or 2541XX
        if (preg_match('/^0([17]\d{8})$/', $number, $matches)) {
            return '254' . $matches[1];
        }

        // If already in 254XXX format, return as is
        if (preg_match('/^254[17]\d{8}$/', $number)) {
            return $number;
        }

        // Return original if format is unclear
        return $number;
    }

    /**
     * Check if mobile number is a valid Safaricom number
     * 
     * @param string $mobileNumber
     * @return bool
     */
    protected function isValidSafaricomNumber(string $mobileNumber): bool
    {
        // Valid Safaricom formats:
        // - 254700000000 to 254799999999 (07XX series)
        // - 254100000000 to 254199999999 (01XX series)
        return preg_match('/^254[17]\d{8}$/', $mobileNumber) === 1;
    }

    /**
     * Get transaction reference from initialize response
     * 
     * @param array $response Response from initialize()
     * @return string|null
     */
    public function getTransactionReference(array $response): ?string
    {
        return $response['data']['transaction_reference'] ?? null;
    }

    /**
     * Get checkout request ID from initialize response
     * 
     * @param array $response Response from initialize()
     * @return string|null
     */
    public function getCheckoutRequestId(array $response): ?string
    {
        return $response['data']['CheckoutRequestID'] ?? null;
    }

    /**
     * Check if initialization was successful
     * 
     * @param array $response Response from initialize()
     * @return bool
     */
    public function wasSuccessful(array $response): bool
    {
        return ($response['success'] ?? false) === true;
    }

    /**
     * Query payment transaction status
     * 
     * Check the status of a payment transaction using the transaction reference.
     * 
     * @param string $transactionReference Transaction reference from payment initialization
     * @return array
     * @throws \Paynecta\LaravelSdk\Exceptions\NotFoundException
     * @throws \Paynecta\LaravelSdk\Exceptions\PaynectaException
     * 
     * @example
     * $status = $paymentService->queryStatus('ABCP20240803123456ABCD');
     * 
     * Response:
     * [
     *     'success' => true,
     *     'message' => 'Transaction status retrieved successfully',
     *     'data' => [
     *         'transaction_reference' => 'ABCP20240803123456ABCD',
     *         'status' => 'completed',
     *         'status_label' => 'Completed',
     *         'amount' => 100,
     *         'formatted_amount' => 'KES 100.00',
     *         'mobile_number' => '254700000000',
     *         'mpesa_receipt_number' => 'QGR7I8J9K0',
     *         'result_code' => 0,
     *         'paid_at' => '2024-08-03T12:01:00.000000Z',
     *         ...
     *     ]
     * ]
     */
    public function queryStatus(string $transactionReference): array
    {
        return $this->client->get('/payment/status', [
            'transaction_reference' => $transactionReference
        ]);
    }

    /**
     * Get payment status from status response
     * 
     * @param array $response Response from queryStatus()
     * @return string|null Status: pending, processing, completed, failed, cancelled
     */
    public function getStatus(array $response): ?string
    {
        return $response['data']['status'] ?? null;
    }

    /**
     * Get human-readable status label
     * 
     * @param array $response Response from queryStatus()
     * @return string|null
     */
    public function getStatusLabel(array $response): ?string
    {
        return $response['data']['status_label'] ?? null;
    }

    /**
     * Check if payment is completed
     * 
     * @param array $response Response from queryStatus()
     * @return bool
     */
    public function isCompleted(array $response): bool
    {
        return $this->getStatus($response) === 'completed';
    }

    /**
     * Check if payment is pending
     * 
     * @param array $response Response from queryStatus()
     * @return bool
     */
    public function isPending(array $response): bool
    {
        $status = $this->getStatus($response);
        return $status === 'pending' || $status === 'processing';
    }

    /**
     * Check if payment has failed
     * 
     * @param array $response Response from queryStatus()
     * @return bool
     */
    public function isFailed(array $response): bool
    {
        return $this->getStatus($response) === 'failed';
    }

    /**
     * Check if payment was cancelled
     * 
     * @param array $response Response from queryStatus()
     * @return bool
     */
    public function isCancelled(array $response): bool
    {
        return $this->getStatus($response) === 'cancelled';
    }

    /**
     * Get M-Pesa receipt number (for completed payments)
     * 
     * @param array $response Response from queryStatus()
     * @return string|null
     */
    public function getMpesaReceiptNumber(array $response): ?string
    {
        return $response['data']['mpesa_receipt_number'] ?? null;
    }

    /**
     * Get failure reason (for failed payments)
     * 
     * @param array $response Response from queryStatus()
     * @return string|null
     */
    public function getFailureReason(array $response): ?string
    {
        return $response['data']['failure_reason'] ?? null;
    }

    /**
     * Get payment amount
     * 
     * @param array $response Response from queryStatus()
     * @return float|null
     */
    public function getAmount(array $response): ?float
    {
        return $response['data']['amount'] ?? null;
    }

    /**
     * Get formatted amount with currency
     * 
     * @param array $response Response from queryStatus()
     * @return string|null
     */
    public function getFormattedAmount(array $response): ?string
    {
        return $response['data']['formatted_amount'] ?? null;
    }

    /**
     * Get payment completion timestamp
     * 
     * @param array $response Response from queryStatus()
     * @return string|null ISO 8601 format (UTC)
     */
    public function getPaidAt(array $response): ?string
    {
        return $response['data']['paid_at'] ?? null;
    }

    /**
     * Poll payment status until completion or timeout
     * 
     * Repeatedly check payment status until it's completed, failed, or timeout is reached.
     * 
     * @param string $transactionReference Transaction reference
     * @param int $maxAttempts Maximum number of attempts (default: 30)
     * @param int $sleepSeconds Seconds to wait between attempts (default: 2)
     * @return array Final status response
     * @throws \Paynecta\LaravelSdk\Exceptions\PaynectaException
     * 
     * @example
     * $finalStatus = $paymentService->pollStatus('ABCP20240803123456ABCD', 30, 2);
     * if ($paymentService->isCompleted($finalStatus)) {
     *     echo "Payment completed!";
     * }
     */
    public function pollStatus(string $transactionReference, int $maxAttempts = 30, int $sleepSeconds = 2): array
    {
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $response = $this->queryStatus($transactionReference);
            
            // If completed, failed, or cancelled, stop polling
            if ($this->isCompleted($response) || 
                $this->isFailed($response) || 
                $this->isCancelled($response)) {
                return $response;
            }
            
            $attempts++;
            
            if ($attempts < $maxAttempts) {
                sleep($sleepSeconds);
            }
        }
        
        // Return last response even if still pending
        return $response;
    }
}