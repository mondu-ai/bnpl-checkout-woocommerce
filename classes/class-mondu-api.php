<?php

use Mondu\Exceptions\MonduException;
use Mondu\Exceptions\ResponseException;

if ( ! defined('ABSPATH' ) ) {
	exit;
}

class Api {
	public function register() {
		register_setting( 'mondu', OPTION_NAME );
	}

    /**
     * Create order
     *
     * @param array $params Order parameters.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
	public function create_order( array $params ) {
        $result = $this->post( '/orders', $params );
        return json_decode( $result['body'], true );
	}

    /**
     * Get Order
     *
     * @param mixed $mondu_uuid Mondu UUID.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
	public function get_order( $mondu_uuid ) {
        $result = $this->get( sprintf( '/orders/%s', $mondu_uuid ) );
        return json_decode( $result['body'], true );
	}

    /**
     * Adjust Order
     *
     * @param mixed $mondu_uuid Mondu UUID.
     * @param array $params Order parameters.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function adjust_order( $mondu_uuid, array $params ) {
        $result = $this->post( sprintf( '/orders/%s/adjust', $mondu_uuid ), $params );
        return json_decode( $result['body'], true );
    }

    /**
     * Cancel Order
     *
     * @param mixed $mondu_uuid Mondu UUID.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function cancel_order( $mondu_uuid ) {
        $result = $this->post( sprintf( '/orders/%s/cancel', $mondu_uuid ) );
        return json_decode( $result['body'], true );
    }

    /**
     * Ship Order
     *
     * @param mixed $mondu_uuid Mondu UUID.
     * @param array $params Order parameters.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function ship_order( $mondu_uuid, array $params ) {
        $result = $this->post( sprintf( '/orders/%s/invoices', $mondu_uuid ), $params );
        return json_decode( $result['body'], true );
    }

    /**
     * Confirm order
     *
     * @param mixed $mondu_uuid Mondu UUID.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function confirm_order( $mondu_uuid ) {
        $result = $this->post( sprintf( '/orders/%s/confirm', $mondu_uuid ) );
        return json_decode( $result['body'], true );
    }

    /**
     * Get Invoices
     *
     * @param mixed $mondu_uuid Mondu UUID.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function get_invoices( $mondu_uuid ) {
        $result = $this->get( sprintf( '/orders/%s/invoices', $mondu_uuid ) );
        return json_decode( $result['body'], true );
    }

    /**
     * Get Invoice
     *
     * @param mixed $mondu_order_uuid Mondu Order UUID.
     * @param mixed $mondu_invoice_uuid Mondu Invoice UUID.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function get_invoice( $mondu_order_uuid, $mondu_invoice_uuid ) {
        $result = $this->get( sprintf( '/orders/%s/invoices/%s', $mondu_order_uuid, $mondu_invoice_uuid ) );
        return json_decode( $result['body'], true );
    }

    /**
     * Webhook Secret
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function webhook_secret() {
        $result = $this->get( '/webhooks/keys' );
        return json_decode( $result['body'], true );
    }

    /**
     * Get Webhooks
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function get_webhooks() {
        $result = $this->get( '/webhooks' );
        return json_decode( $result['body'], true );
    }

    /**
     * Register Webhook
     *
     * @param array $params Webhook parameters.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function register_webhook( $params ) {
        $result = $this->post( '/webhooks', $params );
        return json_decode( $result['body'], true );
    }

    /**
     * Cancel Invoice
     *
     * @param mixed $mondu_uuid Mondu UUID.
     * @param mixed $mondu_invoice_uuid Mondu Invoice UUID.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function cancel_invoice( $mondu_uuid, $mondu_invoice_uuid ) {
        $result = $this->post( sprintf( '/orders/%s/invoices/%s/cancel', $mondu_uuid, $mondu_invoice_uuid ) );
        return json_decode( $result['body'], true );
    }

    /**
     * Create Credit note
     *
     * @param mixed $mondu_invoice_uuid Mondu Invoice UUID.
     * @param array $credit_note Credit note parameters.
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function create_credit_note( $mondu_invoice_uuid, array $credit_note ) {
        $result = $this->post( sprintf( '/invoices/%s/credit_notes', $mondu_invoice_uuid ), $credit_note );
        return json_decode( $result['body'], true );
    }

    /**
     * Get Payment Methods
     *
     * @return mixed
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function get_payment_methods() {
        $result = $this->get( '/payment_methods' );
        return json_decode( $result['body'], true );
    }

    /**
     * Log Plugin Event
     *
     * @param array $params Plugin event parameters.
     *
     * @return void
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    public function log_plugin_event( array $params ) {
        $this->post( '/plugin/events', $params );
    }

    /**
     * Post Request
     *
     * @param mixed      $path Path.
     * @param array|null $body Body.
     *
     * @return array
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    private function post( $path, array $body = null ) {
        $method = 'POST';
        return $this->request( $path, $method, $body );
    }

    /**
     * Put Request
     *
     * @param mixed      $path Path.
     * @param array|null $body Body.
     *
     * @return array
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    private function put( $path, array $body = null ) {
        $method = 'PUT';
        return $this->request( $path, $method, $body );
    }

    /**
     * Patch Request
     *
     * @param mixed      $path Path.
     * @param array|null $body Body.
     *
     * @return array
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    private function patch( $path, array $body = null ) {
        $method = 'PATCH';
        return $this->request( $path, $method, $body );
    }

    /**
     * Get Request
     *
     * @param mixed $path Path.
     * @param mixed $parameters Parameters.
     *
     * @return array
     *
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
    private function get( $path, $parameters = null ) {
        if ( null !== $parameters ) {
            $path .= '&' . http_build_query( $parameters );
        }

        $method = 'GET';
        return $this->request( $path, $method );
    }

    /**
     * Validate Result
     *
     * @param mixed $url URL.
     * @param mixed $result Result.
     *
     * @return array
     * @throws MonduException Mondu Exception.
     * @throws ResponseException Response Exception.
     */
	private function validate_remote_result( $url, $result ) {
		if ( $result instanceof \WP_Error ) {
			throw new MonduException( $result->get_error_message(), $result->get_error_code() );
		} else {
			Mondu_WC()->log(
				array(
					'code'     => isset( $result['response']['code'] ) ? $result['response']['code'] : null,
					'url'      => $url,
					'response' => isset( $result['body'] ) ? $result['body'] : null,
				)
			);
		}

		if ( ! is_array( $result ) || ! isset( $result['response'], $result['body'] ) || ! isset( $result['response']['code'], $result['response']['message'] ) ) {
			throw new MonduException( __( 'Unexpected API response format.', 'mondu' ) );
		}
		if ( strpos( $result['response']['code'], '2' ) !== 0 ) {
			$message = $result['response']['message'];
			if ( isset( $result['body']['errors'], $result['body']['errors']['title'] ) ) {
				$message = $result['body']['errors']['title'];
			}

			throw new ResponseException( $message, $result['response']['code'], json_decode( $result['body'], true ) );
		}

		return $result;
	}

	/**
	 * Send Request
	 *
	 * @param $path
	 * @param $method
	 * @param $body
	 * @return array
	 * @throws MonduException
	 * @throws ResponseException
	 */
	private function request( $path, $method = 'GET', $body = null ) {
		$url  = is_production() ? MONDU_PRODUCTION_URL : MONDU_SANDBOX_URL;
		$url .= $path;

		$headers = array(
			'Content-Type'     => 'application/json',
			'Api-Token'        => Mondu_WC()->global_settings['api_token'],
			'X-Plugin-Name'    => 'woocommerce',
			'X-Plugin-Version' => MONDU_PLUGIN_VERSION,
		);

		$args = array(
			'headers' => $headers,
			'method'  => $method,
			'timeout' => 30,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		Mondu_WC()->log(
			array(
				'method' => $method,
				'url'    => $url,
				'body'   => isset( $args['body'] ) ? $args['body'] : null,
			)
		);

		return $this->validate_remote_result( $url, wp_remote_request( $url, $args ) );
	}
}
