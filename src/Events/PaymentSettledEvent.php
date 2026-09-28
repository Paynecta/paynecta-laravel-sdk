<?php

namespace Paynecta\LaravelSdk\Events;

/**
 * The money arrived.
 *
 * The only event that means a payment was made. `payment.pending` means the
 * payer is looking at a prompt and has not answered; treating that as
 * payment is how an order ships for money that never moved.
 */
class PaymentSettledEvent extends PaymentEvent {}
