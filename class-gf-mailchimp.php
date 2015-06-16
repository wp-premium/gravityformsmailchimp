<?php

GFForms::include_feed_addon_framework();

class GFMailChimp extends GFFeedAddOn {

	protected $_version = GF_MAILCHIMP_VERSION;
	protected $_min_gravityforms_version = '1.8.17';
	protected $_slug = 'gravityformsmailchimp';
	protected $_path = 'gravityformsmailchimp/mailchimp.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms MailChimp Add-On';
	protected $_short_title = 'MailChimp';

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_mailchimp', 'gravityforms_mailchimp_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_mailchimp';
	protected $_capabilities_form_settings = 'gravityforms_mailchimp';
	protected $_capabilities_uninstall = 'gravityforms_mailchimp_uninstall';
	protected $_enable_rg_autoupgrade = true;

	private static $api;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFMailChimp();
		}

		return self::$_instance;
	}

	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => __( 'Subscribe user to MailChimp only when payment is received.', 'gravityformsmailchimp' )
			)
		);

	}

	public function init_ajax() {
		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_mailchimp_menu', array( $this, 'ajax_dismiss_menu' ) );

	}

	public function init_admin() {

		parent::init_admin();

		add_filter( 'gform_addon_navigation', array( $this, 'maybe_create_menu' ) );
	}

	// ------- Plugin settings -------

	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => '',
				'description' => '<p>' . sprintf(
					__( 'MailChimp makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to collect customer information and automatically add them to your MailChimp subscriber list. If you don\'t have a MailChimp account, you can %1$s sign up for one here.%2$s', 'gravityformsmailchimp' ),
					'<a href="http://www.mailchimp.com/" target="_blank">', '</a>'
				) . '</p>',
				'fields'      => array(
					array(
						'name'              => 'apiKey',
						'label'             => __( 'MailChimp API Key', 'gravityformsmailchimp' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_api_key' )
					),
				)
			),
		);
	}

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

	public function feed_settings_fields() {
		return array(
			array(
				'title'       => __( 'MailChimp Feed Settings', 'gravityformsmailchimp' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => __( 'Name', 'gravityformsmailchimp' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => '<h6>' . __( 'Name', 'gravityformsmailchimp' ) . '</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gravityformsmailchimp' )
					),
					array(
						'name'     => 'mailchimpList',
						'label'    => __( 'MailChimp List', 'gravityformsmailchimp' ),
						'type'     => 'mailchimp_list',
						'required' => true,
						'tooltip'  => '<h6>' . __( 'MailChimp List', 'gravityformsmailchimp' ) . '</h6>' . __( 'Select the MailChimp list you would like to add your contacts to.', 'gravityformsmailchimp' ),
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
						'label'     => __( 'Map Fields', 'gravityformsmailchimp' ),
						'type'      => 'field_map',
						'field_map' => $this->merge_vars_field_map(),
						'tooltip'   => '<h6>' . __( 'Map Fields', 'gravityformsmailchimp' ) . '</h6>' . __( 'Associate your MailChimp merge variables to the appropriate Gravity Form fields by selecting.', 'gravityformsmailchimp' ),
					),
					array(
						'name'       => 'groups',
						'label'      => __( 'Groups', 'gravityformsmailchimp' ),
						'dependency' => array( $this, 'has_mailchimp_groups' ),
						'type'       => 'mailchimp_groups',
						'tooltip'    => '<h6>' . __( 'Groups', 'gravityformsmailchimp' ) . '</h6>' . __( 'When one or more groups are enabled, users will be assigned to the groups in addition to being subscribed to the MailChimp list. When disabled, users will not be assigned to groups.', 'gravityformsmailchimp' ),
					),
					array(
						'name'           => 'optinCondition',
						'label'          => __( 'Opt-In Condition', 'gravityformsmailchimp' ),
						'type'           => 'feed_condition',
						'checkbox_label' => __( 'Enable', 'gravityformsmailchimp' ),
						'instructions'   => __( 'Export to MailChimp if', 'gravityformsmailchimp' ),
						'tooltip'        => '<h6>' . __( 'Opt-In Condition', 'gravityformsmailchimp' ) . '</h6>' . __( 'When the opt-in condition is enabled, form submissions will only be exported to MailChimp when the conditions are met. When disabled all form submissions will be exported.', 'gravityformsmailchimp' ),
					),
					array(
						'name'    => 'options',
						'label'   => __( 'Options', 'gravityformsmailchimp' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'name'          => 'double_optin',
								'label'         => __( 'Double Opt-In', 'gravityformsmailchimp' ),
								'default_value' => 1,
								'tooltip'       => '<h6>' . __( 'Double Opt-In', 'gravityformsmailchimp' ) . '</h6>' . __( 'When the double opt-in option is enabled, MailChimp will send a confirmation email to the user and will only add them to your MailChimp list upon confirmation.', 'gravityformsmailchimp' ),
								'onclick'       => 'if(this.checked){jQuery("#mailchimp_doubleoptin_warning").hide();} else{jQuery("#mailchimp_doubleoptin_warning").show();}',
							),
							array(
								'name'    => 'sendWelcomeEmail',
								'label'   => __( 'Send Welcome Email', 'gravityformsmailchimp' ),
								'tooltip' => '<h6>' . __( 'Send Welcome Email', 'gravityformsmailchimp' ) . '</h6>' . __( 'When this option is enabled, users will receive an automatic welcome email from MailChimp upon being added to your MailChimp list.', 'gravityformsmailchimp' ),
							),
						)
					),
					array( 'type' => 'save' ),
				)
			),
		);
	}

	public function checkbox_input_double_optin( $choice, $attributes, $value, $tooltip ) {
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		if ( $value ) {
			$display = 'none';
		} else {
			$display = 'block-inline';
		}

		$markup .= '<span id="mailchimp_doubleoptin_warning" style="padding-left: 10px; font-size: 10px; display:' . $display . '">(' . __( 'Abusing this may cause your MailChimp account to be suspended.', 'gravityformsmailchimp' ) . ')</span>';

		return $markup;
	}


	//-------- Form Settings ---------
	public function feed_edit_page( $form, $feed_id ) {

		// getting MailChimp API
		$api = $this->get_api();

		// ensures valid credentials were entered in the settings page
		if ( ! $api ) {
			?>
			<div><?php echo sprintf(
					__( 'We are unable to login to MailChimp with the provided credentials. Please make sure they are valid in the %sSettings Page%s', 'gravityformsmailchimp' ),
					"<a href='" . esc_url( $this->get_plugin_settings_url() ) . "'>", '</a>'
				); ?>
			</div>

			<?php
			return;
		}

		echo '<script type="text/javascript">var form = ' . GFCommon::json_encode( $form ) . ';</script>';

		parent::feed_edit_page( $form, $feed_id );
	}

	public function feed_list_columns() {
		return array(
			'feedName'            => __( 'Name', 'gravityformsmailchimp' ),
			'mailchimp_list_name' => __( 'MailChimp List', 'gravityformsmailchimp' )
		);
	}

	public function get_column_value_mailchimp_list_name( $feed ) {
		return $this->get_list_name( $feed['meta']['mailchimpList'] );
	}

	public function settings_mailchimp_list( $field, $setting_value = '', $echo = true ) {

		$api  = $this->get_api();
		$html = '';

		// getting all contact lists
		$this->log_debug( __METHOD__ . '(): Retrieving contact lists.' );
		try {
			$params = array(
				'start' => 0,
				'limit' => 100,
			);

			$params = apply_filters( 'gform_mailchimp_lists_params' , $params );

			$lists  = $api->call( 'lists/list', $params );
		} catch ( Exception $e ) {
			$this->log_error( __METHOD__ . '(): Could not load MailChimp contact lists. Error ' . $e->getCode() . ' - ' . $e->getMessage() );
		}

		if ( empty( $lists ) ) {
			echo __( 'Could not load MailChimp contact lists. <br/>Error: ', 'gravityformsmailchimp' ) . $e->getMessage();
		} else if ( empty( $lists['data'] ) ) {
			if ( $lists['total'] == 0 ) {
				//no lists found
				echo __( 'Could not load MailChimp contact lists. <br/>Error: ', 'gravityformsmailchimp' ) . 'No lists found.';
				$this->log_error( __METHOD__ . '(): Could not load MailChimp contact lists. Error ' . 'No lists found.' );
			} else {
				echo __( 'Could not load MailChimp contact lists. <br/>Error: ', 'gravityformsmailchimp' ) . rgar( $lists['errors'][0], 'error' );
				$this->log_error( __METHOD__ . '(): Could not load MailChimp contact lists. Error ' . rgar( $lists['errors'][0], 'error' ) );
			}
		} else {
			if ( isset( $lists['data'] ) && isset( $lists['total'] ) ) {
				$lists = $lists['data'];
				$this->log_debug( __METHOD__ . '(): Number of lists: ' . count( $lists ) );
			}

			$options = array(
				array(
					'label' => __( 'Select a MailChimp List', 'gravityformsmailchimp' ),
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

			$html = $this->settings_select( $field, $setting_value, false );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function settings_mailchimp_groups( $field, $setting_value = '', $echo = true ) {

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
					'name'     => $merge_var['tag'],
					'label'    => $merge_var['name'],
					'required' => $merge_var['req'],
				);
			}
		}

		return $field_map;
	}

	public function has_mailchimp_groups() {
		$groupings = $this->get_mailchimp_groups();

		return ! empty( $groupings );
	}

	public function group_condition( $setting_name_root ) {

		$condition_enabled_setting = "{$setting_name_root}_enabled";
		$is_enabled                = $this->get_setting( $condition_enabled_setting ) == '1';
		$container_style           = ! $is_enabled ? "style='display:none;'" : '';

		$str = "<div id='{$setting_name_root}_condition_container' {$container_style} class='condition_container'>" .
		       __( 'Assign to group:', 'gravityformsmailchimp' ) . ' ';

		$str .= $this->settings_select(
			array(
				'name'     => "{$setting_name_root}_decision",
				'type'     => 'select',
				'choices'  => array(
					array(
						'value' => 'always',
						'label' => __( 'Always', 'gravityformsmailchimp' )
					),
					array(
						'value' => 'if',
						'label' => __( 'If', 'gravityformsmailchimp' )
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

	public function get_conditional_logic_fields() {
		$form   = $this->get_current_form();
		$fields = array();
		foreach ( $form['fields'] as $field ) {
			$type                     = GFFormsModel::get_input_type( $field );
			$conditional_logic_fields = array(
				'checkbox',
				'radio',
				'select',
				'text',
				'website',
				'textarea',
				'email',
				'hidden',
				'number',
				'phone',
				'multiselect',
				'post_title',
				'post_tags',
				'post_custom_field',
				'post_content',
				'post_excerpt',
			);
			if ( in_array( $type, $conditional_logic_fields ) ) {
				$fields[] = array( 'value' => $field['id'], 'label' => $field['label'] );
			}
		}

		return $fields;
	}


	//------ Core Functionality ------

	public function process_feed( $feed, $entry, $form ) {
		$this->log_debug( __METHOD__ . '(): Processing feed.' );

		// login to MailChimp
		$api = $this->get_api();
		if ( ! is_object( $api ) ) {
			$this->log_error( __METHOD__ . '(): Failed to set up the API.' );

			return;
		}

		$feed_meta = $feed['meta'];

		$double_optin = $feed_meta['double_optin'] == true;
		$send_welcome = $feed_meta['sendWelcomeEmail'] == true;

		// retrieve name => value pairs for all fields mapped in the 'mappedFields' field map
		$field_map = $this->get_field_map_fields( $feed, 'mappedFields' );
		$email     = rgar( $entry, $field_map['EMAIL'] );

		$override_empty_fields = apply_filters( "gform_mailchimp_override_empty_fields_{$form['id']}", apply_filters( 'gform_mailchimp_override_empty_fields', true, $form, $entry, $feed ), $form, $entry, $feed );
		if ( ! $override_empty_fields ) {
			$this->log_debug( __METHOD__ . '(): Empty fields will not be overridden.' );
		}

		$merge_vars = array( '' );
		foreach ( $field_map as $name => $field_id ) {

			if ( $name == 'EMAIL' ) {
				continue;
			}

			// $field_id can also be a string like 'date_created'
			switch ( strtolower( $field_id ) ) {
				case 'form_title':
					$merge_vars[ $name ] = rgar( $form, 'title' );
					break;

				case 'date_created':
				case 'ip':
				case 'source_url':
					$merge_vars[ $name ] = rgar( $entry, strtolower( $field_id ) );
					break;

				default :
					$field       = RGFormsModel::get_field( $form, $field_id );
					$is_integer  = $field_id == intval( $field_id );
					$input_type  = RGFormsModel::get_input_type( $field );
					$field_value = rgar( $entry, $field_id );

					// handling full address
					if ( $is_integer && $input_type == 'address' ) {
						$field_value = $this->get_address( $entry, $field_id );
					} // handling full name
					elseif ( $is_integer && $input_type == 'name' ) {
						$field_value = $this->get_name( $entry, $field_id );
					} // handling phone
					elseif ( $is_integer && $input_type == 'phone' && $field['phoneFormat'] == 'standard' ) {
						// reformat phone to go to mailchimp when standard format (US/CAN)
						// needs to be in the format NPA-NXX-LINE 404-555-1212 when US/CAN
						$phone = $field_value;
						if ( preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $phone, $matches ) ) {
							$field_value = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
						}
					} // send selected checkboxes as a concatenated string
					elseif ( $is_integer && $input_type == 'checkbox' ) {
						$selected = array();
						foreach ( $field['inputs'] as $input ) {
							$index = (string) $input['id'];
							if ( ! rgempty( $index, $entry ) ) {
								$selected[] = apply_filters( 'gform_mailchimp_field_value', rgar( $entry, $index ), $form['id'], $field_id, $entry, $name );
							}
						}
						$field_value = join( ', ', $selected );
					}

					if ( empty( $field_value ) && ! $override_empty_fields ) {
						break;
					} else {
						$merge_vars[ $name ] = apply_filters( 'gform_mailchimp_field_value', $field_value, $form['id'], $field_id, $entry, $name );
					}
			}
		}

		$mc_groupings = $this->get_mailchimp_groups( $feed_meta['mailchimpList'] );
		$groupings    = array();

		if ( $mc_groupings !== false ) {
			foreach ( $mc_groupings as $grouping ) {

				if ( ! is_array( $grouping['groups'] ) ) {
					continue;
				}

				$groups = array();

				foreach ( $grouping['groups'] as $group ) {

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
		}


		if ( ! empty( $groupings ) ) {
			$merge_vars['GROUPINGS'] = $groupings;
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
			$allow_resubscription = apply_filters( 'gform_mailchimp_allow_resubscription', apply_filters( "gform_mailchimp_allow_resubscription_{$form['id']}", true, $form, $entry, $feed ), $form, $entry, $feed );
			if ( $member_status == 'unsubscribed' && ! $allow_resubscription ) {
				$this->log_debug( __METHOD__ . '(): User is unsubscribed and resubscription is not allowed.' );

				return true;
			}

			// adding member to list, statuses of $member_status != 'subscribed', 'pending', 'cleaned' need to be
			// 're-subscribed' to send out confirmation email
			$this->log_debug( __METHOD__ . "(): {$email} is either not on the list or on the list but the status is not subscribed - status: " . $member_status . '; adding to list.' );
			$transaction = 'Subscribe';
			try {
				$params = array(
					'id'                => $list_id,
					'email'             => array( 'email' => $email ),
					'merge_vars'        => $merge_vars,
					'email_type'        => 'html',
					'double_optin'      => $double_optin,
					'update_existing'   => false,
					'replace_interests' => true,
					'send_welcome'      => $send_welcome,
				);
				$params = apply_filters( "gform_mailchimp_args_pre_subscribe_{$form['id']}",
					apply_filters( 'gform_mailchimp_args_pre_subscribe', $params, $form, $entry, $feed, $transaction ),
					$form, $entry, $feed, $transaction );

				$this->log_debug( __METHOD__ . '(): Calling - subscribe, Parameters ' . print_r( $params, true ) );
				$subscribe_or_update = $api->call( 'lists/subscribe', $params );
			} catch ( Exception $e ) {
				$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );
			}
		} else {
			// updating member
			$this->log_debug( __METHOD__ . "(): {$email} is already on the list; updating info." );

			// retrieve existing groups for subscribers
			$current_groups = rgar( $member_info['data'][0]['merges'], 'GROUPINGS' );

			$keep_existing_groups = apply_filters( "gform_mailchimp_keep_existing_groups_{$form['id']}", apply_filters( 'gform_mailchimp_keep_existing_groups', true, $form, $entry, $feed ), $form, $entry, $feed );
			if ( is_array( $current_groups ) && $keep_existing_groups ) {
				// add existing groups to selected groups from form so that existing groups are maintained for that subscriber
				$merge_vars = $this->append_groups( $merge_vars, $current_groups );
			}

			$transaction = 'Update';

			try {
				$params = array(
					'id'                => $list_id,
					'email'             => array( 'email' => $email ),
					'merge_vars'        => $merge_vars,
					'email_type'        => 'html',
					'replace_interests' => true,
				);
				$params = apply_filters( "gform_mailchimp_args_pre_subscribe_{$form['id']}",
					apply_filters( 'gform_mailchimp_args_pre_subscribe', $params, $form, $entry, $feed, $transaction ),
					$form, $entry, $feed, $transaction );

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


	//------- Helpers ----------------

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
			$list_name = $list_id . ' (' . __( 'List not found in MailChimp', 'gravityformsmailchimp' ) . ')';
		}

		return $list_name;
	}

	public function get_current_mailchimp_list() {
		return $this->get_setting( 'mailchimpList' );
	}

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

	private function get_group_settings( $group, $grouping_id, $feed ) {

		$prefix = $this->get_group_setting_key( $grouping_id, $group['name'] ) . '_';
		$props  = array( 'enabled', 'decision', 'field_id', 'operator', 'value' );

		$settings = array();
		foreach ( $props as $prop ) {
			$settings[ $prop ] = rgar( $feed['meta'], "{$prefix}{$prop}" );
		}

		return $settings;
	}

	public function get_group_setting_key( $grouping_id, $group_name ) {

		$plugin_settings = GFCache::get( 'mailchimp_plugin_settings' );
		if ( empty( $plugin_settings ) ) {
			$plugin_settings = $this->get_plugin_settings();
			GFCache::set( 'mailchimp_plugin_settings', $plugin_settings );
		}

		$key = 'group_key_' . $grouping_id . '_' . str_replace( '%', '', sanitize_title_with_dashes( $group_name ) );

		if ( ! isset( $plugin_settings[ $key ] ) ) {
			$group_key               = 'mc_group_' . uniqid();
			$plugin_settings[ $key ] = $group_key;
			$this->update_plugin_settings( $plugin_settings );
			GFCache::set( 'mailchimp_plugin_settings', $plugin_settings );
		}

		return $plugin_settings[ $key ];
	}

	public static function is_group_condition_met( $group, $form, $entry ) {

		$field       = RGFormsModel::get_field( $form, $group['field_id'] );
		$field_value = RGFormsModel::get_lead_field_value( $entry, $field );

		$is_value_match = RGFormsModel::is_value_match( $field_value, $group['value'], $group['operator'], $field );

		if ( ! $group['enabled'] ) {
			return false;
		} else if ( $group['decision'] == 'always' || empty( $field ) ) {
			return true;
		} else {
			return $is_value_match;
		}

	}

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

	public static function get_existing_groups( $grouping_name, $current_groupings ) {

		foreach ( $current_groupings as $grouping ) {
			if ( strtolower( $grouping['name'] ) == strtolower( $grouping_name ) ) {
				return $grouping['groups'];
			}
		}

		return array();
	}

	private function get_address( $entry, $field_id ) {
		$street_value  = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.1' ) ) );
		$street2_value = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.2' ) ) );
		$city_value    = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.3' ) ) );
		$state_value   = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.4' ) ) );
		$zip_value     = trim( rgar( $entry, $field_id . '.5' ) );
		$country_value = trim( rgar( $entry, $field_id . '.6' ) );

		if ( ! empty( $country_value ) ) {
			$country_value = class_exists( 'GF_Field_Address' ) ? GF_Fields::get( 'address' )->get_country_code( $country_value ) : GFCommon::get_country_code( $country_value );
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

	private function get_name( $entry, $field_id ) {

		//If field is simple (one input), simply return full content
		$name = rgar( $entry, $field_id );
		if ( ! empty( $name ) ) {
			return $name;
		}

		//Complex field (multiple inputs). Join all pieces and create name
		$prefix = trim( rgar( $entry, $field_id . '.2' ) );
		$first  = trim( rgar( $entry, $field_id . '.3' ) );
		$middle = trim( rgar( $entry, $field_id . '.4' ) );
		$last   = trim( rgar( $entry, $field_id . '.6' ) );
		$suffix = trim( rgar( $entry, $field_id . '.8' ) );

		$name = $prefix;
		$name .= ! empty( $name ) && ! empty( $first ) ? ' ' . $first : $first;
		$name .= ! empty( $name ) && ! empty( $middle ) ? ' ' . $middle : $middle;
		$name .= ! empty( $name ) && ! empty( $last ) ? ' ' . $last : $last;
		$name .= ! empty( $name ) && ! empty( $suffix ) ? ' ' . $suffix : $suffix;

		return $name;
	}


	//------ Temporary Notice for Main Menu --------------------//

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

	public function ajax_dismiss_menu() {

		$current_user = wp_get_current_user();
		update_metadata( 'user', $current_user->ID, 'dismiss_mailchimp_menu', '1' );
	}

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
			<h1><?php _e( 'MailChimp Add-On v3.0', 'gravityformsmailchimp' ) ?></h1>

			<div
				class="about-text"><?php _e( 'Thank you for updating! The new version of the Gravity Forms MailChimp Add-On makes changes to how you manage your MailChimp integration.', 'gravityformsmailchimp' ) ?></div>
			<div class="changelog">
				<hr/>
				<div class="feature-section col two-col">
					<div class="col-1">
						<h3><?php _e( 'Manage MailChimp Contextually', 'gravityformsmailchimp' ) ?></h3>

						<p><?php _e( 'MailChimp Feeds are now accessed via the MailChimp sub-menu within the Form Settings for the Form with which you would like to integrate MailChimp.', 'gravityformsmailchimp' ) ?></p>
					</div>
					<div class="col-2 last-feature">
						<img src="http://gravityforms.s3.amazonaws.com/webimages/AddonNotice/NewMailChimp3.png">
					</div>
				</div>

				<hr/>

				<form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
					<input type="checkbox" name="dismiss_mailchimp_menu" value="1" onclick="dismissMenu();">
					<label><?php _e( 'I understand this change, dismiss this message!', 'gravityformsmailchimp' ) ?></label>
					<img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif'?>"
					     alt="<?php _e( 'Please wait...', 'gravityformsmailchimp' ) ?>" style="display:none;"/>
				</form>

			</div>
		</div>
	<?php
	}


	//------ FOR BACKWARDS COMPATIBILITY ----------------------//

	//Migrate existing data to new table structure
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