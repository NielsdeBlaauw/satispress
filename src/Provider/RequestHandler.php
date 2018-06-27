<?php
/**
 * SatisPress request handler.
 *
 * @package SatisPress
 * @license GPL-2.0-or-later
 * @since 0.3.0
 */

declare ( strict_types = 1 );

namespace SatisPress\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;
use SatisPress\Route\Route;
use WP_REST_Server;

/**
 * Request handler class.
 *
 * @since 0.3.0
 */
class RequestHandler extends AbstractHookProvider {
	/**
	 * Register hooks.
	 *
	 * @since 0.3.0
	 */
	public function register_hooks() {
		add_action( 'parse_request', [ $this, 'dispatch' ] );
	}

	/**
	 * Dispatch the request to an endpoint.
	 *
	 * @since 0.3.0
	 *
	 * @param WP $wp Main WP instance.
	 */
	public function dispatch( $wp ) {
		if ( empty( $wp->query_vars['satispress_route'] ) ) {
			return;
		}

		$route   = $wp->query_vars['satispress_route'];
		$request = $this->plugin->get_container()->get( 'http.request' );
		$request->set_route( $route );

		if ( ! empty( $wp->query_vars['satispress_params'] ) ) {
			$request->set_url_params( $wp->query_vars['satispress_params'] );
		}

		if ( $this->check_authentication() ) {
			$controller = $this->get_route_controller( $route );
			$controller->handle_request( $request );
		}
		exit;
	}

	/**
	 * Check authentication.
	 *
	 * Calls the WP_REST_Server authentication method to leverage authentication
	 * handlers built for the REST API.
	 *
	 * @since 0.3.0
	 *
	 * @see WP_REST_Server::check_authentication()
	 *
	 * @return boolean
	 */
	protected function check_authentication() {
		$server = new WP_REST_Server();
		$result = $server->check_authentication();

		if ( ! is_wp_error( $result ) ) {
			return true;
		}

		$response = rest_ensure_response( $result );

		if ( is_wp_error( $response ) ) {
			$response = $this->error_to_response( $response );
		} elseif ( $response->is_error() ) {
			$response = $this->error_to_response( $response->as_error() );
		}

		$server->send_headers( $response->get_headers() );
		status_header( $response->get_status() );

		echo wp_json_encode( $response->get_data() );
		exit;
	}

	/**
	 * Retrieve the controller for a route.
	 *
	 * @since 0.3.0
	 *
	 * @param string $route Route.
	 * @return Route
	 */
	protected function get_route_controller( $route ): Route {
		return $this->plugin->get_container()->get( 'route.' . $route );
	}
}
