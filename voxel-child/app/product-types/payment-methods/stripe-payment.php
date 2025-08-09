<?php

namespace Voxel\Product_Types\Payment_Methods;

if ( ! defined('ABSPATH') ) {
    exit;
}

class Stripe_Payment extends Base_Payment_Method {
    use Stripe_Payment\Order_Actions;

    public function get_type(): string {
        return 'stripe_payment';
    }

    public function get_label(): string {
        return _x( 'Stripe payment', 'payment methods', 'voxel' );
    }

    public function process_payment() {
        try {
            $customer = $this->order->get_customer();
            $stripe_customer = $customer->get_or_create_stripe_customer();
            $billing_address_collection = \Voxel\get( 'product_settings.stripe_payments.billing_address_collection', 'auto' );
            $tax_id_collection = !! \Voxel\get( 'product_settings.stripe_payments.tax_id_collection.enabled', true );

            $tax_collection_method = null;
            if ( \Voxel\get( 'product_settings.tax_collection.enabled' ) ) {
                $tax_collection_method = \Voxel\get( 'product_settings.tax_collection.collection_method', 'stripe_tax' );
            }

            $args = [
                'client_reference_id' => sprintf( 'order:%d', $this->order->get_id() ),
                'customer' => $stripe_customer->id,
                'mode' => 'payment',
                'currency' => $this->order->get_currency(),
                'customer_update' => [
                    'address' => 'auto',
                    'name' => 'auto',
                    'shipping' => 'auto',
                ],
                'locale' => 'auto',
                'line_items' => array_map( function( $line_item ) use ( $tax_collection_method ) {
                    $order_item = $line_item['order_item'];
                    $data = [
                        'quantity' => $line_item['quantity'],
                        'price_data' => [
                            'currency' => $line_item['currency'],
                            'unit_amount_decimal' => $line_item['amount_in_cents'],
                            'product_data' => [
                                'name' => $line_item['product']['label'],
                            ],
                        ],
                    ];

                    if ( ! empty( $line_item['product']['description'] ) ) {
                        $data['price_data']['product_data']['description'] = $line_item['product']['description'];
                    }

                    if ( ! empty( $line_item['product']['thumbnail_url'] ) ) {
                        $data['price_data']['product_data']['images'] = [ $line_item['product']['thumbnail_url'] ];
                    }

                    if ( $tax_collection_method === 'stripe_tax' ) {
                        $tax_behavior = \Voxel\get( sprintf(
                            'product_settings.tax_collection.stripe_tax.product_types.%s.tax_behavior',
                            $order_item->get_product_type_key()
                        ), 'default' );

                        if ( in_array( $tax_behavior, [ 'inclusive', 'exclusive' ], true ) ) {
                            $data['price_data']['tax_behavior'] = $tax_behavior;
                        }

                        $tax_code = \Voxel\get( sprintf(
                            'product_settings.tax_collection.stripe_tax.product_types.%s.tax_code',
                            $order_item->get_product_type_key()
                        ) );

                        if ( ! empty( $tax_code ) ) {
                            $data['price_data']['product_data']['tax_code'] = $tax_code;
                        }
                    } elseif ( $tax_collection_method === 'tax_rates' ) {
                        $tax_calculation_method = \Voxel\get( sprintf(
                            'product_settings.tax_collection.tax_rates.product_types.%s.calculation_method',
                            $order_item->get_product_type_key()
                        ), 'fixed' );

                        if ( $tax_calculation_method === 'fixed' ) {
                            $tax_rates = \Voxel\get( sprintf(
                                'product_settings.tax_collection.tax_rates.product_types.%s.fixed_rates.%s',
                                $order_item->get_product_type_key(),
                                \Voxel\Stripe::is_test_mode() ? 'test_mode' : 'live_mode'
                            ), [] );

                            if ( ! empty( $tax_rates ) ) {
                                $data['tax_rates'] = $tax_rates;
                            }
                        } elseif ( $tax_calculation_method === 'dynamic' ) {
                            $dynamic_tax_rates = \Voxel\get( sprintf(
                                'product_settings.tax_collection.tax_rates.product_types.%s.dynamic_rates.%s',
                                $order_item->get_product_type_key(),
                                \Voxel\Stripe::is_test_mode() ? 'test_mode' : 'live_mode'
                            ), [] );

                            if ( ! empty( $dynamic_tax_rates ) ) {
                                $data['dynamic_tax_rates'] = $dynamic_tax_rates;
                            }
                        }
                    }

                    return $data;
                }, $this->get_line_items() ),
                'payment_intent_data' => [
                    'capture_method' => $this->get_capture_method() === 'automatic' ? 'automatic_async' : 'manual',
                    'metadata' => [
                        'voxel:payment_for' => 'order',
                        'voxel:order_id' => $this->order->get_id(),
                    ],
                ],
                'success_url' => $this->get_success_url(),
                'cancel_url' => $this->get_cancel_url(),
                'submit_type' => $this->get_submit_type(),
                'metadata' => [
                    'voxel:payment_for' => 'order',
                    'voxel:order_id' => $this->order->get_id(),
                ],
                'billing_address_collection' => $billing_address_collection === 'required' ? 'required' : 'auto',
                'tax_id_collection' => [
                    'enabled' => $tax_id_collection,
                ],

                // 'shipping_options' => null, // @todo
                // 'allow_promotion_codes' => null,
            ];

            if ( $tax_collection_method === 'stripe_tax' ) {
                $args['automatic_tax'] = [
                    'enabled' => true,
                ];
            }

            if ( \Voxel\get( 'product_settings.stripe_payments.phone_number_collection.enabled' ) ) {
                $args['phone_number_collection'] = [
                    'enabled' => true,
                ];
            }

            if ( \Voxel\get( 'product_settings.shipping.enabled' ) ) {
                $shipping_product_types = array_keys( (array) \Voxel\get( 'product_settings.shipping.product_types', [] ) );
                $shipping_allowed_countries = (array) \Voxel\get( 'product_settings.shipping.allowed_countries', [] );
                if ( ! empty( $shipping_allowed_countries ) ) {
                    foreach ( $this->order->get_items() as $order_item ) {
                        if ( in_array( $order_item->get_product_type_key(), $shipping_product_types, true ) ) {
                            $args['shipping_address_collection'] = [
                                'allowed_countries' => $shipping_allowed_countries,
                            ];

                            break;
                        }
                    }
                }
            }

            $vendor = $this->order->get_vendor();
            if ( $vendor !== null && $vendor->is_active_vendor() ) {
                if ( \Voxel\get('product_settings.multivendor.integration_method') === 'destination_charges' ) {
                    $args['payment_intent_data']['application_fee_amount'] = $this->get_application_fee_amount();
                    $args['payment_intent_data']['transfer_data'] = [
                        'destination' => $vendor->get_stripe_vendor_id(),
                    ];

                    if ( \Voxel\get('product_settings.multivendor.settlement_merchant') === 'vendor' ) {
                        $args['payment_intent_data']['on_behalf_of'] = $vendor->get_stripe_vendor_id();

                        if ( $tax_collection_method === 'stripe_tax' ) {
                            $args['automatic_tax'] = [
                                'enabled' => true,
                                'liability' => [
                                    'type' => 'self',
                                ],
                            ];
                        }
                    }

                    $this->order->set_details( 'multivendor.mode', 'destination_charges' );
                    $this->order->set_details( 'multivendor.vendor_fees', $vendor->get_vendor_fees() );
                }
            }

            //implement the custom discount for membership or non-membership
            $data = $this->order->get_details();
            $user_id = custom_get_current_user_id(); 

            if (isset($data['cart']['items']) && is_array($data['cart']['items'])) {
                $items = $data['cart']['items'];
                $first_item = reset($items); // Get the first item in the array
                
                if (isset($first_item['product']['post_id'])) {
                    $product_id = $first_item['product']['post_id'];
                    $productDetails = fetch_product_details_by_id($product_id);
                    $adult_quantity = isset($first_item['addons']['adult_total_price']['quantity']) ? $first_item['addons']['adult_total_price']['quantity'] : 0;
                    $child_quantity = isset($first_item['addons']['child_total_price']['quantity']) ? $first_item['addons']['child_total_price']['quantity'] : 0;

                    $member = isset($productDetails['membership_level']) ? $productDetails['membership_level'] : 'default';

                    $calculate_rates = calculate_rates($productDetails, $adult_quantity, $child_quantity, $member);
					$everything_costa_rica_adult_rate = $calculate_rates['everything_costa_rica_adult_rate'] ?? 0;
					$everything_costa_rica_child_rate = $calculate_rates['everything_costa_rica_child_rate'] ?? 0;
					$totalCostaRicaRate = $calculate_rates['totalCostaRicaRate'] ?? 0;
					$deposit_amount = $calculate_rates['deposit_amount']?? 0;
					$due_on_arrival = $calculate_rates['due_on_arrival'] ?? 0;
                }
            }

            $deposit_amount = number_format((float)$deposit_amount, 2, '.', '');
            $due_on_arrival = number_format((float)$due_on_arrival, 2, '.', '');
			 
			$args['line_items'][0]['price_data']['unit_amount_decimal'] = $deposit_amount * 100;
            
            $session = \Voxel\Vendor\Stripe\Checkout\Session::create( $args );

            if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $session->currency ) ) {
                $total_order_amount /= 100;
            }

            $total_order_amount = $due_on_arrival;
            if ( $total_order_amount === 0 ) {
                $this->order->set_details( 'checkout.is_zero_amount', true );
            }

            $this->order->set_details( 'pricing.total', $total_order_amount);
            $this->order->set_details( 'checkout.session_id', $session->id );
            $this->order->set_details( 'checkout.capture_method', $this->get_capture_method() );
			$this->order->set_details( 'booking.amount', $deposit_amount ); // custom made
			$this->order->set_details( 'remaining.amount', $due_on_arrival); // custom made

			update_post_meta($this->order->get_id(), 'custom_discount', get_user_plan());
            update_post_meta($this->order->get_id(), 'total_costa_rica_rate', $totalCostaRicaRate );
			update_post_meta($this->order->get_id(), 'custom_booking_amount', $deposit_amount );
			update_post_meta($this->order->get_id(), 'custom_remaining_amount', $due_on_arrival);
           
            $this->order->save();

            return wp_send_json( [
                'success' => true,
                'redirect_url' => $session->url,
            ] );
        } catch ( \Voxel\Vendor\Stripe\Exception\ApiErrorException | \Voxel\Vendor\Stripe\Exception\InvalidArgumentException $e ) {
            return wp_send_json( [
                'success' => false,
                'message' => _x( 'Something went wrong', 'checkout', 'voxel' ),
                'debug' => [
                    'type' => 'stripe_error',
                    'code' => method_exists( $e, 'getStripeCode' ) ? $e->getStripeCode() : $e->getCode(),
                    'message' => $e->getMessage(),
                ],
            ] );
        }
    }

    protected function get_success_url() {
        return add_query_arg( [
            'vx' => 1,
            'action' => 'stripe_payments.checkout.success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
            'order_id' => $this->order->get_id(),
        ], home_url('/') );
    }

    protected function get_cancel_url() {
        $redirect_url = wp_get_referer() ?: home_url('/');
        $redirect_url = add_query_arg( 't', time(), $redirect_url );

        return add_query_arg( [
            'vx' => 1,
            'action' => 'stripe_payments.checkout.cancel',
            'session_id' => '{CHECKOUT_SESSION_ID}',
            'order_id' => $this->order->get_id(),
            'redirect_to' => rawurlencode( $redirect_url ),
        ], home_url('/') );
    }

    protected function get_submit_type(): string {
        foreach ( $this->order->get_items() as $item ) {
            if ( $item->get_type() === 'booking' ) {
                return 'book';
            }
        }

        return 'auto';
    }

    public function get_capture_method(): string {
        $approval = \Voxel\get( 'product_settings.stripe_payments.order_approval' );
        if ( $approval === 'manual' ) {
            return 'manual';
        } elseif ( $approval === 'deferred' ) {
            return 'deferred';
        } else {
            return 'automatic';
        }
    }

    public function is_zero_amount(): bool {
        return !! $this->order->get_details( 'checkout.is_zero_amount' );
    }

    public function payment_intent_updated(
        \Voxel\Vendor\Stripe\PaymentIntent $payment_intent,
        \Voxel\Vendor\Stripe\Checkout\Session $session = null
    ) {
        if ( $this->order->get_details( 'checkout.capture_method' ) === 'deferred' ) {
            if ( $payment_intent->status === 'requires_capture' ) {
                $cart_is_valid = false;
                try {
                    $cart = $this->order->get_cart();
                    $cart_is_valid = true;
                } catch ( \Exception $e ) {
                    \Voxel\log($e->getMessage(), $e->getCode());
                }

                if ( $cart_is_valid ) {
                    $payment_intent = $payment_intent->capture();
                } else {
                    $payment_intent = $payment_intent->cancel();
                }
            }
        }

        $order_status = $this->determine_order_status_from_payment_intent( $payment_intent );
        if ( $order_status !== null ) {
            $this->order->set_status( $order_status );
        }

        $this->order->set_details( 'payment_intent', [
            'id' => $payment_intent->id,
            'amount' => $payment_intent->amount,
            'currency' => $payment_intent->currency,
            'customer' => $payment_intent->customer,
            'status' => $payment_intent->status,
            'canceled_at' => $payment_intent->canceled_at,
            'cancellation_reason' => $payment_intent->cancellation_reason,
            'created' => $payment_intent->created,
            'livemode' => $payment_intent->livemode,
            'capture_method' => $payment_intent->capture_method,
            'application_fee_amount' => $payment_intent->application_fee_amount,
            'transfer_data' => [
                'destination' => $payment_intent->transfer_data->destination ?? null,
            ],
            'transfer_group' => $payment_intent->transfer_group,
            'shipping' => [
                'carrier' => $payment_intent->shipping->carrier ?? null,
                'phone' => $payment_intent->shipping->phone ?? null,
                'tracking_number' => $payment_intent->shipping->tracking_number ?? null,
            ],
        ] );

        $total_order_amount = $payment_intent->amount;
        if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $payment_intent->currency ) ) {
            $total_order_amount /= 100;
        }

        $this->order->set_details( 'pricing.total', $total_order_amount );
        $this->order->set_transaction_id( $payment_intent->id );
        $this->order->set_details( 'checkout.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );

        if ( $session ) {
            $this->order->set_details( 'checkout.session_details', $this->_get_checkout_session_details_for_storage( $session ) );

            $tax_amount = $this->_get_tax_amount_from_checkout_session( $session );
            $discount_amount = $this->_get_discount_amount_from_checkout_session( $session );
            $shipping_amount = $this->_get_shipping_amount_from_checkout_session( $session );

            if ( $tax_amount !== null ) {
                $this->order->set_details( 'pricing.tax', $tax_amount );
            }

            if ( $discount_amount !== null ) {
                $this->order->set_details( 'pricing.discount', $discount_amount );
            }

            if ( $shipping_amount !== null ) {
                $this->order->set_details( 'pricing.shipping', $shipping );
            }
        }

        $this->order->save();
    }

    protected function _get_checkout_session_details_for_storage( \Voxel\Vendor\Stripe\Checkout\Session $session ) {
        return [
            'customer_details' => [
                'address' => [
                    'city' => $session->customer_details->address->city ?? null,
                    'country' => $session->customer_details->address->country ?? null,
                    'line1' => $session->customer_details->address->line1 ?? null,
                    'line2' => $session->customer_details->address->line2 ?? null,
                    'postal_code' => $session->customer_details->address->postal_code ?? null,
                    'state' => $session->customer_details->address->state ?? null,
                ],
                'email' => $session->customer_details->email ?? null,
                'name' => $session->customer_details->name ?? null,
                'phone' => $session->customer_details->phone ?? null,
            ],
            'shipping_details' => [
                'address' => [
                    'city' => $session->shipping_details->address->city ?? null,
                    'country' => $session->shipping_details->address->country ?? null,
                    'line1' => $session->shipping_details->address->line1 ?? null,
                    'line2' => $session->shipping_details->address->line2 ?? null,
                    'postal_code' => $session->shipping_details->address->postal_code ?? null,
                    'state' => $session->shipping_details->address->state ?? null,
                ],
                'name' => $session->shipping_details->name ?? null,
            ],
        ];
    }

    protected function _get_tax_amount_from_checkout_session( \Voxel\Vendor\Stripe\Checkout\Session $session ) {
        $tax_amount = $session->total_details->amount_tax;
        if ( ! is_numeric( $tax_amount ) ) {
            return null;
        }

        if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $session->currency ) ) {
            $tax_amount /= 100;
        }

        if ( $tax_amount === 0 ) {
            return null;
        }

        return $tax_amount;
    }

    protected function _get_discount_amount_from_checkout_session( \Voxel\Vendor\Stripe\Checkout\Session $session ) {
        $discount_amount = $session->total_details->amount_discount;
        if ( ! is_numeric( $discount_amount ) ) {
            return null;
        }

        if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $session->currency ) ) {
            $discount_amount /= 100;
        }

        if ( $discount_amount === 0 ) {
            return null;
        }

        return $discount_amount;
    }

    protected function _get_shipping_amount_from_checkout_session( \Voxel\Vendor\Stripe\Checkout\Session $session ) {
        $shipping_amount = $session->total_details->amount_shipping;
        if ( ! is_numeric( $shipping_amount ) ) {
            return null;
        }

        if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $session->currency ) ) {
            $shipping_amount /= 100;
        }

        if ( $shipping_amount === 0 ) {
            return null;
        }

        return $shipping_amount;
    }

    protected function determine_order_status_from_payment_intent( \Voxel\Vendor\Stripe\PaymentIntent $payment_intent ): ?string {
        if ( in_array( $payment_intent->status, [ 'requires_payment_method', 'requires_confirmation', 'requires_action', 'processing' ], true ) ) {
            return \Voxel\ORDER_PENDING_PAYMENT;
        } elseif ( $payment_intent->status === 'canceled' ) {
            return \Voxel\ORDER_CANCELED;
        } elseif ( $payment_intent->status === 'requires_capture' ) {
            return \Voxel\ORDER_PENDING_APPROVAL;
        } elseif ( $payment_intent->status === 'succeeded' ) {
            $stripe = \Voxel\Stripe::getClient();
            $latest_charge = $stripe->charges->retrieve( $payment_intent->latest_charge, [] );

            // handle refunds
            if ( $latest_charge ) {
                if ( $latest_charge->refunded ) {
                    // full refund
                    return \Voxel\ORDER_REFUNDED;
                } elseif ( $latest_charge->amount_refunded > 0 ) {
                    // partial refund
                    return \Voxel\ORDER_REFUNDED;
                }
            }

            return \Voxel\ORDER_COMPLETED;
        } else {
            return null;
        }
    }

    public function zero_amount_checkout_session_updated( \Voxel\Vendor\Stripe\Checkout\Session $session ) {
        $this->order->set_details( 'checkout.session_details', $this->_get_checkout_session_details_for_storage( $session ) );

        if ( $session->payment_status === 'paid' ) {
            $capture_method = $this->order->get_details( 'checkout.capture_method' );
            if ( $capture_method === 'deferred' ) {
                $cart_is_valid = false;
                try {
                    $cart = $this->order->get_cart();
                    $cart_is_valid = true;
                } catch ( \Exception $e ) {}

                if ( $cart_is_valid ) {
                    $status = \Voxel\ORDER_COMPLETED;
                } else {
                    $status = \Voxel\ORDER_CANCELED;
                }
            } elseif ( $capture_method === 'manual' ) {
                $status = \Voxel\ORDER_PENDING_APPROVAL;
            } else {
                $status = \Voxel\ORDER_COMPLETED;
            }

            $this->order->set_status( $status );
            $this->order->set_details( 'checkout.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
            $this->order->save();
        } else {
            $this->order->set_status( \Voxel\ORDER_CANCELED );
            $this->order->set_details( 'checkout.last_synced_at', \Voxel\utc()->format( 'Y-m-d H:i:s' ) );
            $this->order->save();
        }
    }

    public function should_sync(): bool {
        return ! $this->order->get_details( 'checkout.last_synced_at' );
    }

    public function sync(): void {
        $stripe = \Voxel\Stripe::getClient();
        if ( $this->is_zero_amount() ) {
            $session = $stripe->checkout->sessions->retrieve( $this->order->get_details( 'checkout.session_id' ) );
            $this->zero_amount_checkout_session_updated( $session );
        } else {
            if ( $transaction_id = $this->order->get_transaction_id() ) {
                $payment_intent = $stripe->paymentIntents->retrieve( $transaction_id );
                $this->payment_intent_updated( $payment_intent );
            } elseif ( $checkout_session_id = $this->order->get_details( 'checkout.session_id' ) ) {
                $session = $stripe->checkout->sessions->retrieve( $checkout_session_id, [
                    'expand' => [ 'payment_intent' ],
                ] );

                $payment_intent = $session->payment_intent;
                if ( $payment_intent !== null ) {
                    $this->payment_intent_updated( $payment_intent, $session );
                }
            } else {
                //
            }
        }
    }

    public function get_customer_details(): array {
        $details = [];
        $data = (array) $this->order->get_details( 'checkout.session_details.customer_details', [] );

        if ( ! empty( $data['name'] ) ) {
            $details[] = [
                'label' => _x( 'Customer name', 'order customer details', 'voxel' ),
                'content' => $data['name'],
            ];
        }

        if ( ! empty( $data['email'] ) ) {
            $details[] = [
                'label' => _x( 'Email', 'order customer details', 'voxel' ),
                'content' => $data['email'],
            ];
        }

        if ( ! empty( $data['address']['country'] ) ) {
            $country_code = $data['address']['country'];
            $country = \Voxel\Data\Country_List::all()[ strtoupper( $country_code ) ] ?? null;

            $details[] = [
                'label' => _x( 'Country', 'order customer details', 'voxel' ),
                'content' => $country['name'] ?? $country_code,
            ];
        }

        if ( ! empty( $data['address']['line1'] ) ) {
            $details[] = [
                'label' => _x( 'Address line 1', 'order customer details', 'voxel' ),
                'content' => $data['address']['line1'],
            ];
        }

        if ( ! empty( $data['address']['line2'] ) ) {
            $details[] = [
                'label' => _x( 'Address line 2', 'order customer details', 'voxel' ),
                'content' => $data['address']['line2'],
            ];
        }

        if ( ! empty( $data['address']['city'] ) ) {
            $details[] = [
                'label' => _x( 'City', 'order customer details', 'voxel' ),
                'content' => $data['address']['city'],
            ];
        }

        if ( ! empty( $data['address']['postal_code'] ) ) {
            $details[] = [
                'label' => _x( 'Postal code', 'order customer details', 'voxel' ),
                'content' => $data['address']['postal_code'],
            ];
        }

        if ( ! empty( $data['address']['state'] ) ) {
            $details[] = [
                'label' => _x( 'State', 'order customer details', 'voxel' ),
                'content' => $data['address']['state'],
            ];
        }

        if ( ! empty( $data['phone'] ) ) {
            $details[] = [
                'label' => _x( 'Phone number', 'order customer details', 'voxel' ),
                'content' => $data['phone'],
            ];
        }

        return $details;
    }

    public function get_shipping_details(): array {
        $details = [];
        $data = (array) $this->order->get_details( 'checkout.session_details.shipping_details', [] );

        if ( ! empty( $data['name'] ) ) {
            $details[] = [
                'label' => _x( 'Recipient name', 'order shipping details', 'voxel' ),
                'content' => $data['name'],
            ];
        }

        if ( ! empty( $data['address']['country'] ) ) {
            $country_code = $data['address']['country'];
            $country = \Voxel\Data\Country_List::all()[ strtoupper( $country_code ) ] ?? null;

            $details[] = [
                'label' => _x( 'Country', 'order shipping details', 'voxel' ),
                'content' => $country['name'] ?? $country_code,
            ];
        }

        if ( ! empty( $data['address']['line1'] ) ) {
            $details[] = [
                'label' => _x( 'Address line 1', 'order shipping details', 'voxel' ),
                'content' => $data['address']['line1'],
            ];
        }

        if ( ! empty( $data['address']['line2'] ) ) {
            $details[] = [
                'label' => _x( 'Address line 2', 'order shipping details', 'voxel' ),
                'content' => $data['address']['line2'],
            ];
        }

        if ( ! empty( $data['address']['city'] ) ) {
            $details[] = [
                'label' => _x( 'City', 'order shipping details', 'voxel' ),
                'content' => $data['address']['city'],
            ];
        }

        if ( ! empty( $data['address']['postal_code'] ) ) {
            $details[] = [
                'label' => _x( 'Postal code', 'order shipping details', 'voxel' ),
                'content' => $data['address']['postal_code'],
            ];
        }

        if ( ! empty( $data['address']['state'] ) ) {
            $details[] = [
                'label' => _x( 'State', 'order shipping details', 'voxel' ),
                'content' => $data['address']['state'],
            ];
        }

        return $details;
    }

    protected function get_application_fee_amount() {
        $currency = $this->order->get_currency();
        $subtotal_in_cents = $this->order->get_subtotal();
        if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $currency ) ) {
            $subtotal_in_cents *= 100;
        }

        $application_fee_amount = 0;
        foreach ( $this->order->get_vendor()->get_vendor_fees() as $fee ) {
            if ( $fee['type'] === 'fixed' ) {
                $fee_amount_in_cents = $fee['fixed_amount'];
                if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $currency ) ) {
                    $fee_amount_in_cents *= 100;
                }

                $application_fee_amount += $fee_amount_in_cents;
            } elseif ( $fee['type'] === 'percentage' ) {
                $pct = $fee['percentage_amount'];
                $application_fee_amount += ( $subtotal_in_cents * ( $pct / 100 ) );
            }
        }

        return round( $application_fee_amount );
    }

    public function get_vendor_fees_summary(): array {
        if ( $this->order->get_details('multivendor.mode') === 'destination_charges' ) {
            $currency = $this->order->get_currency();
            $application_fee_amount = $this->order->get_details( 'payment_intent.application_fee_amount' );
            if ( ! is_numeric( $application_fee_amount ) ) {
                return [];
            }

            if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $currency ) ) {
                $application_fee_amount /= 100;
            }

            $details = [
                'total' => $application_fee_amount,
                'breakdown' => [],
            ];

            foreach ( (array) $this->order->get_details('multivendor.vendor_fees', []) as $fee ) {
                if ( ( $fee['type'] ?? null ) === 'fixed' ) {
                    if ( ! is_numeric( $fee['fixed_amount'] ?? null ) && $fee['fixed_amount'] > 0 ) {
                        continue;
                    }

                    $details['breakdown'][] = [
                        'label' => $fee['label'] ?? 'Platform fee',
                        'content' => \Voxel\currency_format( $fee['fixed_amount'], $currency, false ),
                    ];
                } elseif ( ( $fee['type'] ?? null ) === 'percentage' ) {
                    if ( ! is_numeric( $fee['percentage_amount'] ?? null ) && $fee['percentage_amount'] > 0 && $fee['percentage_amount'] <= 100 ) {
                        continue;
                    }

                    $details['breakdown'][] = [
                        'label' => $fee['label'] ?? 'Platform fee',
                        'content' => round( $fee['percentage_amount'], 2 ).'%',
                    ];
                }
            }

            return $details;
        } else {
            return [];
        }
    }
}
