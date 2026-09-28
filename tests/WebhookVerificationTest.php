<?php

namespace Paynecta\LaravelSdk\Tests;

use Illuminate\Support\Facades\Event;
use Paynecta\LaravelSdk\Events\PaymentSettledEvent;
use Paynecta\LaravelSdk\Services\WebhookService;

/**
 * The property these tests exist for: a delivery nobody can prove came from
 * Paynecta must not move money.
 *
 * Until 2.0 this package verified nothing. It checked five fields were
 * present and acted on the body, so anybody who knew the URL could post a
 * payment.completed and have an order marked paid. Every test below is one
 * way that used to work and now does not.
 */
class WebhookVerificationTest extends TestCase
{
    private const SECRET = 'whsec_testing_only';

    private function body(array $overrides = []): string
    {
        return json_encode(array_merge([
            'event' => 'payment.settled',
            'sent_at' => '2026-09-28T09:14:31Z',
            'data' => [
                'reference' => 'PN7K4QX2',
                'merchant_reference' => 'ORDER-7',
                'amount' => 124500,
                'status' => 'succeeded',
                'receipt' => 'NLJ7RT61SV',
            ],
        ], $overrides));
    }

    private function deliver(string $body, array $headers): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            'paynecta/webhook',
            [],
            [],
            [],
            collect($headers)->mapWithKeys(
                fn ($v, $k) => ['HTTP_' . str_replace('-', '_', strtoupper($k)) => $v]
            )->all(),
            $body
        );
    }

    private function signed(string $body, ?int $at = null): array
    {
        $ts = (string) ($at ?? time());

        return [
            WebhookService::HEADER_SIGNATURE => hash_hmac('sha256', $ts . '.' . $body, self::SECRET),
            WebhookService::HEADER_TIMESTAMP => $ts,
            WebhookService::HEADER_EVENT => 'payment.settled',
            WebhookService::HEADER_DELIVERY => 'dlv_' . bin2hex(random_bytes(6)),
            'Content-Type' => 'application/json',
        ];
    }

    public function test_a_properly_signed_delivery_is_accepted(): void
    {
        Event::fake([PaymentSettledEvent::class]);

        $body = $this->body();
        $this->deliver($body, $this->signed($body))->assertOk();

        Event::assertDispatched(PaymentSettledEvent::class, function (PaymentSettledEvent $e) {
            // Matched on the merchant's own reference, which is the whole
            // reason it is carried: ours is a string the app has never seen.
            return $e->merchantReference() === 'ORDER-7'
                && $e->amount() === 124500
                && $e->receipt() === 'NLJ7RT61SV';
        });
    }

    public function test_an_unsigned_delivery_is_refused(): void
    {
        Event::fake([PaymentSettledEvent::class]);

        // Exactly what an attacker sends: a well-formed body and no
        // signature. This is what used to mark an order paid.
        $this->deliver($this->body(), ['Content-Type' => 'application/json'])
            ->assertStatus(401);

        Event::assertNotDispatched(PaymentSettledEvent::class);
    }

    public function test_a_tampered_body_is_refused(): void
    {
        Event::fake([PaymentSettledEvent::class]);

        $body = $this->body();
        $headers = $this->signed($body);

        // The signature is real; the amount is not. Verifying a re-encoded
        // payload rather than the raw bytes is the usual way this passes.
        $tampered = str_replace('124500', '1', $body);

        $this->deliver($tampered, $headers)->assertStatus(401);

        Event::assertNotDispatched(PaymentSettledEvent::class);
    }

    public function test_a_replayed_delivery_is_refused_once_it_is_stale(): void
    {
        Event::fake([PaymentSettledEvent::class]);

        $body = $this->body();
        // Correctly signed for a timestamp ten minutes ago. A signature does
        // not expire on its own, so without a window this is valid for ever.
        $this->deliver($body, $this->signed($body, time() - 600))->assertStatus(401);

        Event::assertNotDispatched(PaymentSettledEvent::class);
    }

    public function test_a_delivery_signed_with_another_secret_is_refused(): void
    {
        Event::fake([PaymentSettledEvent::class]);

        $body = $this->body();
        $ts = (string) time();

        $this->deliver($body, [
            WebhookService::HEADER_SIGNATURE => hash_hmac('sha256', $ts . '.' . $body, 'not-our-secret'),
            WebhookService::HEADER_TIMESTAMP => $ts,
            'Content-Type' => 'application/json',
        ])->assertStatus(401);

        Event::assertNotDispatched(PaymentSettledEvent::class);
    }

    public function test_everything_is_refused_when_no_secret_is_configured(): void
    {
        // An application that has not registered its address cannot tell us
        // from a stranger. Failing closed is the point: accepting anything
        // while unconfigured is the hole this release replaces.
        config()->set('paynecta.webhook_secret', '');
        Event::fake([PaymentSettledEvent::class]);

        $body = $this->body();
        $this->deliver($body, $this->signed($body))->assertStatus(401);

        Event::assertNotDispatched(PaymentSettledEvent::class);
    }

    public function test_the_same_delivery_twice_only_fires_once(): void
    {
        Event::fake([PaymentSettledEvent::class]);

        $body = $this->body();
        $headers = $this->signed($body);

        $this->deliver($body, $headers)->assertOk();
        $this->deliver($body, $headers)->assertOk();

        // Both accepted, because refusing a repeat would put a correctly
        // signed delivery in the merchant's failed list.
        Event::assertDispatchedTimes(PaymentSettledEvent::class, 1);
    }

    public function test_a_test_send_is_accepted_and_acted_on_in_no_way(): void
    {
        Event::fake([PaymentSettledEvent::class]);

        $body = json_encode(['event' => 'webhook.test', 'data' => []]);
        $ts = (string) time();

        $this->deliver($body, [
            WebhookService::HEADER_SIGNATURE => hash_hmac('sha256', $ts . '.' . $body, self::SECRET),
            WebhookService::HEADER_TIMESTAMP => $ts,
            'Content-Type' => 'application/json',
        ])->assertOk();

        // Somebody pressed a button in their dashboard. Treating that as
        // news about money would mark an order paid from a settings screen.
        Event::assertNotDispatched(PaymentSettledEvent::class);
    }
}
