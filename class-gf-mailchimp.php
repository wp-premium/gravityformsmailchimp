<?php

GFForms::include_feed_addon_framework();

class GFMailChimp extends GFFeedAddOn {

	protected $_version = GF_MAILCHIMP_VERSION;
	protected $_min_gravityforms_version = '1.9.3';
	protected $_slug = 'gravityformsmailchimp';
	protected $_path = 'gravityformsmailchimp/mailchimp.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms MailChimp Add-On';
	protected $_short_title = 'MailChimp';
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Members plugin integration
	 */
	protected $_capabilities = array( 'gravityforms_mailchimp', 'gravityforms_mailchimp_uninstall' );

	/**
	 * Permissions
	 */
	protected $_capabilities_settings_page = 'gravityforms_mailchimp';
	protected $_capabilities_form_settings = 'gravityforms_mailchimp';
	protected $_capabilities_uninstall = 'gravityforms_mailchimp_uninstall';

	/**
	 * @var string $merge_var_name The MailChimp list field tag name; used by gform_mailchimp_field_value.
	 */
	protected $merge_var_name = '';

	private static $api;
	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFMailChimp
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFMailChimp();
		}

		return self::$_instance;
	}

	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed, subscribe the user to the list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return void
	 */
	public function process_feed( $feed, $entry, $form ) {
		$this->log_debug( __METHOD__ . '(): Processing feed.' );

		// login to MailChimp
		$api = $this->get_api();
		if ( ! is_object( $api ) ) {
			$this->log_error( __METHOD__ . '(): Failed to set up the API.' );

			return;
		}

		$feed_meta = $feed['meta'];

		// retrieve name => value pairs for all fields mapped in the 'mappedFields' field map
		$field_map            = $this->get_field_map_fields( $feed, 'mappedFields' );
		$this->merge_var_name = 'EMAIL';
		$email                = $this->get_field_value( $form, $entry, $field_map['EMAIL'] );

		// abort if email is invalid
		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			$this->log_error( __METHOD__ . "(): A valid Email address must be provided." );

			return;
		}

		$override_empty_fields = gf_apply_filters( 'gform_mailchimp_override_empty_fields', $form['id'], true, $form, $entry, $feed );
		if ( ! $override_empty_fields ) {
			$this->log_debug( __METHOD__ . '(): Empty fields will not be overridden.' );
		}

		$merge_vars = array();
		foreach ( $field_map as $name => $field_id ) {

			if ( $name == 'EMAIL' ) {
				continue;
			}

			$this->merge_var_name = $name;
			$field_value          = $this->get_field_value( $form, $entry, $field_id );

			if ( empty( $field_value ) && ! $override_empty_fields ) {
				continue;
			} else {
				$merge_vars[ $name ] = $field_value;
			}

		}

		$mc_groupings = $this->get_mailchimp_groups( $feed_meta['mailchimpList'] );

		if ( ! empty ( $mc_groupings ) ) {
			$groupings = array();

			foreach ( $mc_groupings as $grouping ) {

				if ( ! is_array( $grouping['groups'] ) ) {
					continue;
				}

				$groups = array();

				foreach ( $grouping['groups'] as $group ) {

					$this->log_debug( __METHOD__ . '(): Evaluating condition for group: ' . rgar( $group, 'name' ) );
					$group_settings = $this->get_group_settings( $group, $grouping['id'], $feed );
					if ( ! $this->is_group_condition_met( $group_settings, $form, $entry ) ) {
						continue;
					}

					$groups[] = $group['name'];
				}

				if ( ! empty( $groups ) ) {
					$groupings[] = array(
						'name'   => $grouping['name'],
						'groups' => $groups,
					);

				}
			}

			if ( ! empty( $groupings ) ) {
				$merge_vars['GROUPINGS'] = $groupings;
			}
		}

		$this->log_debug( __METHOD__ . "(): Checking to see if $email is already on the list." );
		$list_id = $feed_meta['mailchimpList'];
		try {
			$params      = array(
				'id'     => $list_id,
				'emails' => array(
					array( 'email' => $email ),
				)
			);
			$member_info = $api->call( 'lists/member-info', $params );
		} catch ( Exception $e ) {
			$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );
		}

		if ( empty( $member_info ) ) {
			$this->log_error( __METHOD__ . '(): There was an error while trying to retrieve member information. Unable to process feed.' );

			return;
		}

		$subscribe_or_update = false;
		$member_not_found    = absint( rgar( $member_info, 'error_count' ) ) > 0;
		$member_status       = rgars( $member_info, 'data/0/status' );

		if ( $member_not_found || $member_status != 'subscribed' ) {
			$allow_resubscription = gf_apply_filters( 'gform_mailchimp_allow_resubscription', $form['id'], true, $form, $entry, $feed );
			if ( $member_status == 'unsubscribed' && ! $allow_resubscription ) {
				$this->log_debug( __METHOD__ . '(): User is unsubscribed and resubscription is not allowed.' );

				return;
			}

			// adding member to list
			// $member_status != 'subscribed', 'pending', 'cleaned' need to be 're-subscribed' to send out confirmation email
			$this->log_debug( __METHOD__ . "(): {$email} is either not on the list or on the list but the status is not subscribed - status: " . $member_status . '; adding to list.' );
			$transaction = 'Subscribe';

			try {
				$params = array(
					'id'                => $list_id,
					'email'             => array( 'email' => $email ),
					'merge_vars'        => $merge_vars,
					'email_type'        => 'html',
					'double_optin'      => $feed_meta['double_optin'] == true,
					'update_existing'   => false,
					'replace_interests' => true,
					'send_welcome'      => $feed_meta['sendWelcomeEmail'] == true,
				);
				$params = gf_apply_filters( 'gform_mailchimp_args_pre_subscribe', $form['id'], $params, $form, $entry, $feed, $transaction );

				$this->log_debug( __METHOD__ . '(): Calling - subscribe, Parameters ' . print_r( $params, true ) );
				$subscribe_or_update = $api->call( 'lists/subscribe', $params );
			} catch ( Exception $e ) {
				$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );
			}
		} else {
			// updating member
			$this->log_debug( __METHOD__ . "(): {$email} is already on the list; updating info." );

			$keep_existing_groups = gf_apply_filters( 'gform_mailchimp_keep_existing_groups', $form['id'], true, $form, $entry, $feed );
			$transaction          = 'Update';

			/**
			 * Switching to setting the replace_interests parameter instead of using append_groups()
			 * MailChimp now handles merging the new group selections with any existing groups at their end
			 */

//			// retrieve existing groups for subscribers
//			$current_groups = rgar( $member_info['data'][0]['merges'], 'GROUPINGS' );
//			if ( is_array( $current_groups ) && $keep_existing_groups ) {
//				// add existing groups to selected groups from form so that existing groups are maintained for that subscriber
//				$merge_vars = $this->append_groups( $merge_vars, $current_groups );
//			}

			try {
				$params = array(
					'id'                => $list_id,
					'email'             => array( 'email' => $email ),
					'merge_vars'        => $merge_vars,
					'email_type'        => 'html',
					'replace_interests' => ! $keep_existing_groups,
				);
				$params = gf_apply_filters( 'gform_mailchimp_args_pre_subscribe', $form['id'], $params, $form, $entry, $feed, $transaction );

				$this->log_debug( __METHOD__ . '(): Calling - update-member, Parameters ' . print_r( $params, true ) );
				$subscribe_or_update = $api->call( 'lists/update-member', $params );
			} catch ( Exception $e ) {
				$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );
			}
		}

		if ( rgar( $subscribe_or_update, 'email' ) ) {
			//email will be returned if successful
			$this->log_debug( __METHOD__ . "(): {$transaction} successful." );
		} else {
			$this->log_error( __METHOD__ . "(): {$transaction} failed." );
		}
	}

	/**
	 * Returns the value of the selected field.
	 *
	 * @param array $form The form object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 * @param string $field_id The ID of the field being processed.
	 *
	 * @return array
	 */
	public function get_field_value( $form, $entry, $field_id ) {
		$field_value = '';

		switch ( strtolower( $field_id ) ) {

			case 'form_title':
				$field_value = rgar( $form, 'title' );
				break;

			case 'date_created':
				$date_created = rgar( $entry, strtolower( $field_id ) );
				if ( empty( $date_created ) ) {
					//the date created may not yet be populated if this function is called during the validation phase and the entry is not yet created
					$field_value = gmdate( 'Y-m-d H:i:s' );
				} else {
					$field_value = $date_created;
				}
				break;

			case 'ip':
			case 'source_url':
				$field_value = rgar( $entry, strtolower( $field_id ) );
				break;

			default:

				$field = GFFormsModel::get_field( $form, $field_id );

				if ( is_object( $field ) ) {

					$is_integer = $field_id == intval( $field_id );
					$input_type = RGFormsModel::get_input_type( $field );

					if ( $is_integer && $input_type == 'address' ) {

						$field_value = $this->get_full_address( $entry, $field_id );

					} elseif ( $is_integer && $input_type == 'name' ) {

						$field_value = $this->get_full_name( $entry, $field_id );

					} elseif ( $is_integer && $input_type == 'checkbox' ) {

						$selected = array();
						foreach ( $field->inputs as $input ) {
							$index = (string) $input['id'];
							if ( ! rgempty( $index, $entry ) ) {
								$selected[] = $this->maybe_override_field_value( rgar( $entry, $index ), $form, $entry, $index );
							}
						}
						$field_value = implode( ', ', $selected );

					} elseif ( $input_type == 'phone' && $field->phoneFormat == 'standard' ) {

						// reformat standard format phone to match MailChimp format
						// format: NPA-NXX-LINE (404-555-1212) when US/CAN
						$field_value = rgar( $entry, $field_id );
						if ( ! empty( $field_value ) && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches ) ) {
							$field_value = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
						}

					} else {

						if ( is_callable( array( 'GF_Field', 'get_value_export' ) ) ) {
							$field_value = $field->get_value_export( $entry, $field_id );
						} else {
							$field_value = rgar( $entry, $field_id );
						}

					}

				} else {

					$field_value = rgar( $entry, $field_id );

				}

		}

		return $this->maybe_override_field_value( $field_value, $form, $entry, $field_id );
	}

	/**
	 * Use the legacy gform_mailchimp_field_value filter instead of the framework gform_SLUG_field_value filter.
	 *
	 * @param string $field_value The field value.
	 * @param array $form The form object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 * @param string $field_id The ID of the field being processed.
	 *
	 * @return string
	 */
	public function maybe_override_field_value( $field_value, $form, $entry, $field_id ) {

		return gf_apply_filters( 'gform_mailchimp_field_value', array(
			$form['id'],
			$field_id
		), $field_value, $form['id'], $field_id, $entry, $this->merge_var_name );
	}


	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe user to MailChimp only when payment is received.', 'gravityformsmailchimp' )
			)
		);

	}

	/**
	 * Clear the cached settings on uninstall.
	 *
	 * @return bool
	 */
	public function uninstall() {
		parent::uninstall();
		GFCache::delete( 'mailchimp_plugin_settings' );

		return true;
	}

	// ------- Plugin settings -------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => '',
				'description' => '<p>' . sprintf(
						esc_html__( 'MailChimp makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to collect customer information and automatically add them to your MailChimp subscriber list. If you don\'t have a MailChimp account, you can %1$s sign up for one here.%2$s', 'gravityformsmailchimp' ),
						'<a href="http://www.mailchimp.com/" target="_blank">', '</a>'
					) . '</p>',
				'fields'      => array(
					array(
						'name'              => 'apiKey',
						'label'             => esc_html__( 'MailChimp API Key', 'gravityformsmailchimp' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_api_key' )
					),
				)
			),
		);
	}

	/**
	 * Ensure the group setting keys are not lost when the settings are saved. Clear the cached settings if no feeds exist so the new format keys will be used.
	 *
	 * @return array The post data containing the updated settings.
	 */
	public function get_posted_settings() {
		$post_data = parent::get_posted_settings();

		if ( $this->is_plugin_settings( $this->_slug ) && $this->is_save_postback() && ! empty( $post_data ) ) {
			$feed_count = $this->count_feeds();

			if ( $feed_count > 0 ) {
				$settings           = $this->get_previous_settings();
				$settings['apiKey'] = rgar( $post_data, 'apiKey' );

				return $settings;
			} else {
				GFCache::delete( 'mailchimp_plugin_settings' );
			}

		}

		return $post_data;
	}

	/**
	 * Count how many MailChimp feeds exist.
	 *
	 * @return int
	 */
	public function count_feeds() {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT count(*) FROM {$wpdb->prefix}gf_addon_feed WHERE addon_slug=%s", $this->_slug );

		return $wpdb->get_var( $sql );
	}

	// ------- Feed list page -------

	/**
	 * Prevent feeds being listed or created if the api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		$settings = $this->get_plugin_settings();

		return $this->is_valid_api_key( rgar( $settings, 'apiKey' ) );
	}

	/**
	 * If the api key is invalid or empty return the appropriate message.
	 *
	 * @return string
	 */
	public function configure_addon_message() {

		$settings_label = sprintf( esc_html__( '%s Settings', 'gravityforms' ), $this->get_short_title() );
		$settings_link  = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

		$settings = $this->get_plugin_settings();

		if ( rgempty( 'apiKey', $settings ) ) {

			return sprintf( esc_html__( 'To get started, please configure your %s.', 'gravityforms' ), $settings_link );
		}

		return sprintf( esc_html__( 'We are unable to login to MailChimp with the provided API key. Please make sure you have entered a valid API key on the %s page.', 'gravityformsmailchimp' ), $settings_link );

	}

	/**
	 * Display a warning message instead of the feeds if the API key isn't valid.
	 *
	 * @param array $form The form currently being edited.
	 * @param integer $feed_id The current feed ID.
	 */
	public function feed_edit_page( $form, $feed_id ) {

		if ( ! $this->can_create_feed() ) {

			echo '<h3><span>' . $this->feed_settings_title() . '</span></h3>';
			echo '<div>' . $this->configure_addon_message() . '</div>';

			return;
		}

		parent::feed_edit_page( $form, $feed_id );
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'            => esc_html__( 'Name', 'gravityformsmailchimp' ),
			'mailchimp_list_name' => esc_html__( 'MailChimp List', 'gravityformsmailchimp' )
		);
	}

	/**
	 * Returns the value to be displayed in the MailChimp List column.
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_mailchimp_list_name( $feed ) {
		return $this->get_list_name( $feed['meta']['mailchimpList'] );
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @return array The feed settings.
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'MailChimp Feed Settings', 'gravityformsmailchimp' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gravityformsmailchimp' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => '<h6>' . esc_html__( 'Name', 'gravityformsmailchimp' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsmailchimp' )
					),
					array(
						'name'     => 'mailchimpList',
						'label'    => esc_html__( 'MailChimp List', 'gravityformsmailchimp' ),
						'type'     => 'mailchimp_list',
						'required' => true,
						'tooltip'  => '<h6>' . esc_html__( 'MailChimp List', 'gravityformsmailchimp' ) . '</h6>' . esc_html__( 'Select the MailChimp list you would like to add your contacts to.', 'gravityformsmailchimp' ),
					),
				)
			),
			array(
				'title'       => '',
				'description' => '',
				'dependency'  => 'mailchimpList',
				'fields'      => array(
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__( 'Map Fields', 'gravityformsmailchimp' ),
						'type'      => 'field_map',
						'field_map' => $this->merge_vars_field_map(),
						'tooltip'   => '<h6>' . esc_html__( 'Map Fields', 'gravityformsmailchimp' ) . '</h6>' . esc_html__( 'Associate your MailChimp merge variables to the appropriate Gravity Form fields by selecting.', 'gravityformsmailchimp' ),
					),
					array(
						'name'       => 'groups',
						'label'      => esc_html__( 'Groups', 'gravityformsmailchimp' ),
						'dependency' => array( $this, 'has_mailchimp_groups' ),
						'type'       => 'mailchimp_groups',
						'tooltip'    => '<h6>' . esc_html__( 'Groups', 'gravityformsmailchimp' ) . '</h6>' . esc_html__( 'When one or more groups are enabled, users will be assigned to the groups in addition to being subscribed to the MailChimp list. When disabled, users will not be assigned to groups.', 'gravityformsmailchimp' ),
					),
					array(
						'name'    => 'optinCondition',
						'label'   => esc_html__( 'Conditional Logic', 'gravityformsmailchimp' ),
						'type'    => 'feed_condition',
						'tooltip' => '<h6>' . esc_html__( 'Conditional Logic', 'gravityformsmailchimp' ) . '</h6>' . esc_html__( 'When conditional logic is enabled, form submissions will only be exported to MailChimp when the conditions are met. When disabled all form submissions will be exported.', 'gravityformsmailchimp' ),
					),
					array(
						'name'    => 'options',
						'label'   => esc_html__( 'Options', 'gravityformsmailchimp' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'name'          => 'double_optin',
								'label'         => esc_html__( 'Double Opt-In', 'gravityformsmailchimp' ),
								'default_value' => 1,
								'tooltip'       => '<h6>' . esc_html__( 'Double Opt-In', 'gravityformsmailchimp' ) . '</h6>' . esc_html__( 'When the double opt-in option is enabled, MailChimp will send a confirmation email to the user and will only add them to your MailChimp list upon confirmation.', 'gravityformsmailchimp' ),
								'onclick'       => 'if(this.checked){jQuery("#mailchimp_doubleoptin_warning").hide();} else{jQuery("#mailchimp_doubleoptin_warning").show();}',
							),
							array(
								'name'    => 'sendWelcomeEmail',
								'label'   => esc_html__( 'Send Welcome Email', 'gravityformsmailchimp' ),
								'tooltip' => '<h6>' . esc_html__( 'Send Welcome Email', 'gravityformsmailchimp' ) . '</h6>' . esc_html__( 'When this option is enabled, users will receive an automatic welcome email from MailChimp upon being added to your MailChimp list.', 'gravityformsmailchimp' ),
							),
						)
					),
					array( 'type' => 'save' ),
				)
			),
		);
	}

	/**
	 * Define the markup for the mailchimp_list type field.
	 *
	 * @param array $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_mailchimp_list( $field, $echo = true ) {

		$api  = $this->get_api();
		$html = '';

		// getting all contact lists
		$this->log_debug( __METHOD__ . '(): Retrieving contact lists.' );
		try {
			$params = array(
				'start' => 0,
				'limit' => 100,
			);

			$params = apply_filters( 'gform_mailchimp_lists_params', $params );

			$lists = $api->call( 'lists/list', $params );
		} catch ( Exception $e ) {
			$this->log_error( __METHOD__ . '(): Could not load MailChimp contact lists. Error ' . $e->getCode() . ' - ' . $e->getMessage() );
		}

		if ( empty( $lists ) ) {
			printf( esc_html__( 'Could not load MailChimp contact lists. %sError: %s', 'gravityformsmailchimp' ), '<br/>', $e->getMessage() );
		} elseif ( empty( $lists['data'] ) ) {
			if ( $lists['total'] == 0 ) {
				//no lists found
				printf( esc_html__( 'Could not load MailChimp contact lists. %sError: %s', 'gravityformsmailchimp' ), '<br/>', esc_html__( 'No lists found.', 'gravityformsmailchimp' ) );
				$this->log_error( __METHOD__ . '(): Could not load MailChimp contact lists. Error ' . 'No lists found.' );
			} else {
				printf( esc_html__( 'Could not load MailChimp contact lists. %sError: %s', 'gravityformsmailchimp' ), '<br/>', rgar( $lists['errors'][0], 'error' ) );
				$this->log_error( __METHOD__ . '(): Could not load MailChimp contact lists. Error ' . rgar( $lists['errors'][0], 'error' ) );
			}
		} else {
			if ( isset( $lists['data'] ) && isset( $lists['total'] ) ) {
				$lists = $lists['data'];
				$this->log_debug( __METHOD__ . '(): Number of lists: ' . count( $lists ) );
			}

			$options = array(
				array(
					'label' => esc_html__( 'Select a MailChimp List', 'gravityformsmailchimp' ),
					'value' => ''
				)
			);
			foreach ( $lists as $list ) {
				$options[] = array(
					'label' => esc_html( $list['name'] ),
					'value' => esc_attr( $list['id'] )
				);
			}

			$field['type']     = 'select';
			$field['choices']  = $options;
			$field['onchange'] = 'jQuery(this).parents("form").submit();';

			$html = $this->settings_select( $field, false );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Return an array of MailChimp list fields which can be mapped to the Form fields/entry meta.
	 *
	 * @return array
	 */
	public function merge_vars_field_map() {

		$api       = $this->get_api();
		$list_id   = $this->get_setting( 'mailchimpList' );
		$field_map = array();

		if ( ! empty( $list_id ) ) {
			try {
				$params = array(
					'id' => array( $list_id ),
				);

				$lists = $api->call( 'lists/merge-vars', $params );
			} catch ( Exception $e ) {
				$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );

				return $field_map;
			}

			if ( empty( $lists['data'] ) ) {
				$this->log_error( __METHOD__ . '(): Unable to retrieve list due to ' . $lists['errors'][0]['code'] . ' - ' . $lists['errors'][0]['error'] );

				return $field_map;
			}
			$list       = $lists['data'][0];
			$merge_vars = $list['merge_vars'];

			foreach ( $merge_vars as $merge_var ) {
				$field_map[] = array(
					'name'       => $merge_var['tag'],
					'label'      => $merge_var['name'],
					'required'   => $merge_var['req'],
					'field_type' => $merge_var['tag'] == 'EMAIL' ? array( 'email', 'hidden' ) : '',
				);
			}
		}

		return $field_map;
	}

	/**
	 * Does the list have any groups configured?
	 *
	 * @return bool
	 */
	public function has_mailchimp_groups() {
		$groupings = $this->get_mailchimp_groups();

		return ! empty( $groupings );
	}

	/**
	 * Define the markup for the mailchimp_groups type field.
	 *
	 * @param array $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_mailchimp_groups( $field, $echo = true ) {

		$groupings = $this->get_mailchimp_groups();
		if ( empty( $groupings ) ) {
			$this->log_debug( __METHOD__ . '(): No groups found.' );

			return;
		}

		$str = "
		<style>
			.gaddon-mailchimp-groupname {font-weight: bold;}
			.gaddon-setting-checkbox {margin: 5px 0 0 0;}
			.gaddon-mailchimp-group .gf_animate_sub_settings {padding-left: 10px;}
		</style>

		<div id='gaddon-mailchimp_groups'>";

		foreach ( $groupings as $grouping ) {

			$grouping_label = $grouping['name'];

			$str .= "<div class='gaddon-mailchimp-group'>
						<div class='gaddon-mailchimp-groupname'>" . esc_attr( $grouping_label ) . "</div><div class='gf_animate_sub_settings'>";

			foreach ( $grouping['groups'] as $group ) {
				$setting_key_root   = $this->get_group_setting_key( $grouping['id'], $group['name'] );
				$choice_key_enabled = "{$setting_key_root}_enabled";


				$str .= $this->settings_checkbox(
					array(
						'name'    => $group['name'],
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'name'  => $choice_key_enabled,
								'label' => $group['name'],
							),
						),
						'onclick' => "if(this.checked){jQuery('#{$setting_key_root}_condition_container').slideDown();} else{jQuery('#{$setting_key_root}_condition_container').slideUp();}",
					), false
				);

				$str .= $this->group_condition( $setting_key_root );

			}
			$str .= '</div></div>';
		}
		$str .= '</div>';

		if ( $echo ) {
			echo $str;
		}

		return $str;

	}

	/**
	 * Define the markup for the group conditional logic.
	 *
	 * @param string $setting_name_root The group setting key.
	 *
	 * @return string
	 */
	public function group_condition( $setting_name_root ) {

		$condition_enabled_setting = "{$setting_name_root}_enabled";
		$is_enabled                = $this->get_setting( $condition_enabled_setting ) == '1';
		$container_style           = ! $is_enabled ? "style='display:none;'" : '';

		$str = "<div id='{$setting_name_root}_condition_container' {$container_style} class='condition_container'>" .
		       esc_html__( 'Assign to group:', 'gravityformsmailchimp' ) . ' ';

		$str .= $this->settings_select(
			array(
				'name'     => "{$setting_name_root}_decision",
				'type'     => 'select',
				'choices'  => array(
					array(
						'value' => 'always',
						'label' => esc_html__( 'Always', 'gravityformsmailchimp' )
					),
					array(
						'value' => 'if',
						'label' => esc_html__( 'If', 'gravityformsmailchimp' )
					),
				),
				'onchange' => "if(jQuery(this).val() == 'if'){jQuery('#{$setting_name_root}_decision_container').show();}else{jQuery('#{$setting_name_root}_decision_container').hide();}",
			), false
		);

		$decision = $this->get_setting( "{$setting_name_root}_decision" );
		if ( empty( $decision ) ) {
			$decision = 'always';
		}

		$conditional_style = $decision == 'always' ? "style='display:none;'" : '';

		$str .= '   <span id="' . $setting_name_root . '_decision_container" ' . $conditional_style . '><br />' .
		        $this->simple_condition( $setting_name_root, $is_enabled ) .
		        '   </span>' .

		        '</div>';

		return $str;
	}

	/**
	 * Define which field types can be used for the group conditional logic.
	 *
	 * @return array
	 */
	public function get_conditional_logic_fields() {
		$form   = $this->get_current_form();
		$fields = array();
		foreach ( $form['fields'] as $field ) {
			if ( $field->is_conditional_logic_supported() ) {
				$fields[] = array( 'value' => $field->id, 'label' => GFCommon::get_label( $field ) );
			}
		}

		return $fields;
	}

	/**
	 * Define the markup for the double_optin checkbox input.
	 *
	 * @param array $choice The choice properties.
	 * @param string $attributes The attributes for the input tag.
	 * @param string $value - Is choice selected? (1 if field has been checked. 0 or null otherwise)
	 * @param string $tooltip - The tooltip for this checkbox item.
	 *
	 * @return string
	 */
	public function checkbox_input_double_optin( $choice, $attributes, $value, $tooltip ) {
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		if ( $value ) {
			$display = 'none';
		} else {
			$display = 'block-inline';
		}

		$markup .= '<span id="mailchimp_doubleoptin_warning" style="padding-left: 10px; font-size: 10px; display:' . $display . '">(' . esc_html__( 'Abusing this may cause your MailChimp account to be suspended.', 'gravityformsmailchimp' ) . ')</span>';

		return $markup;
	}


	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * Validate the API Key
	 *
	 * @param string $apikey The MailChimp API key to be validated.
	 *
	 * @return bool|null
	 */
	public function is_valid_api_key( $apikey ) {
		if ( empty( $apikey ) ) {
			return null;
		}

		if ( ! class_exists( 'Mailchimp' ) ) {
			require_once( 'api/Mailchimp.php' );
		}

		$this->log_debug( __METHOD__ . "(): Validating login for API Info for key {$apikey}." );
		$api = new Mailchimp( trim( $apikey ), null );
		try {
			$lists = $api->call( 'lists/list', '' );
		} catch ( Exception $e ) {
			$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );
		}

		if ( empty( $lists ) ) {
			$this->log_error( __METHOD__ . '(): Invalid API Key. Error ' . $e->getCode() . ' - ' . $e->getMessage() );

			return false;
		} else {
			$this->log_debug( __METHOD__ . '(): API Key is valid.' );

			return true;
		}
	}

	/**
	 * Validate the API Key and return an instance of MailChimp class.
	 *
	 * @return Mailchimp|null
	 */
	private function get_api() {

		if ( self::$api ) {
			return self::$api;
		}

		$settings = $this->get_plugin_settings();
		$api      = null;

		if ( ! empty( $settings['apiKey'] ) ) {
			if ( ! class_exists( 'Mailchimp' ) ) {
				require_once( 'api/Mailchimp.php' );
			}

			$apikey = $settings['apiKey'];
			$this->log_debug( __METHOD__ . '(): Retrieving API Info for key ' . $apikey );

			try {
				$api = new Mailchimp( trim( $apikey ), null );
			} catch ( Exception $e ) {
				$this->log_error( __METHOD__ . '(): Failed to set up the API.' );
				$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );

				return null;
			}
		} else {
			$this->log_debug( __METHOD__ . '(): API credentials not set.' );

			return null;
		}

		if ( ! is_object( $api ) ) {
			$this->log_error( __METHOD__ . '(): Failed to set up the API.' );

			return null;
		}

		$this->log_debug( __METHOD__ . '(): Successful API response received.' );
		self::$api = $api;

		return self::$api;
	}

	/**
	 * Get the name of the specified MailChimp list.
	 *
	 * @param string $list_id The MailChimp list ID.
	 *
	 * @return string
	 */
	private function get_list_name( $list_id ) {
		global $_lists;
		if ( ! isset( $_lists ) ) {
			$api = $this->get_api();
			if ( ! is_object( $api ) ) {
				$this->log_error( __METHOD__ . '(): Failed to set up the API.' );

				return '';
			}
			try {
				$params = array(
					'start' => 0,
					'limit' => 100,
				);
				$_lists = $api->call( 'lists/list', $params );
			} catch ( Exception $e ) {
				$this->log_debug( __METHOD__ . '(): Could not load MailChimp contact lists. Error ' . $e->getCode() . ' - ' . $e->getMessage() );

				return '';
			}
		}

		$list_name_array = wp_filter_object_list( $_lists['data'], array( 'id' => $list_id ), 'and', 'name' );
		if ( $list_name_array ) {
			$list_names = array_values( $list_name_array );
			$list_name  = $list_names[0];
		} else {
			$list_name = $list_id . ' (' . esc_html__( 'List not found in MailChimp', 'gravityformsmailchimp' ) . ')';
		}

		return $list_name;
	}

	/**
	 * Retrieve the value of the mailchimpList setting.
	 *
	 * @return string
	 */
	public function get_current_mailchimp_list() {
		return $this->get_setting( 'mailchimpList' );
	}

	/**
	 * Retrieve the interest groups for the list.
	 *
	 * @param string|bool $mailchimp_list The MailChimp list ID.
	 *
	 * @return array|bool
	 */
	private function get_mailchimp_groups( $mailchimp_list = false ) {

		$this->log_debug( __METHOD__ . '(): Retrieving groups.' );
		$api = $this->get_api();

		if ( ! $mailchimp_list ) {
			$mailchimp_list = $this->get_current_mailchimp_list();
		}

		if ( ! $mailchimp_list ) {
			$this->log_error( __METHOD__ . '(): Could not find mailchimp list.' );

			return false;
		}

		try {
			$params = array( 'id' => $mailchimp_list );
			if ( empty( $api ) ) {
				$groups = array();
			} else {
				$groups = $api->call( 'lists/interest-groupings', $params );
			}
		} catch ( Exception $e ) {
			$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );
			$groups = array();
		}

		if ( rgar( $groups, 'status' ) == 'error' ) {
			$this->log_error( __METHOD__ . '(): ' . print_r( $groups, 1 ) );
			$groups = array();
		}

		return $groups;
	}

	/**
	 * Retrieve the settings for the specified group.
	 *
	 * @param array $group The group properties.
	 * @param string $grouping_id The group ID.
	 * @param array $feed The current feed.
	 *
	 * @return array
	 */
	private function get_group_settings( $group, $grouping_id, $feed ) {

		$prefix = $this->get_group_setting_key( $grouping_id, $group['name'] ) . '_';
		$props  = array( 'enabled', 'decision', 'field_id', 'operator', 'value' );

		$settings = array();
		foreach ( $props as $prop ) {
			$settings[ $prop ] = rgar( $feed['meta'], "{$prefix}{$prop}" );
		}

		return $settings;
	}

	/**
	 * Retrieve the group setting key.
	 *
	 * @param string $grouping_id The group ID.
	 * @param string $group_name The group name.
	 *
	 * @return string
	 */
	public function get_group_setting_key( $grouping_id, $group_name ) {

		$plugin_settings = GFCache::get( 'mailchimp_plugin_settings' );
		if ( empty( $plugin_settings ) ) {
			$plugin_settings = $this->get_plugin_settings();
			GFCache::set( 'mailchimp_plugin_settings', $plugin_settings );
		}

		$key = 'group_key_' . $grouping_id . '_' . str_replace( '%', '', sanitize_title_with_dashes( $group_name ) );

		if ( ! isset( $plugin_settings[ $key ] ) ) {
			$group_key               = sanitize_key( uniqid( 'mc_group_', true ) );
			$plugin_settings[ $key ] = $group_key;
			$this->update_plugin_settings( $plugin_settings );
			GFCache::set( 'mailchimp_plugin_settings', $plugin_settings );
		}

		return $plugin_settings[ $key ];
	}

	/**
	 * Determine if the user should be subscribed to the group.
	 *
	 * @param array $group The group properties.
	 * @param array $form The form currently being processed.
	 * @param array $entry The entry currently being processed.
	 *
	 * @return bool
	 */
	public function is_group_condition_met( $group, $form, $entry ) {
		if ( ! $group['enabled'] ) {
			$this->log_debug( __METHOD__ . '(): Group not enabled. Returning false.' );

			return false;
		} elseif ( $group['decision'] == 'always' ) {
			$this->log_debug( __METHOD__ . '(): Group decision is always. Returning true.' );

			return true;
		}

		$field = RGFormsModel::get_field( $form, $group['field_id'] );

		if ( ! is_object( $field ) ) {
			$this->log_debug( __METHOD__ . "(): Field #{$group['field_id']} not found. Returning true." );

			return true;
		} else {
			$field_value    = RGFormsModel::get_lead_field_value( $entry, $field );
			$is_value_match = RGFormsModel::is_value_match( $field_value, $group['value'], $group['operator'], $field );
			$this->log_debug( __METHOD__ . "(): Add to group if field #{$group['field_id']} value {$group['operator']} '{$group['value']}'. Is value match? " . var_export( $is_value_match, 1 ) );

			return $is_value_match;
		}

	}

	/**
	 * Returns the combined value of the specified Address field.
	 * Street 2 and Country are the only inputs not required by MailChimp.
	 * If other inputs are missing MailChimp will not store the field value, we will pass a hyphen when an input is empty.
	 * MailChimp requires the inputs be delimited by 2 spaces.
	 *
	 * @param array $entry The entry currently being processed.
	 * @param string $field_id The ID of the field to retrieve the value for.
	 *
	 * @return string
	 */
	public function get_full_address( $entry, $field_id ) {
		$street_value  = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.1' ) ) );
		$street2_value = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.2' ) ) );
		$city_value    = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.3' ) ) );
		$state_value   = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.4' ) ) );
		$zip_value     = trim( rgar( $entry, $field_id . '.5' ) );
		$country_value = trim( rgar( $entry, $field_id . '.6' ) );

		if ( ! empty( $country_value ) ) {
			$country_value = GF_Fields::get( 'address' )->get_country_code( $country_value );
		}

		$address = array(
			! empty( $street_value ) ? $street_value : '-',
			$street2_value,
			! empty( $city_value ) ? $city_value : '-',
			! empty( $state_value ) ? $state_value : '-',
			! empty( $zip_value ) ? $zip_value : '-',
			$country_value,
		);

		return implode( '  ', $address );
	}


	// # DEPRECATED ----------------------------------------------------------------------------------------------------

	/**
	 * Append the new interest groups to the current groups and update the list merge_vars.
	 *
	 * @param array $merge_vars The list merge_vars containing the new groups.
	 * @param array $current_groupings The current groups.
	 *
	 * @deprecated No longer used.
	 *
	 * @return array
	 */
	public function append_groups( $merge_vars, $current_groupings ) {

		if ( empty( $current_groupings ) ) {
			return $merge_vars;
		}

		$active_current_groups = array();
		$i                     = 0;
		foreach ( $current_groupings as &$current_group ) {
			foreach ( $current_group['groups'] as $current_grouping ) {
				if ( $current_grouping['interested'] == true ) {
					$active_current_groups[ $i ]['name']     = $current_group['name'];
					$active_current_groups[ $i ]['groups'][] = $current_grouping['name'];
				}
			}

			$i ++;
		}

		if ( ! isset( $merge_vars['GROUPINGS'] ) ) {
			$this->log_debug( __METHOD__ . '(): No groups to append. Returning existing groups.' );
			$merge_vars['GROUPINGS'] = $active_current_groups;
		} else {
			$this->log_debug( __METHOD__ . '(): Appending groups to existing groups.' );
			$merge_vars['GROUPINGS'] = array_merge_recursive( $merge_vars['GROUPINGS'], $active_current_groups );
		}

		return $merge_vars;
	}

	/**
	 * Retrieve existing groups.
	 *
	 * @param string $grouping_name The group name.
	 * @param array $current_groupings The current groups.
	 *
	 * @deprecated No longer used.
	 *
	 * @return array
	 */
	public static function get_existing_groups( $grouping_name, $current_groupings ) {

		foreach ( $current_groupings as $grouping ) {
			if ( strtolower( $grouping['name'] ) == strtolower( $grouping_name ) ) {
				return $grouping['groups'];
			}
		}

		return array();
	}


	// # TO FRAMEWORK MIGRATION ----------------------------------------------------------------------------------------

	/**
	 * Initialize the admin specific hooks.
	 */
	public function init_admin() {

		parent::init_admin();

		add_filter( 'gform_addon_navigation', array( $this, 'maybe_create_menu' ) );
	}

	/**
	 * Maybe add the temporary plugin page to the menu.
	 *
	 * @param array $menus
	 *
	 * @return array
	 */
	public function maybe_create_menu( $menus ) {

		$current_user           = wp_get_current_user();
		$dismiss_mailchimp_menu = get_metadata( 'user', $current_user->ID, 'dismiss_mailchimp_menu', true );
		if ( $dismiss_mailchimp_menu != '1' ) {
			$menus[] = array(
				'name'       => $this->_slug,
				'label'      => $this->get_short_title(),
				'callback'   => array( $this, 'temporary_plugin_page' ),
				'permission' => $this->_capabilities_form_settings
			);
		}

		return $menus;
	}

	/**
	 * Initialize the AJAX hooks.
	 */
	public function init_ajax() {
		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_mailchimp_menu', array( $this, 'ajax_dismiss_menu' ) );

	}

	/**
	 * Update the user meta to indicate they shouldn't see the temporary plugin page again.
	 */
	public function ajax_dismiss_menu() {

		$current_user = wp_get_current_user();
		update_metadata( 'user', $current_user->ID, 'dismiss_mailchimp_menu', '1' );
	}

	/**
	 * Display a temporary page explaining how feeds are now managed.
	 */
	public function temporary_plugin_page() {
		$current_user = wp_get_current_user();
		?>
		<script type="text/javascript">
			function dismissMenu() {
				jQuery('#gf_spinner').show();
				jQuery.post(ajaxurl, {
						action: "gf_dismiss_mailchimp_menu"
					},
					function (response) {
						document.location.href = '?page=gf_edit_forms';
						jQuery('#gf_spinner').hide();
					}
				);

			}
		</script>

		<div class="wrap about-wrap">
			<h1><?php esc_html_e( 'MailChimp Add-On v3.0', 'gravityformsmailchimp' ) ?></h1>

			<div class="about-text"><?php esc_html_e( 'Thank you for updating! The new version of the Gravity Forms MailChimp Add-On makes changes to how you manage your MailChimp integration.', 'gravityformsmailchimp' ) ?></div>
			<div class="changelog">
				<hr/>
				<div class="feature-section col two-col">
					<div class="col-1">
						<h3><?php esc_html_e( 'Manage MailChimp Contextually', 'gravityformsmailchimp' ) ?></h3>

						<p><?php esc_html_e( 'MailChimp Feeds are now accessed via the MailChimp sub-menu within the Form Settings for the Form with which you would like to integrate MailChimp.', 'gravityformsmailchimp' ) ?></p>
					</div>
					<div class="col-2 last-feature">
						<img src="http://gravityforms.s3.amazonaws.com/webimages/AddonNotice/NewMailChimp3.png">
					</div>
				</div>

				<hr/>

				<form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
					<input type="checkbox" name="dismiss_mailchimp_menu" value="1" onclick="dismissMenu();">
					<label><?php esc_html_e( 'I understand this change, dismiss this message!', 'gravityformsmailchimp' ) ?></label>
					<img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif' ?>" alt="<?php esc_attr_e( 'Please wait...', 'gravityformsmailchimp' ) ?>" style="display:none;"/>
				</form>

			</div>
		</div>
		<?php
	}

	/**
	 * Checks if a previous version was installed and if the feeds need migrating to the framework structure.
	 *
	 * @param string $previous_version The version number of the previously installed version.
	 */
	public function upgrade( $previous_version ) {

		if ( empty( $previous_version ) ) {
			$previous_version = get_option( 'gf_mailchimp_version' );
		}
		$previous_is_pre_addon_framework = ! empty( $previous_version ) && version_compare( $previous_version, '3.0.dev1', '<' );

		if ( $previous_is_pre_addon_framework ) {

			//get old plugin settings
			$old_settings = get_option( 'gf_mailchimp_settings' );
			//remove username and password from the old settings; these were very old legacy api settings that we do not support anymore

			if ( is_array( $old_settings ) ) {

				foreach ( $old_settings as $id => $setting ) {
					if ( $id != 'username' && $id != 'password' ) {
						if ( $id == 'apikey' ) {
							$id = 'apiKey';
						}
						$new_settings[ $id ] = $setting;
					}
				}
				$this->update_plugin_settings( $new_settings );

			}

			//get old feeds
			$old_feeds = $this->get_old_feeds();

			if ( $old_feeds ) {

				$counter = 1;
				foreach ( $old_feeds as $old_feed ) {
					$feed_name  = 'Feed ' . $counter;
					$form_id    = $old_feed['form_id'];
					$is_active  = rgar( $old_feed, 'is_active' ) ? '1' : '0';
					$field_maps = rgar( $old_feed['meta'], 'field_map' );
					$groups     = rgar( $old_feed['meta'], 'groups' );
					$list_id    = rgar( $old_feed['meta'], 'contact_list_id' );

					$new_meta = array(
						'feedName'         => $feed_name,
						'mailchimpList'    => $list_id,
						'double_optin'     => rgar( $old_feed['meta'], 'double_optin' ) ? '1' : '0',
						'sendWelcomeEmail' => rgar( $old_feed['meta'], 'welcome_email' ) ? '1' : '0',
					);

					//add mappings
					foreach ( $field_maps as $key => $mapping ) {
						$new_meta[ 'mappedFields_' . $key ] = $mapping;
					}

					if ( ! empty( $groups ) ) {
						$group_id = 0;
						//add groups to meta
						//get the groups from mailchimp because we need to use the main group id to build the key used to map the fields
						//old data only has the text, use the text to get the id
						$mailchimp_groupings = $this->get_mailchimp_groups( $list_id );

						//loop through the existing feed data to create mappings for new tables
						foreach ( $groups as $key => $group ) {
							//get the name of the top level group so the id can be retrieved from the mailchimp data
							foreach ( $mailchimp_groupings as $mailchimp_group ) {
								if ( str_replace( '%', '', sanitize_title_with_dashes( $mailchimp_group['name'] ) ) == $key ) {
									$group_id = $mailchimp_group['id'];
									break;
								}
							}

							if ( is_array( $group ) ) {
								foreach ( $group as $subkey => $subgroup ) {
									$setting_key_root                            = $this->get_group_setting_key( $group_id, $subgroup['group_label'] );
									$new_meta[ $setting_key_root . '_enabled' ]  = rgar( $subgroup, 'enabled' ) ? '1' : '0';
									$new_meta[ $setting_key_root . '_decision' ] = rgar( $subgroup, 'decision' );
									$new_meta[ $setting_key_root . '_field_id' ] = rgar( $subgroup, 'field_id' );
									$new_meta[ $setting_key_root . '_operator' ] = rgar( $subgroup, 'operator' );
									$new_meta[ $setting_key_root . '_value' ]    = rgar( $subgroup, 'value' );

								}
							}
						}
					}

					//add conditional logic, legacy only allowed one condition
					$conditional_enabled = rgar( $old_feed['meta'], 'optin_enabled' );
					if ( $conditional_enabled ) {
						$new_meta['feed_condition_conditional_logic']        = 1;
						$new_meta['feed_condition_conditional_logic_object'] = array(
							'conditionalLogic' =>
								array(
									'actionType' => 'show',
									'logicType'  => 'all',
									'rules'      => array(
										array(
											'fieldId'  => rgar( $old_feed['meta'], 'optin_field_id' ),
											'operator' => rgar( $old_feed['meta'], 'optin_operator' ),
											'value'    => rgar( $old_feed['meta'], 'optin_value' )
										),
									)
								)
						);
					} else {
						$new_meta['feed_condition_conditional_logic'] = 0;
					}

					$this->insert_feed( $form_id, $is_active, $new_meta );
					$counter ++;

				}

				//set paypal delay setting
				$this->update_paypal_delay_settings( 'delay_mailchimp_subscription' );
			}
		}
	}

	/**
	 * Migrate the delayed payment setting for the PayPal add-on integration.
	 *
	 * @param $old_delay_setting_name
	 */
	public function update_paypal_delay_settings( $old_delay_setting_name ) {
		global $wpdb;
		$this->log_debug( __METHOD__ . '(): Checking to see if there are any delay settings that need to be migrated for PayPal Standard.' );

		$new_delay_setting_name = 'delay_' . $this->_slug;

		//get paypal feeds from old table
		$paypal_feeds_old = $this->get_old_paypal_feeds();

		//loop through feeds and look for delay setting and create duplicate with new delay setting for the framework version of PayPal Standard
		if ( ! empty( $paypal_feeds_old ) ) {
			$this->log_debug( __METHOD__ . '(): Old feeds found for ' . $this->_slug . ' - copying over delay settings.' );
			foreach ( $paypal_feeds_old as $old_feed ) {
				$meta = $old_feed['meta'];
				if ( ! rgempty( $old_delay_setting_name, $meta ) ) {
					$meta[ $new_delay_setting_name ] = $meta[ $old_delay_setting_name ];
					//update paypal meta to have new setting
					$meta = maybe_serialize( $meta );
					$wpdb->update( "{$wpdb->prefix}rg_paypal", array( 'meta' => $meta ), array( 'id' => $old_feed['id'] ), array( '%s' ), array( '%d' ) );
				}
			}
		}

		//get paypal feeds from new framework table
		$paypal_feeds = $this->get_feeds_by_slug( 'gravityformspaypal' );
		if ( ! empty( $paypal_feeds ) ) {
			$this->log_debug( __METHOD__ . '(): New feeds found for ' . $this->_slug . ' - copying over delay settings.' );
			foreach ( $paypal_feeds as $feed ) {
				$meta = $feed['meta'];
				if ( ! rgempty( $old_delay_setting_name, $meta ) ) {
					$meta[ $new_delay_setting_name ] = $meta[ $old_delay_setting_name ];
					$this->update_feed_meta( $feed['id'], $meta );
				}
			}
		}
	}

	/**
	 * Retrieve any old PayPal feeds.
	 *
	 * @return bool|array
	 */
	public function get_old_paypal_feeds() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rg_paypal';

		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		$form_table_name = GFFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
				FROM {$table_name} s
				INNER JOIN {$form_table_name} f ON s.form_id = f.id";

		$this->log_debug( __METHOD__ . "(): getting old paypal feeds: {$sql}" );

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$this->log_debug( __METHOD__ . "(): error?: {$wpdb->last_error}" );

		$count = sizeof( $results );

		$this->log_debug( __METHOD__ . "(): count: {$count}" );

		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;
	}

	/**
	 * Retrieve any old feeds which need migrating to the framework,
	 *
	 * @return bool|array
	 */
	public function get_old_feeds() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rg_mailchimp';

		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		$form_table_name = GFFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
					FROM $table_name s
					INNER JOIN $form_table_name f ON s.form_id = f.id";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$count = sizeof( $results );
		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;
	}

}