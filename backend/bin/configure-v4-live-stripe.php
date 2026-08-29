<?php
declare(strict_types=1);

/**
 * Create/reuse the approved Growth Alignment V4 LIVE Stripe products/prices
 * and save their Price IDs into V4 global_settings.
 *
 * Safe to rerun: products are matched by metadata and prices by product,
 * currency and amount before anything new is created.
 */

$container = require __DIR__ . '/../src/bootstrap.php';

$settings = $container['settings'];
$secret = trim((string) $settings->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? ''));
$webhook = trim((string) $settings->get('stripe.webhook_secret', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''));

if ($secret === '') {
    fwrite(STDERR, "ERROR: Stripe secret key is not configured.\n");
    exit(1);
}

if (!str_starts_with($secret, 'sk_live_')) {
    fwrite(STDERR, "ERROR: Refusing to configure V4 prices because Stripe is not in LIVE mode.\n");
    exit(1);
}

if ($webhook === '') {
    fwrite(STDERR, "ERROR: Stripe webhook secret is not configured.\n");
    exit(1);
}

$stripe = new \Stripe\StripeClient($secret);

$catalog = [
    [
        'kind' => 'full_report',
        'track' => 'personal',
        'name' => 'Growth Alignment - Personal',
        'amount' => 499,
        'setting' => 'stripe.price_personal',
    ],
    [
        'kind' => 'full_report',
        'track' => 'newjoiner',
        'name' => 'Growth Alignment - New Joiner',
        'amount' => 1995,
        'setting' => 'stripe.price_newjoiner',
    ],
    [
        'kind' => 'full_report',
        'track' => 'manager',
        'name' => 'Growth Alignment - Manager',
        'amount' => 4995,
        'setting' => 'stripe.price_manager',
    ],
    [
        'kind' => 'full_report',
        'track' => 'executive',
        'name' => 'Growth Alignment - Executive',
        'amount' => 9995,
        'setting' => 'stripe.price_executive',
    ],
    [
        'kind' => 'retest',
        'track' => 'personal',
        'name' => 'Growth Alignment - Personal Retest',
        'amount' => 299,
        'setting' => 'stripe.retest_price_personal',
    ],
    [
        'kind' => 'retest',
        'track' => 'newjoiner',
        'name' => 'Growth Alignment - New Joiner Retest',
        'amount' => 995,
        'setting' => 'stripe.retest_price_newjoiner',
    ],
    [
        'kind' => 'retest',
        'track' => 'manager',
        'name' => 'Growth Alignment - Manager Retest',
        'amount' => 2995,
        'setting' => 'stripe.retest_price_manager',
    ],
    [
        'kind' => 'retest',
        'track' => 'executive',
        'name' => 'Growth Alignment - Executive Retest',
        'amount' => 4995,
        'setting' => 'stripe.retest_price_executive',
    ],
];

/** @return \Stripe\Product|null */
function findProduct(\Stripe\StripeClient $stripe, string $kind, string $track): ?\Stripe\Product
{
    $products = $stripe->products->all(['active' => true, 'limit' => 100]);
    foreach ($products->data as $product) {
        $metadata = $product->metadata ?? null;
        if (!$metadata) {
            continue;
        }

        if (($metadata['app'] ?? '') === 'growth_alignment'
            && ($metadata['version'] ?? '') === 'v4'
            && ($metadata['kind'] ?? '') === $kind
            && ($metadata['track'] ?? '') === $track) {
            return $product;
        }
    }

    return null;
}

/** @return \Stripe\Price|null */
function findPrice(\Stripe\StripeClient $stripe, string $productId, int $amount): ?\Stripe\Price
{
    $prices = $stripe->prices->all([
        'product' => $productId,
        'active' => true,
        'limit' => 100,
    ]);

    foreach ($prices->data as $price) {
        if (strtolower((string) $price->currency) === 'usd'
            && (int) $price->unit_amount === $amount) {
            return $price;
        }
    }

    return null;
}

echo "Growth Alignment V4 LIVE Stripe configuration\n";
echo "=============================================\n";

foreach ($catalog as $item) {
    $product = findProduct($stripe, $item['kind'], $item['track']);

    if (!$product) {
        $product = $stripe->products->create([
            'name' => $item['name'],
            'metadata' => [
                'app' => 'growth_alignment',
                'version' => 'v4',
                'kind' => $item['kind'],
                'track' => $item['track'],
            ],
        ]);
        $productState = 'CREATED';
    } else {
        $productState = 'REUSED';
    }

    $price = findPrice($stripe, $product->id, $item['amount']);
    if (!$price) {
        $price = $stripe->prices->create([
            'product' => $product->id,
            'currency' => 'usd',
            'unit_amount' => $item['amount'],
            'metadata' => [
                'app' => 'growth_alignment',
                'version' => 'v4',
                'kind' => $item['kind'],
                'track' => $item['track'],
            ],
        ]);
        $priceState = 'CREATED';
    } else {
        $priceState = 'REUSED';
    }

    $settings->set($item['setting'], $price->id, false);

    printf(
        "%s | USD %0.2f | product=%s (%s) | price=%s (%s) | %s=SAVED\n",
        $item['name'],
        $item['amount'] / 100,
        $product->id,
        $productState,
        $price->id,
        $priceState,
        $item['setting']
    );
}

echo "=============================================\n";
echo "DONE: V4 LIVE Stripe products/prices are mapped.\n";
