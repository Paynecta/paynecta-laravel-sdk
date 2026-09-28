# Paynecta Laravel SDK

Take M-Pesa payments in a Laravel application. Money settles to the Kenyan
bank account, paybill or till you chose, already reconciled.

```bash
composer require paynecta/paynecta-laravel-sdk
```

---

## Upgrading from 1.x

**1.x does not work and cannot be made to.** Every endpoint it called has
been withdrawn: `paynecta.co.ke/api/v1` answers 404, and `X-API-Key` with
`X-User-Email` is no longer how anything authenticates. If payments stopped
working, that is why.

There is no compatibility shim, because there is nothing left to be
compatible with. Three things change:

**1. Your credentials are now a key pair.**

```diff
- PAYNECTA_API_KEY=...
- PAYNECTA_EMAIL=you@example.com
+ PAYNECTA_PUBLIC_KEY=pyn_pk_live_...
+ PAYNECTA_SECRET_KEY=pyn_sk_live_...
+ PAYNECTA_TAPFLOW=your-payment-page
```

Get them from **Developers → API keys** in your dashboard. The secret is
shown once.

**2. You no longer send a phone number.**

```diff
- Paynecta::payments()->initialize($phone, $amount, $reference);
+ $checkout = Paynecta::checkouts()->open($amountInCents, [
+     'reference'    => $order->id,
+     'redirect_url' => route('orders.show', $order),
+ ]);
+ return redirect()->away($checkout['url']);
```

The old call took a phone number and pushed a prompt at it. Around fifteen
thousand unwanted prompts went out that way and the shortcode was flagged,
which is why the API has no such field now. Your customer enters their own
number on Paynecta's page and confirms it with a code before anything
reaches their phone.

**3. Webhooks are verified, and were not before.**

1.x checked that five fields were present and then acted on the body.
Anybody who knew your webhook URL could post a `payment.completed` and have
an order marked paid. You now need a signing secret, and without one **every
delivery is refused**:

```bash
php artisan paynecta:register
```

That registers your address and prints the secret to put in
`PAYNECTA_WEBHOOK_SECRET`.

Event names changed with the API's: `PaymentCompletedEvent` →
`PaymentSettledEvent`, `PaymentCancelledEvent` → `PaymentFailedEvent`, and
`PaymentPendingEvent` is new.

---

## Setup

```bash
php artisan vendor:publish --tag=paynecta-config
php artisan paynecta:register
```

```env
PAYNECTA_PUBLIC_KEY=pyn_pk_live_...
PAYNECTA_SECRET_KEY=pyn_sk_live_...
PAYNECTA_TAPFLOW=your-payment-page
PAYNECTA_WEBHOOK_SECRET=whsec_...
```

Check it works:

```php
Paynecta::whoami();
```

## Taking a payment

Amounts are **integers, in cents**. `124500` is KES 1,245.00. Never a float:
one that is nearly 1245.00 is somebody's money being nearly right.

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

$checkout = Paynecta::checkouts()->open(124500, [
    'reference'    => $order->id,           // your handle. match on this.
    'redirect_url' => route('orders.show', $order),
    'cancel_url'   => route('checkout'),
]);

$order->update(['paynecta_session' => $checkout['id']]);

return redirect()->away($checkout['url']);
```

The payer finishes on Paynecta and comes back. **Their arrival is not proof
of payment** — anybody can type that URL. Learn the outcome from a webhook,
or by asking:

```php
Paynecta::checkouts()->settled($order->paynecta_session);   // bool
Paynecta::checkouts()->find($order->paynecta_session);      // the whole thing
```

## Hearing what happened

```php
use Paynecta\LaravelSdk\Events\PaymentSettledEvent;

class FulfilOrder
{
    public function handle(PaymentSettledEvent $event): void
    {
        // Match on your own reference. Paynecta's is a string you have
        // never seen before this arrived, and matching on amount and time
        // goes wrong the day two customers buy the same thing a minute
        // apart.
        $order = Order::find($event->merchantReference());

        $order->markPaid(
            receipt: $event->receipt(),      // M-Pesa's receipt
            amount:  $event->amount(),       // cents
            payer:   $event->payer(),        // masked, always
        );
    }
}
```

`PaymentSettledEvent` is the **only** one that means money moved.
`PaymentPendingEvent` means the payer is looking at a prompt and has not
answered; treating it as payment ships orders for money that never arrived.

Deliveries can arrive twice — we would rather tell you twice than not at
all. The package remembers delivery ids and fires your listener once, but
the cache is a convenience and not a ledger: **write handlers that are safe
to run twice.**

### Verifying it yourself

If you handle deliveries on your own route:

```php
app(\Paynecta\LaravelSdk\Services\WebhookService::class)->verify($request);
```

It throws `WebhookException` when it cannot be trusted. Verify the **raw
body**, not a re-encoded payload — that is the usual way a verification
routine comes to pass everything.

## Everything else

```php
Paynecta::tapflows()->all(usableOnly: true);     // pages you can collect into
Paynecta::tapflows()->create('Shop', ['slug' => 'shop']);

Paynecta::transactions()->all(['status' => 'succeeded']);
Paynecta::transactions()->find('PN7K4QX2');

Paynecta::webhooks()->all();                      // addresses, with secrets
Paynecta::webhooks()->registerSelf();             // point us at this app
Paynecta::webhooks()->disable();
Paynecta::webhooks()->remove();

Paynecta::integrations()->register();             // show under Installations
Paynecta::integrations()->all();

Paynecta::banks()->all();
Paynecta::balance()->get();
Paynecta::whoami();
```

Anything not wrapped is still reachable:

```php
Paynecta::get('/v1/transactions', ['per_page' => 100]);
```

## Errors

| Thrown | When |
| --- | --- |
| `AuthenticationException` | keys refused, or the account cannot do this |
| `ValidationException` | the request was wrong, with the reason |
| `NotFoundException` | no such payment, page or session |
| `RateLimitException` | too many requests |
| `WebhookException` | a delivery that could not be verified |
| `PaynectaException` | the parent of all of them |

Messages carry Paynecta's own wording and the next step, so
`"That page charges a set amount, so a session cannot name another one"`
reaches your logs rather than `422`.

## Configuration

Everything in `config/paynecta.php` reads from `.env`. Worth knowing:

- `webhook_tolerance` — how old a delivery may be, default 300 seconds. A
  signature does not expire on its own; without a window a captured delivery
  replays for ever.
- `register_installation` — off by default. It reports your app's URL and
  name, which is yours to opt into.
- `logging` — requests and responses. Keys and tokens are never written,
  whatever this is set to.

## Requirements

PHP 8.2+, Laravel 10, 11 or 12, and a Paynecta account. Payments are in
Kenyan shillings over Safaricom M-Pesa.

## Tests

```bash
composer install && ./vendor/bin/phpunit
```

## Documentation

[docs.paynecta.co.ke](https://docs.paynecta.co.ke/api/introduction)
