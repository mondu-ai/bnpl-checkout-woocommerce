<?php

if ( ! class_exists( 'MonduOrdersController' ) ) {
    class MonduOrdersController extends WP_REST_Controller
    {
        public function register_routes()
        {
            $namespace = 'mondu/v1/orders';

            register_rest_route(
                $namespace,
                '/confirm',
                [
                    [
                        'methods'             => 'GET',
                        'callback'            => [$this, 'confirm'],
                        'permission_callback' => '__return_true',
                    ],
                ]
            );
            register_rest_route(
                $namespace,
                '/decline',
                [
                    [
                        'methods'             => 'GET',
                        'callback'            => [$this, 'decline'],
                        'permission_callback' => '__return_true',
                    ],
                ]
            );
        }

        public function confirm(WP_REST_Request $request)
        {
            $params         = $request->get_params();
            $order_number   = $params['external_reference_id'];
            $mondu_order_id = $params['order_uuid'];
            $return_url     = urldecode($params['return_url']);
            $order          = get_order_from_order_number_or_uuid($order_number);

            try {
                if (!$order) {
                    throw new \Exception(__('Order not found'));
                }

                if (isset(WC()->cart)) {
                    WC()->cart->empty_cart();
                }

                if (in_array($order->get_status(), ['pending', 'failed'])) {
                    $order->update_status('wc-on-hold', __('On hold', 'woocommerce'));
                }

                Mondu_WC()->mondu_request_wrapper->confirm_order( $order->get_id(), $mondu_order_id, $order_number );
            } catch (\Exception $e) {
                Mondu_WC()->log(
                    [
                        'error_confirming_order' => $params,
                    ]
                );
            }

            wp_safe_redirect($return_url);
            exit;
        }

        public function decline(WP_REST_Request $request)
        {
            $params       = $request->get_params();
            $order_number = $params['external_reference_id'];
            $return_url   = urldecode($params['return_url']);
            $order        = get_order_from_order_number($order_number);

            $order->add_order_note(esc_html(sprintf(__('Order was declined by Mondu.', 'mondu'))), false);

            if ($order->get_status() == 'pending') {
                $order->update_status('wc-failed', __('Failed', 'woocommerce'));
            }

            wp_safe_redirect($return_url);
            exit;
        }
    }
}
