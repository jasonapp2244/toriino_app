<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a PaymentIntent for one-time payments (courses, sessions).
     */
    public function createPaymentIntent(float $amount, string $currency = 'usd', array $metadata = [])
    {
        return PaymentIntent::create([
            'amount'   => (int) ($amount * 100), // Stripe uses cents
            'currency' => $currency,
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ]);
    }

    /**
     * Create a Stripe Checkout Session for subscriptions.
     */
    public function createSubscriptionCheckout(
        string $plan,
        float $price,
        int $userId,
        string $role,
        string $successUrl,
        string $cancelUrl
    ): StripeSession {
        return StripeSession::create([
            'mode'       => 'payment', // one-time for simplicity; use 'subscription' for recurring
            'line_items' => [[
                'price_data' => [
                    'currency'     => 'usd',
                    'product_data' => [
                        'name'        => ucfirst($role) . ' ' . ucfirst($plan) . ' Subscription',
                        'description' => "Torrino {$role} {$plan} plan",
                    ],
                    'unit_amount' => (int) ($price * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'user_id' => $userId,
                'plan'    => $plan,
                'role'    => $role,
                'type'    => 'subscription',
            ],
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
        ]);
    }

    /**
     * Retrieve a PaymentIntent by ID.
     */
    public function retrievePaymentIntent(string $paymentIntentId)
    {
        return PaymentIntent::retrieve($paymentIntentId);
    }

    /**
     * Construct webhook event from payload.
     */
    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return Webhook::constructEvent(
            $payload,
            $sigHeader,
            config('services.stripe.webhook_secret')
        );
    }
}
