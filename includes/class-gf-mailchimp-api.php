<?php

/**
 * Gravity Forms MailChimp API Library.
 *
 * @since     4.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GF_MailChimp_API {

	/**
	 * MailChimp account API key.
	 *
	 * @since  4.0
	 * @access protected
	 * @var    string $api_key MailChimp account API key.
	 */
	protected $api_key;

	/**
	 * MailChimp account data center.
	 *
	 * @since  4.0
	 * @access protected
	 * @var    string $data_center MailChimp account data center.
	 */
	protected $data_center;

	/**
	 * Initialize API library.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $api_key (default: '') MailChimp API key.
	 *
	 * @uses GF_MailChimp_API::set_data_center()
	 */
	public function __construct( $api_key = '' ) {

		// Assign API key to object.
		$this->api_key = $api_key;

		// Set data center.
		$this->set_data_center();

	}

	/**
	 * Get current account details.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function account_details() {

		return $this->process_request();

	}

	/**
	 * Get all interests for an interest category.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $list_id     MailChimp list ID.
	 * @param string $category_id Interest category ID.
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function get_interest_category_interests( $list_id, $category_id ) {

		return $this->process_request( 'lists/' . $list_id . '/interest-categories/' . $category_id . '/interests', array( 'count' => 9999 ), 'GET', 'interests' );

	}

	/**
	 * Get a specific MailChimp list.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $list_id MailChimp list ID.
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function get_list( $list_id ) {

		return $this->process_request( 'lists/' . $list_id );

	}

	/**
	 * Get all MailChimp lists.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param array $params List request parameters.
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function get_lists( $params ) {

		return $this->process_request( 'lists', $params );

	}

	/**
	 * Get all interest categories for a MailChimp list.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $list_id MailChimp list ID.
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function get_list_interest_categories( $list_id ) {

		return $this->process_request( 'lists/' . $list_id . '/interest-categories', array( 'count' => 9999 ), 'GET', 'categories' );

	}

	/**
	 * Get a specific MailChimp list member.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $list_id       MailChimp list ID.
	 * @param string $email_address Email address.
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function get_list_member( $list_id, $email_address ) {

		// Prepare subscriber hash.
		$subscriber_hash = md5( strtolower( $email_address ) );

		return $this->process_request( 'lists/' . $list_id . '/members/' . $subscriber_hash );

	}

	/**
	 * Get all merge fields for a MailChimp list.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $list_id MailChimp list ID.
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function get_list_merge_fields( $list_id ) {

		return $this->process_request( 'lists/' . $list_id . '/merge-fields', array( 'count' => 9999 ) );

	}

	/**
	 * Add or update a MailChimp list member.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $list_id       MailChimp list ID.
	 * @param string $email_address Email address.
	 * @param array  $subscription  Subscription details.
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function update_list_member( $list_id, $email_address, $subscription ) {

		// Prepare subscriber hash.
		$subscriber_hash = md5( strtolower( $email_address ) );

		return $this->process_request( 'lists/' . $list_id . '/members/' . $subscriber_hash, $subscription, 'PUT' );

	}

	/**
	 * Add a note to the MailChimp list member.
	 *
	 * @since  4.0.10
	 * @access public
	 *
	 * @param string $list_id       MailChimp list ID.
	 * @param string $email_address Email address.
	 * @param string $note          The note to be added to the member.
	 *
	 * @uses GF_MailChimp_API::process_request()
	 *
	 * @return array
	 */
	public function add_member_note( $list_id, $email_address, $note ) {

		// Prepare subscriber hash.
		$subscriber_hash = md5( strtolower( $email_address ) );

		return $this->process_request( 'lists/' . $list_id . '/members/' . $subscriber_hash . '/notes', array( 'note' => $note ), 'POST' );

	}

	/**
	 * Process MailChimp API request.
	 *
	 * @since  4.0
	 * @access private
	 *
	 * @param string $path       Request path.
	 * @param array  $data       Request data.
	 * @param string $method     Request method. Defaults to GET.
	 * @param string $return_key Array key from response to return. Defaults to null (return full response).
	 *
	 * @throws Exception if API request returns an error, exception is thrown.
	 *
	 * @return array
	 */
	private function process_request( $path = '', $data = array(), $method = 'GET', $return_key = null ) {

		// If API key is not set, throw exception.
		if ( rgblank( $this->api_key ) ) {
			throw new Exception( 'API key must be defined to process an API request.' );
		}

		// Build base request URL.
		$request_url = 'https://' . $this->data_center . '.api.mailchimp.com/3.0/' . $path;

		// Add request URL parameters if needed.
		if ( 'GET' === $method && ! empty( $data ) ) {
			$request_url = add_query_arg( $data, $request_url );
		}

		// Build base request arguments.
		$args = array(
			'method'   => $method,
			'headers'  => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( ':' . $this->api_key ),
				'Content-Type'  => 'application/json',
			),
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'timeout'   => apply_filters( 'http_request_timeout', 30 ),
		);

		// Add data to arguments if needed.
		if ( 'GET' !== $method ) {
			$args['body'] = json_encode( $data );
		}

		// Filter request arguments.
		$args = apply_filters( 'gform_mailchimp_request_args', $args, $path );

		// Get request response.
		$response = wp_remote_request( $request_url, $args );

		// If request was not successful, throw exception.
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		// Decode response body.
		$response['body'] = json_decode( $response['body'], true );

		// If status code is set, throw exception.
		if ( isset( $response['body']['status'] ) && isset( $response['body']['title'] ) ) {

			// Initialize exception.
			$exception = new GF_MailChimp_Exception( $response['body']['title'], $response['body']['status'] );

			// Add detail.
			$exception->setDetail( $response['body']['detail'] );

			// Add errors if available.
			if ( isset( $response['body']['errors'] ) ) {
				$exception->setErrors( $response['body']['errors'] );
			}

			throw $exception;

		}

		// Remove links from response.
		unset( $response['body']['_links'] );

		// If a return key is defined and array item exists, return it.
		if ( ! empty( $return_key ) && isset( $response['body'][ $return_key ] ) ) {
			return $response['body'][ $return_key ];
		}

		return $response['body'];

	}

	/**
	 * Set data center based on API key.
	 *
	 * @since  4.0
	 * @access private
	 */
	private function set_data_center() {

		// If API key is empty, return.
		if ( empty( $this->api_key ) ) {
			return;
		}

		// Explode API key.
		$exploded_key = explode( '-', $this->api_key );

		// Set data center from API key.
		$this->data_center = isset( $exploded_key[1] ) ? $exploded_key[1] : 'us1';

	}

}

/**
 * Gravity Forms MailChimp Exception.
 *
 * @since     4.0.3
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GF_MailChimp_Exception extends Exception {

	/**
	 * Additional details about the exception.
	 *
	 * @since  4.0.3
	 * @access protected
	 * @var    string $detail Additional details about the exception.
	 */
	protected $detail;

	/**
	 * Exception error messages.
	 *
	 * @since  4.0.3
	 * @access protected
	 * @var    array $errors Exception error messages.
	 */
	protected $errors;

	/**
	 * Get additional details about the exception.
	 *
	 * @since  4.0.3
	 * @access public
	 *
	 * @return string|null
	 */
	public function getDetail() {

		return $this->detail;

	}

	/**
	 * Get exception error messages.
	 *
	 * @since  4.0.3
	 * @access public
	 *
	 * @return array|null
	 */
	public function getErrors() {

		return $this->errors;

	}

	/**
	 * Set exception details.
	 *
	 * @since  4.0.3
	 * @access public
	 *
	 * @param string $detail Additional details about the exception.
	 */
	public function setDetail( $detail ) {

		$this->detail = $detail;

	}

	/**
	 * Set exception error messages.
	 *
	 * @since  4.0.3
	 * @access public
	 *
	 * @param string $detail Additional error messages about the exception.
	 */
	public function setErrors( $errors ) {

		$this->errors = $errors;

	}

}
