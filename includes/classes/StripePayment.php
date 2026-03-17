<?php
namespace MediaWiki\Extension\JsonForms;

if (is_readable(__DIR__ . "/../../vendor/autoload.php")) {
	include_once __DIR__ . "/../../vendor/autoload.php";
}

class StripePayment
{
	protected \Stripe\StripeClient $stripeClient;

	public function __construct($secretKey)
	{
		$this->stripeClient = new \Stripe\StripeClient($secretKey);
	}

	/**
	 * @param array $data
	 * @param array $metadata
	 * @return object
	 */
	public function createCustomer(array $data, array $metadata = [])
	{
		$customers = $this->stripeClient->customers->all( [
			'email' => $data['email'],
			'limit' => 1
		] );

		if ( !empty($customers->data)) {
			$customer = $customers->data[0];

			if ( !empty( $data['payment_method'] ) ) {    
				$this->stripeClient->paymentMethods->attach(
					$data['payment_method'],
					['customer' => $customer->id ]
				);
    
    			$this->stripeClient->customers->update(
				    $customer->id,
				    [
				        'invoice_settings' => [
			            'default_payment_method' => $data['payment_method']
			        ]
    			] );

			}
			
			return $customer;
		}

		/*
        $customerParams = [
        // Basic info
        'email' => 'jane@example.com',          // Customer email
        'name' => 'Jane Doe',                   // Full name
        'phone' => '+1234567890',               // Optional phone
        'description' => 'Premium user',        // Optional description
        
        // Address (optional)
        'address' => [
        'line1' => '123 Main St',
        'line2' => 'Apt 4B',
        'city' => 'New York',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => 'US'
        ],
        
        // Payment & invoices
        'invoice_prefix' => 'INV123',           // Prefix for invoices
        'invoice_settings' => [                 // Invoice default settings
        'custom_fields' => [
            [
                'name' => 'Membership Level',
                'value' => 'Gold'
            ]
        ],
        'footer' => 'Thanks for your purchase!',
        'default_payment_method' => 'pm_1Mx...'
        ],
        
        // Payment methods
        'payment_method' => 'pm_1Mx...',        // Optional: attach a PM at creation
        'tax_exempt' => 'none',                 // none, exempt, reverse
        'preferred_locales' => ['en-US'],       // optional array of locales
        
        // Shipping (physical products)
        'shipping' => [
        'name' => 'Jane Doe',
        'address' => [
            'line1' => '123 Main St',
            'line2' => '',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US'
        ]
        ],
        
        // Metadata
        'metadata' => [
        'internal_user_id' => 42,
        'signup_source' => 'website'
        ],
        
        // Optional fields for subscription defaults
        'balance' => 0,                        // in cents, positive or negative
        'currency' => 'usd',                    // for customer balance
        'default_source' => 'card_xxx',        // legacy: attach card
        ];
        */

		$data['metadata'] = $metadata;
		if (!empty($data['payment_method'])) {
        	$data['invoice_settings'] = [
				'default_payment_method' => $data['payment_method']
			];
		}

		return $this->stripeClient->customers->create($data);
	}

	/**
	 * @param string $name
	 * @param array $metadata
	 * @return object
	 */
	public function createProduct(string $name, array $metadata = [])
	{
		/*
        $productParams = [
        // Basic
        'name' => 'Premium Plan',       // Required: Name of the product
        'id' => 'premium_plan_001',     // Optional: your own identifier
        'active' => true,               // Optional: whether product is active
        'description' => 'Monthly subscription plan',  // Optional text
        'images' => ['https://example.com/image.png'], // Optional: array of image URLs
        'shippable' => false,           // Optional: true if product requires shipping
        'statement_descriptor' => 'PremiumPlan', // Optional: appears on customer statement
        'unit_label' => 'unit',         // Optional: label for quantity (like "piece")
        
        // Tax and pricing
        'tax_code' => 'txcd_99999999',  // Optional: Stripe tax code
        'default_price_data' => [       // Optional: create default price along with product
        'unit_amount' => 2000,      // amount in cents
        'currency' => 'usd',
        'recurring' => [
            'interval' => 'month',  // day, week, month, year
            'interval_count' => 1
        ],
        'tax_behavior' => 'inclusive', // inclusive, exclusive, unspecified
        ],
        
        // Metadata (custom key-value pairs)
        'metadata' => [
        'internal_id' => 42,
        'category' => 'subscription'
        ],
        
        // Additional optional fields
        'attributes' => ['size', 'color'], // Optional array of attribute names
        'caption' => 'Best choice for professionals', // Optional caption
        'package_dimensions' => [         // Optional: for shippable products
        'height' => 10,
        'length' => 20,
        'weight' => 500, // grams
        'width' => 5
        ],
        'url' => 'https://example.com/product/premium', // Optional URL
        'deactivate_on' => [],             // Optional: array of SKUs to deactivate
        ];
        */
		return $this->stripeClient->products->create([
			"name" => $name,
			"metadata" => $metadata,
		]);
	}

	/**
	 * Create a product with a price
	 *
	 * @param string $name Product name
	 * @param int $unitAmount Amount in cents (e.g., 2000 = $20)
	 * @param string $currency Currency code (e.g., 'usd')
	 * @param array $metadata Optional metadata
	 * @param array|null $recurring Optional recurring info for subscriptions ['interval'=>'month','interval_count'=>1]
	 * @return array ['product' => $product, 'price' => $price]
	 */
	public function createProductWithPrice(
		string $name,
		int $unitAmount,
		string $currency,
		array $metadata = [],
		array $recurring = null,
	): array {
		// Create the product
		$product = $this->stripe->products->create([
			"name" => $name,
			"metadata" => $metadata,
		]);

		// Prepare price parameters
		$priceParams = [
			"product" => $product->id,
			"unit_amount" => $unitAmount,
			"currency" => $currency,
		];

		if ($recurring !== null) {
			$priceParams["recurring"] = $recurring;
		}

		// Create the price
		$price = $this->stripe->prices->create($priceParams);

		return ["product" => $product, "price" => $price];
	}

	/**
	 * @param string $productId
	 * @param int $amount
	 * @param string $currency
	 * @return object
	 */
	public function createPrice(
		string $productId,
		int $amount,
		string $currency,
	) {
		/*
            $priceParams = [
            // Required
            'product' => $productId,        // ID of the product this price belongs to
            'unit_amount' => 2000,          // Amount in cents (e.g., 2000 = $20.00)
            'currency' => 'usd',            // Currency code (3-letter)
            
            // Optional: recurring prices (subscriptions)
            'recurring' => [
            'interval' => 'month',       // day, week, month, year
            'interval_count' => 1,       // e.g., every 2 months
            'trial_period_days' => 7     // optional trial period
            ],
            
            // Optional: metadata
            'metadata' => [
            'internal_id' => 42,
            'plan_type' => 'premium'
            ],
            
            // Optional: display and tax
            'nickname' => 'Premium Monthly',  // internal name
            'tax_behavior' => 'inclusive',    // inclusive, exclusive, unspecified
            'tiers_mode' => null,             // volume, graduated, or null
            'billing_scheme' => 'per_unit',   // per_unit, tiered
            
            // Optional: tiered pricing
            'tiers' => [                       // only if tiers_mode is set
            ['up_to' => 10, 'unit_amount' => 1000],
            ['up_to' => 20, 'unit_amount' => 900],
            ['up_to' => 'inf', 'unit_amount' => 800]
            ],
            
            // Optional: lookup key for client-side checkout
            'lookup_key' => 'premium_monthly',
            
            // Optional: set a product by ID or create a new product inline
            // 'product_data' => [
            //     'name' => 'Premium Plan',
            //     'metadata' => ['internal_id' => 42]
            // ]
            ];
            */
		return $this->stripeClient->prices->create([
			"product" => $productId,
			"unit_amount" => $amount,
			"currency" => $currency,
		]);
	}

	/**
	 * Create a PaymentIntent for a product using its default price
	 *
	 * @param string $productId Stripe Product ID
	 * @param string|null $customerId Stripe Customer ID (optional)
	 * @param string|null $paymentMethodId Stripe PaymentMethod ID (optional)
	 * @param bool $confirm Whether to confirm the payment immediately
	 * @return \Stripe\PaymentIntent
	 * @throws Exception if no active price found
	 */
	public function createPaymentIntentForProduct(
		string $productId,
		string $customerId = null,
		string $paymentMethodId = null,
		bool $confirm = true,
	): \Stripe\PaymentIntent {
		// Fetch the first active price for the product
		$prices = $this->stripeClient->prices->all([
			"product" => $productId,
			"active" => true,
			"limit" => 1,
		]);

		if (empty($prices->data)) {
			throw new \Exception(
				"No active price found for product $productId",
			);
		}

		$price = $prices->data[0];

		// Build base PaymentIntent parameters
		$params = [
			"amount" => $price->unit_amount,
			"currency" => $price->currency,
			"metadata" => [
				"product_id" => $productId,
				"price_id" => $price->id,
			],
			//  Prevent redirect-based errors
			"automatic_payment_methods" => [
				"enabled" => true,
				"allow_redirects" => "never",
			],
		];

		// Attach optional customer
		if ($customerId) {
			$params["customer"] = $customerId;
		}

		// Attach payment method if provided
		if ($paymentMethodId) {
			$params["payment_method"] = $paymentMethodId;
			$params["setup_future_usage"] = "off_session";
			$params["confirm"] = $confirm; // safe to confirm now
		} else {
			$params["confirm"] = false; // safe if no payment method
		}

		// Create and return the PaymentIntent
		return $this->stripeClient->paymentIntents->create($params);
	}

	/**
	 * @param int $amount
	 * @param string $currency
	 * @param string $paymentMethodId
	 * @param string $customerId null
	 * @param array $metadata []
	 * @return object
	 */
	public function createPaymentIntent(
		int $amount,
		string $currency,
		string $paymentMethodId,
		string $customerId = null,
		array $metadata = [],
	) {
		/*
            $paymentIntentParams = [
            // Required
            'amount' => 2000,                  // Amount in cents (e.g., 2000 = $20.00)
            'currency' => 'usd',               // Currency code (3-letter)
            
            // Optional: link to a customer
            'customer' => $customerId,         // Stripe Customer ID
            
            // Optional: attach a payment method
            'payment_method' => $paymentMethodId,  // Stripe PaymentMethod ID
            'payment_method_types' => ['card'],    // List of allowed types
            
            // Optional: confirm immediately
            'confirm' => true,                 // If true, attempts to confirm the payment
            
            // Optional: save card for future off-session use
            'setup_future_usage' => 'off_session', // off_session or on_session
            
            // Optional: for one-time use without saving
            'capture_method' => 'automatic',   // automatic or manual
            
            // Optional: metadata for internal tracking
            'metadata' => [
            'user_id' => 42,
            'order_id' => 123
            ],
            
            // Optional: receipt & description
            'description' => 'Order #1234',
            'statement_descriptor' => 'My Store',
            
            // Optional: shipping information
            'shipping' => [
            'name' => 'John Doe',
            'address' => [
            'line1' => '123 Main St',
            'line2' => 'Apt 4B',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US'
            ]
            ],
            
            // Optional: payment options for advanced flows
            'transfer_data' => [                // Connect platforms
            'destination' => 'acct_123',
            'amount' => 1800
            ],
            'on_behalf_of' => 'acct_123',      // If collecting on behalf of another account
            'application_fee_amount' => 200,   // Fee in cents if using Stripe Connect
            
            // Optional: setup for recurring or subscription payments
            'setup_future_usage' => 'off_session',  // to save card for future
            
            // Optional: capture details
            'capture_method' => 'automatic',   // or 'manual' if you want delayed capture
            ];
            
            */

		$params = [
			"amount" => $amount,
			"currency" => $currency,
			"payment_method" => $paymentMethodId,
			"confirm" => true,
			"metadata" => $metadata,
		];

		if ($customerId) {
			$params["customer"] = $customerId;
		}

		return $this->stripeClient->paymentIntents->create($params);
	}

	/**
	 * @param \Stripe\PaymentIntent $pi
	 * @return array Status info and what frontend should do
	 */
	public function handlePaymentIntentResult(\Stripe\PaymentIntent $pi): array
	{
		$result = [
			"id" => $pi->id,
			"status" => $pi->status,
			"amount" => $pi->amount,
			"currency" => $pi->currency,
			"client_secret" => $pi->client_secret,
			"requires_action" => false,
			"error" => null,
		];

		$result["succeeded"] = $pi->status === "succeeded";

		// Payment requires additional action (e.g., 3D Secure)
		if (
			$pi->status === "requires_action" ||
			$pi->status === "requires_payment_method"
		) {
			$result["requires_action"] = true;
			$result["message"] = "Payment requires additional action";
			$result["next_action"] = $pi->next_action ?? null;
			$result["error"] = $pi->last_payment_error ?? null;
		}

		// Other statuses
		else {
			$result["message"] = "Payment not completed yet";
		}

		return $result;
	}

	/**
	 * @param string $paymentMethodId
	 * @param string $customerId
	 * @return object
	 */
	public function attachPaymentMethod(
		string $paymentMethodId,
		string $customerId,
	) {
		/*
            $attachParams = [
            // Required
            'customer' => $customerId,      // The Stripe Customer ID to attach to
            
            // Optional
            'invoice_settings' => [          // Only relevant for invoices
            'default_payment_method' => $paymentMethodId
            ],
            
            'expand' => ['customer'],        // Optional: expand related objects in the response
            'metadata' => [                  // Optional: store internal info
            'internal_user_id' => 42,
            'purpose' => 'subscription'
            ]
            ];
            
            */

		return $this->stripeClient->paymentMethods->attach($paymentMethodId, [
			"customer" => $customerId,
		]);
	}

	/**
	 * @param string $payload
	 * @param string $signature
	 * @param string $endpointSecret
	 * @return object
	 */
	public function verifyWebhook($payload, $signature, $endpointSecret)
	{
		return \Stripe\Webhook::constructEvent(
			$payload,
			$signature,
			$endpointSecret,
		);
	}
}

