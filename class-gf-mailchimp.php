<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms Mailchimp Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GFMailChimp extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  3.0
	 * @access private
	 * @var    GFMailChimp $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Mailchimp Add-On.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_version Contains the version, defined from mailchimp.php
	 */
	protected $_version = GF_MAILCHIMP_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '1.9.12';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsmailchimp';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsmailchimp/mailchimp.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'http://www.gravityforms.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'Gravity Forms Mailchimp Add-On';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Mailchimp';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capabilities needed for the Mailchimp Add-On
	 *
	 * @since  3.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_mailchimp', 'gravityforms_mailchimp_uninstall' );

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_mailchimp';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_mailchimp';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_mailchimp_uninstall';

	/**
	 * Defines the Mailchimp list/audience field tag name.
	 *
	 * @since  3.7
	 * @access protected
	 * @var    string $merge_var_name The Mailchimp list/audience field tag name; used by gform_mailchimp_field_value.
	 */
	protected $merge_var_name = '';

	/**
	 * Defines the Mailchimp merge fields used in the current request.
	 *
	 * @since  4.2.4
	 * @access protected
	 * @var    array $merge_fields The Mailchimp merge fields used in the current request.
	 */
	protected $merge_fields = array();

	/**
	 * Contains an instance of the Mailchimp API library, if available.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    GF_MailChimp_API $api If available, contains an instance of the Mailchimp API library.
	 */
	public $api = null;

	/**
	 * Get an instance of this class.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return GFMailChimp
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;

	}

	/**
	 * Autoload the required libraries.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @uses GFAddOn::is_gravityforms_supported()
	 */
	public function pre_init() {

		parent::pre_init();

		if ( $this->is_gravityforms_supported() ) {

			// Load the Mailchimp API library.
			if ( ! class_exists( 'GF_MailChimp_API' ) ) {
				require_once( 'includes/class-gf-mailchimp-api.php' );
			}

		}

	}

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @uses GFFeedAddOn::add_delayed_payment_support()
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe user to Mailchimp only when payment is received.', 'gravityformsmailchimp' ),
			)
		);

	}

	/**
	 * Remove unneeded settings.
	 *
	 * @since  4.0
	 * @access public
	 */
	public function uninstall() {

		parent::uninstall();

		GFCache::delete( 'mailchimp_plugin_settings' );
		delete_option( 'gf_mailchimp_settings' );
		delete_option( 'gf_mailchimp_version' );

	}

	/**
	 * Register needed styles.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @return array
	 */
	public function styles() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$styles = array(
			array(
				'handle'  => $this->_slug . '_form_settings',
				'src'     => $this->get_base_url() . "/css/form_settings{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 4.7
	 *
	 * @return string
	 */
	public function get_menu_icon() {

		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' );

	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'description' => '<p>' .
					sprintf(
						esc_html__( 'Mailchimp makes it easy to send email newsletters to your customers, manage your subscriber audiences, and track campaign performance. Use Gravity Forms to collect customer information and automatically add it to your Mailchimp subscriber audience. If you don\'t have a Mailchimp account, you can %1$ssign up for one here.%2$s', 'gravityformsmailchimp' ),
						'<a href="http://www.mailchimp.com/" target="_blank">', '</a>'
					)
					. '</p>',
				'fields'      => array(
					array(
						'name'              => 'apiKey',
						'label'             => esc_html__( 'Mailchimp API Key', 'gravityformsmailchimp' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
				),
			),
		);

	}





	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		$settings = array(
			array(
				'title'  => esc_html__( 'Mailchimp Feed Settings', 'gravityformsmailchimp' ),
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gravityformsmailchimp' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Name', 'gravityformsmailchimp' ),
							esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsmailchimp' )
						),
					),
					array(
						'name'     => 'mailchimpList',
						'label'    => esc_html__( 'Mailchimp Audience', 'gravityformsmailchimp' ),
						'type'     => 'mailchimp_list',
						'required' => true,
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Mailchimp Audience', 'gravityformsmailchimp' ),
							esc_html__( 'Select the Mailchimp audience you would like to add your contacts to.', 'gravityformsmailchimp' )
						),
					),
				),
			),
			array(
				'dependency' => 'mailchimpList',
				'fields'     => array(
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__( 'Map Fields', 'gravityformsmailchimp' ),
						'type'      => 'field_map',
						'field_map' => $this->merge_vars_field_map(),
						'tooltip'   => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Map Fields', 'gravityformsmailchimp' ),
							esc_html__( 'Associate your Mailchimp merge tags to the appropriate Gravity Form fields by selecting the appropriate form field from the list.', 'gravityformsmailchimp' )
						),
					),
					array(
						'name'       => 'interestCategories',
						'label'      => esc_html__( 'Groups', 'gravityformsmailchimp' ),
						'dependency' => array( $this, 'has_interest_categories' ),
						'type'       => 'interest_categories',
						'tooltip'    => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Groups', 'gravityformsmailchimp' ),
							esc_html__( 'When one or more groups are enabled, users will be assigned to the groups in addition to being subscribed to the Mailchimp audience. When disabled, users will not be assigned to groups.', 'gravityformsmailchimp' )
						),
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
								'onclick'       => 'if(this.checked){jQuery("#mailchimp_doubleoptin_warning").hide();} else{jQuery("#mailchimp_doubleoptin_warning").show();}',
								'tooltip'       => sprintf(
									'<h6>%s</h6>%s',
									esc_html__( 'Double Opt-In', 'gravityformsmailchimp' ),
									esc_html__( 'When the double opt-in option is enabled, Mailchimp will send a confirmation email to the user and will only add them to your Mailchimp audience upon confirmation.', 'gravityformsmailchimp' )
								),
							),
							array(
								'name'  => 'markAsVIP',
								'label' => esc_html__( 'Mark subscriber as VIP', 'gravityformsmailchimp' ),
							),
						),
					),
					array(
						'name'  => 'tags',
						'type'  => 'text',
						'class' => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label' => esc_html__( 'Tags', 'gravityformsmailchimp' ),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Tags', 'gravityformsmailchimp' ),
							esc_html__( 'Associate tags to your Mailchimp contacts with a comma separated list (e.g. new lead, Gravity Forms, web source). Commas within a merge tag value will be created as a single tag.', 'gravityformsmailchimp' )
						),
					),
					array(
						'name'  => 'note',
						'type'  => 'textarea',
						'class' => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label' => esc_html__( 'Note', 'gravityformsmailchimp' ),
					),
					array(
						'name'    => 'optinCondition',
						'label'   => esc_html__( 'Conditional Logic', 'gravityformsmailchimp' ),
						'type'    => 'feed_condition',
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gravityformsmailchimp' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to Mailchimp when the conditions are met. When disabled all form submissions will be exported.', 'gravityformsmailchimp' )
						),
					),
					array( 'type' => 'save' ),
				),
			),
		);

		// Get currently selected list/audience.
		$list = $this->get_setting( 'mailchimpList' );

		// If a list/audience is selected, get marketing permissions and add setting.
		if ( $list ) {

			try {

				// Get Mailchimp list/audience,
				$list = $this->api->get_list( $list );

				// If marketing permissions are enabled for list/audience, add setting.
				if ( rgar( $list, 'marketing_permissions' ) ) {

					// Prepare setting.
					$setting = array(
						'name'    => 'marketingPermissions',
						'label'   => esc_html__( 'Marketing Permissions', 'gravityformsmailchimp' ),
						'type'    => 'marketing_permissions',
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Marketing Permissions', 'gravityformsmailchimp' ),
							esc_html__( 'When enabled and conditions are met, users will be opted into your Mailchimp audience marketing permissions. If a user is already subscribed to your audience, they will not be opted out of permissions they are already opted into.', 'gravityformsmailchimp' )
						),
					);

					// Add setting.
					$settings = $this->add_field_after( 'interestCategories', $setting, $settings );

				}

			} catch ( Exception $e ) {

				// Log that list/audience could not be retrieved.
				$this->log_error( __METHOD__ . '(): Unable to add Marketing Permissions field because audience could not be retrieved; ' . $e->getMessage() );

			}

		}

		return $settings;

	}

	/**
	 * Define the markup for the mailchimp_list type field.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param array $field The field properties.
	 * @param bool  $echo  Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string
	 */
	public function settings_mailchimp_list( $field, $echo = true ) {

		// Initialize HTML string.
		$html = '';

		// If API is not initialized, return.
		if ( ! $this->initialize_api() ) {
			return $html;
		}

		// Prepare list/audience request parameters.
		$params = array( 'start' => 0, 'limit' => 100 );

		// Filter parameters.
		$params = apply_filters( 'gform_mailchimp_lists_params', $params );

		// Convert start parameter to 3.0.
		if ( isset( $params['start'] ) ) {
			$params['offset'] = $params['start'];
			unset( $params['start'] );
		}

		// Convert limit parameter to 3.0.
		if ( isset( $params['limit'] ) ) {
			$params['count'] = $params['limit'];
			unset( $params['limit'] );
		}

		try {

			// Log contact lists/audiences request parameters.
			$this->log_debug( __METHOD__ . '(): Retrieving contact audiences; params: ' . print_r( $params, true ) );

			// Get lists/audiences.
			$lists = $this->api->get_lists( $params );

		} catch ( Exception $e ) {

			// Log that contact lists/audiences could not be obtained.
			$this->log_error( __METHOD__ . '(): Could not retrieve Mailchimp contact audiences; ' . $e->getMessage() );

			// Display error message.
			printf( esc_html__( 'Could not load Mailchimp contact audiences. %sError: %s', 'gravityformsmailchimp' ), '<br/>', $e->getMessage() );

			return;

		}

		// If no lists/audiences were found, display error message.
		if ( 0 === $lists['total_items'] ) {

			// Log that no lists/audiences were found.
			$this->log_error( __METHOD__ . '(): Could not load Mailchimp contact audiences; no audiences found.' );

			// Display error message.
			printf( esc_html__( 'Could not load Mailchimp contact audiences. %sError: %s', 'gravityformsmailchimp' ), '<br/>', esc_html__( 'No audiences found.', 'gravityformsmailchimp' ) );

			return;

		}

		// Log number of lists/audiences retrieved.
		$this->log_debug( __METHOD__ . '(): Number of audiences: ' . count( $lists['lists'] ) );

		// Initialize select options.
		$options = array(
			array(
				'label' => esc_html__( 'Select a Mailchimp Audience', 'gravityformsmailchimp' ),
				'value' => '',
			),
		);

		// Loop through Mailchimp lists/audiences.
		foreach ( $lists['lists'] as $list ) {

			// Add list/audience to select options.
			$options[] = array(
				'label' => esc_html( $list['name'] ),
				'value' => esc_attr( $list['id'] ),
			);

		}

		// Add select field properties.
		$field['type']     = 'select';
		$field['choices']  = $options;
		$field['onchange'] = 'jQuery(this).parents("form").submit();';

		// Generate select field.
		$html = $this->settings_select( $field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Return an array of Mailchimp list/audience fields which can be mapped to the Form fields/entry meta.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function merge_vars_field_map() {

		// Initialize field map array.
		$field_map = array(
			'EMAIL' => array(
				'name'       => 'EMAIL',
				'label'      => esc_html__( 'Email Address', 'gravityformsmailchimp' ),
				'required'   => true,
				'field_type' => array( 'email', 'hidden' ),
			),
		);

		// If unable to initialize API, return field map.
		if ( ! $this->initialize_api() ) {
			return $field_map;
		}

		// Get current list/audience ID.
		$list_id = $this->get_setting( 'mailchimpList' );

		// Get merge fields.
		$merge_fields = $this->get_list_merge_fields( $list_id );

		// If merge fields exist, add to field map.
		if ( ! empty( $merge_fields['merge_fields'] ) ) {

			// Loop through merge fields.
			foreach ( $merge_fields['merge_fields'] as $merge_field ) {

				// Define required field type.
				$field_type = null;

				// If this is an email merge field, set field types to "email" or "hidden".
				if ( 'EMAIL' === strtoupper( $merge_field['tag'] ) ) {
					$field_type = array( 'email', 'hidden' );
				}

				// If this is an address merge field, set field type to "address".
				if ( 'address' === $merge_field['type'] ) {
					$field_type = array( 'address' );
				}

				// Add to field map.
				$field_map[ $merge_field['tag'] ] = array(
					'name'       => $merge_field['tag'],
					'label'      => $merge_field['name'],
					'required'   => $merge_field['required'],
					'field_type' => $field_type,
				);

			}

		}

		return $field_map;
	}

	/**
	 * Prevent feeds being listed or created if the API key isn't valid.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		return $this->initialize_api();

	}

	/**
	 * Allow the feed to be duplicated.
	 *
	 * @since 4.7
	 *
	 * @param array|int $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {

		return true;

	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feedName'            => esc_html__( 'Name', 'gravityformsmailchimp' ),
			'mailchimp_list_name' => esc_html__( 'Mailchimp List', 'gravityformsmailchimp' ),
		);

	}

	/**
	 * Returns the value to be displayed in the Mailchimp List/Audience column.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param array $feed The feed being included in the feed list/audience.
	 *
	 * @return string
	 */
	public function get_column_value_mailchimp_list_name( $feed ) {

		// If unable to initialize API, return the list/audience ID.
		if ( ! $this->initialize_api() ) {
			return rgars( $feed, 'meta/mailchimpList' );
		}

		try {

			// Get list/audience.
			$list = $this->api->get_list( rgars( $feed, 'meta/mailchimpList' ) );

			// Return list/audience name.
			return rgar( $list, 'name' );

		} catch ( Exception $e ) {

			// Log error.
			$this->log_error( __METHOD__ . '(): Unable to get Mailchimp audience for feed list; ' . $e->getMessage() );

			// Return list/audience ID.
			return rgars( $feed, 'meta/mailchimpList' );

		}

	}

	/**
	 * Define the markup for the interest categories type field.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param array $field The field properties.
	 * @param bool  $echo  Should the setting markup be echoed.
	 *
	 * @return string
	 */
	public function settings_interest_categories( $field, $echo = true ) {

		// Get interest categories.
		$categories = $this->get_interest_categories();

		// If no categories are found, return.
		if ( empty( $categories ) ) {
			$this->log_debug( __METHOD__ . '(): No categories found.' );
			return '';
		}

		// Start field markup.
		$html = "<div id='gaddon-mailchimp_interest_categories'>";

		// Loop through interest categories.
		foreach ( $categories as $category ) {

			// Open category container.
			$html .= '<div class="gaddon-mailchimp-category">';

			// Define label.
			$label = rgar( $category, 'title' );

			// Display category label.
			$html .= '<div class="gaddon-mailchimp-categoryname">' . esc_html( $label ) . '</div><div class="gf_animate_sub_settings">';

			// Get interests category interests.
			$interests = $this->api->get_interest_category_interests( $category['list_id'], $category['id'] );

			// Loop through interests.
			foreach ( $interests as $interest ) {

				// Define interest key.
				$interest_key = 'interestCategory_' . $interest['id'];

				// Define enabled checkbox key.
				$enabled_key = $interest_key . '_enabled';

				// Get interest checkbox markup.
				$html .= $this->settings_checkbox(
					array(
						'name'    => esc_html( $interest['name'] ),
						'type'    => 'checkbox',
						'onclick' => "if(this.checked){jQuery('#{$interest_key}_condition_container').slideDown();} else{jQuery('#{$interest_key}_condition_container').slideUp();}",
						'choices' => array(
							array(
								'name'  => $enabled_key,
								'label' => esc_html( $interest['name'] ),
							),
						),
					),
					false
				);

				$html .= $this->interest_category_condition( $interest_key );

			}

			$html .= '</div></div>';
		}

		$html .= '</div>';

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Define the markup for the interest category conditional logic.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $setting_name_root The category setting key.
	 *
	 * @return string
	 */
	public function interest_category_condition( $setting_name_root ) {

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

		$str .= '   <span id="' . $setting_name_root . '_decision_container" class="gform-settings-simple-condition" ' . $conditional_style . '><br />' .
		        $this->simple_condition( $setting_name_root, $is_enabled ) .
		        '   </span>' .

		        '</div>';

		return $str;

	}

	/**
	 * Define the markup for the Marketing Permissions feed settings field.
	 *
	 * @since  4.6
	 * @access public
	 *
	 * @param array $field The field properties.
	 * @param bool  $echo  Should the setting markup be echoed.
	 *
	 * @return string
	 */
	public function settings_marketing_permissions( $field, $echo = true ) {

		// Get current list/audience.
		$list = $this->get_setting( 'mailchimpList' );

		// Get marketing permissions.
		$permissions = $this->get_marketing_permissions( $list );

		// If permissions are not available, display error message.
		if ( ! $permissions ) {

			$html = esc_html__( 'You must have at least one audience subscriber to configure Marketing Permissions.', 'gravityformsmailchimp' );
			$html .= '&nbsp;' . gform_tooltip( esc_html__( "Due to limitations with Mailchimp's API, we are only able to get available Marketing Permissions when the selected audience has at least one subscriber.", 'gravityformsmailchimp' ), '', true );

			if ( $echo ) {
				echo $html;
			}

			return $html;

		}

		// Start field markup.
		$html = "<div id='gaddon-mailchimp_marketing_permissions'>";

		// Loop through marketing permissions, add conditional logic for each.
		foreach ( $permissions as $permission ) {

			// Prepare permission key.
			$permission_key = $field['name'] . '_' . $permission['marketing_permission_id'];

			// Open category container.
			$html .= '<div class="gaddon-mailchimp-permission">';

			// Display toggle checkbox.
			$html .= $this->settings_checkbox(
				array(
					'name'    => esc_html( $permission['marketing_permission_id'] ),
					'type'    => 'checkbox',
					'choices' => array(
						array(
							'name'    => $permission_key . '_enabled',
							'label'   => esc_html( $permission['text'] ),
							'class'   => 'gaddon-mailchimp-permission-toggle',
							'onclick' => "if(this.checked){jQuery('#{$permission_key}_condition_container').slideDown();} else{jQuery('#{$permission_key}_condition_container').slideUp();}",
						),
					),
				),
				false
			);

			// Display condition field for permission.
			$html .= $this->marketing_permissions_condition( $permission_key );

			$html .= '</div>';

		}

		$html .= '</div>';

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Define the markup for the Marketing Permissions conditional logic.
	 *
	 * @since  4.6
	 * @access public
	 *
	 * @param string $setting_name_root The category setting key.
	 *
	 * @return string
	 */
	public function marketing_permissions_condition( $setting_name_root ) {

		$condition_enabled_setting = "{$setting_name_root}_enabled";
		$is_enabled                = '1' === $this->get_setting( $condition_enabled_setting );
		$container_style           = ! $is_enabled ? "style='display:none;'" : '';

		$str = sprintf(
			'<div id="%s_condition_container" %s class="condition_container gf_animate_sub_settings"><span id="%s_condition_label" class="condition_label">%s</span>',
			$setting_name_root,
			$container_style,
			$setting_name_root,
			esc_html__( 'Enable permission if:', 'gravityformsmailchimp' )
		);


		$str .= '   <span id="' . $setting_name_root . '_decision_container" class="gform-settings-simple-condition"><br />' .
				$this->simple_condition( $setting_name_root, $is_enabled ) .
				'   </span>' .

				'</div>';

		return $str;

	}


	/**
	 * Define which field types can be used for the group conditional logic.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @uses GFAddOn::get_current_form()
	 * @uses GFCommon::get_label()
	 * @uses GF_Field::get_entry_inputs()
	 * @uses GF_Field::get_input_type()
	 * @uses GF_Field::is_conditional_logic_supported()
	 *
	 * @return array
	 */
	public function get_conditional_logic_fields() {

		// Initialize conditional logic fields array.
		$fields = array();

		// Get the current form.
		$form = $this->get_current_form();

		/**
		 * Loop through the form fields.
		 *
		 * @var GF_Field $field
		 */
		foreach ( $form['fields'] as $field ) {

			// If this field does not support conditional logic, skip it.
			if ( ! $field->is_conditional_logic_supported() ) {
				continue;
			}

			// Get field inputs.
			$inputs = $field->get_entry_inputs();

			// If field has multiple inputs, add them as individual field options.
			if ( $inputs && 'checkbox' !== $field->get_input_type() ) {

				// Loop through the inputs.
				foreach ( $inputs as $input ) {

					// If this is a hidden input, skip it.
					if ( rgar( $input, 'isHidden' ) ) {
						continue;
					}

					// Add input to conditional logic fields array.
					$fields[] = array(
						'value' => $input['id'],
						'label' => GFCommon::get_label( $field, $input['id'] ),
					);

				}

			} else {

				// Add field to conditional logic fields array.
				$fields[] = array(
					'value' => $field->id,
					'label' => GFCommon::get_label( $field ),
				);

			}

		}

		return $fields;

	}

	/**
	 * Define the markup for the double_optin checkbox input.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param array  $choice     The choice properties.
	 * @param string $attributes The attributes for the input tag.
	 * @param string $value      Is choice selected (1 if field has been checked. 0 or null otherwise).
	 * @param string $tooltip    The tooltip for this checkbox item.
	 *
	 * @return string
	 */
	public function checkbox_input_double_optin( $choice, $attributes, $value, $tooltip ) {

		// Get checkbox input markup.
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		// Define visibility status of warning.
		$display = $value ? 'none' : 'block-inline';

		// Add warning to checkbox markup.
		$markup .= '<span id="mailchimp_doubleoptin_warning" style="padding-left: 10px; font-size: 10px; display:' . $display . '">(' . esc_html__( 'Abusing this may cause your Mailchimp account to be suspended.', 'gravityformsmailchimp' ) . ')</span>';

		return $markup;

	}





	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed, subscribe the user to the list/audience.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param array $feed  The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 *
	 * @return array
	 */
	public function process_feed( $feed, $entry, $form ) {

		// Log that we are processing feed.
		$this->log_debug( __METHOD__ . '(): Processing feed.' );

		// If unable to initialize API, log error and return.
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Unable to process feed because API could not be initialized.', 'gravityformsmailchimp' ), $feed, $entry, $form );
			return $entry;
		}

		// Set current merge variable name.
		$this->merge_var_name = 'EMAIL';

		// Get field map values.
		$field_map = $this->get_field_map_fields( $feed, 'mappedFields' );

		// Get mapped email address.
		$email = $this->get_field_value( $form, $entry, $field_map['EMAIL'] );

		// If email address is invalid, log error and return.
		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			$this->add_feed_error( esc_html__( 'A valid Email address must be provided.', 'gravityformsmailchimp' ), $feed, $entry, $form );
			return $entry;
		}

		/**
		 * Prevent empty form fields erasing values already stored in the mapped Mailchimp MMERGE fields
		 * when updating an existing subscriber.
		 *
		 * @param bool  $override If the merge field should be overridden.
		 * @param array $form     The form object.
		 * @param array $entry    The entry object.
		 * @param array $feed     The feed object.
		 */
		$override_empty_fields = gf_apply_filters( 'gform_mailchimp_override_empty_fields', array( $form['id'] ), true, $form, $entry, $feed );

		// Log that empty fields will not be overridden.
		if ( ! $override_empty_fields ) {
			$this->log_debug( __METHOD__ . '(): Empty fields will not be overridden.' );
		}

		// Initialize array to store merge vars.
		$merge_vars = array();

		// Loop through field map.
		foreach ( $field_map as $name => $field_id ) {

			// If no field is mapped, skip it.
			if ( rgblank( $field_id ) ) {
				continue;
			}

			// If this is the email field, skip it.
			if ( strtoupper( $name ) === 'EMAIL' ) {
				continue;
			}

			// Set merge var name to current field map name.
			$this->merge_var_name = $name;

			// Get field object.
			$field = GFFormsModel::get_field( $form, $field_id );

			// Get field value.
			$field_value = $this->get_field_value( $form, $entry, $field_id );

			// If field value is empty and we are not overriding empty fields, skip it.
			if ( empty( $field_value ) && ( ! $override_empty_fields || ( is_object( $field ) && 'address' === $field->get_input_type() ) ) ) {
				continue;
			}

			// Get merge field.
			$merge_field = $this->get_list_merge_field( $feed['meta']['mailchimpList'], $name );

			// Format date field.
			if ( ! empty( $field_value ) && ! empty( $merge_field ) && in_array( $merge_field['type'], array( 'date', 'birthday' ) ) ) {

				// Get date format.
				$date_format = $merge_field['options']['date_format'];

				// Convert field value to timestamp.
				$field_value_timestamp = strtotime( $field_value );

				// Format date.
				switch( $date_format ) {

					case 'DD/MM':
					case 'MM/DD':
						$field_value = date( 'm/d', $field_value_timestamp );
						break;

					case 'DD/MM/YYYY':
					case 'MM/DD/YYYY':
						$field_value = date( 'm/d/Y', $field_value_timestamp );
						break;

				}

			}

			$merge_vars[ $name ] = $field_value;

		}

		// Define initial member, member found and member status variables.
		$member        = false;
		$member_found  = false;
		$member_status = null;

		try {

			// Log that we are checking if user is already subscribed to list/audience.
			$this->log_debug( __METHOD__ . "(): Checking to see if $email is already on the audience." );

			// Get member info.
			$member = $this->api->get_list_member( $feed['meta']['mailchimpList'], $email );

			// Set member found status to true.
			$member_found = true;

			// Set member status.
			$member_status = $member['status'];

			// Log member status.
			$this->log_debug( __METHOD__ . "(): $email was found on audience. Status: $member_status" );

		} catch ( Exception $e ) {

			// If the exception code is not 404, abort feed processing.
			if ( 404 !== $e->getCode() ) {

				// Log that we could not get the member information.
				$this->add_feed_error( sprintf( esc_html__( 'Unable to check if email address is already used by a member: %s', 'gravityformsmailchimp' ), $e->getMessage() ), $feed, $entry, $form );

				return $entry;

			}

			// Log member status.
			$this->log_debug( __METHOD__ . "(): $email was not found on audience." );

		}

		/**
		 * Modify whether a user that currently has a status of unsubscribed on your list/audience is resubscribed.
		 * By default, the user is resubscribed.
		 *
		 * @param bool  $allow_resubscription If the user should be resubscribed.
		 * @param array $form                 The form object.
		 * @param array $entry                The entry object.
		 * @param array $feed                 The feed object.
		 */
		$allow_resubscription = gf_apply_filters( array( 'gform_mailchimp_allow_resubscription', $form['id'] ), true, $form, $entry, $feed );

		// If member is unsubscribed and resubscription is not allowed, exit.
		if ( 'unsubscribed' == $member_status && ! $allow_resubscription ) {
			$this->log_debug( __METHOD__ . '(): User is unsubscribed and resubscription is not allowed.' );
			return;
		}

		/**
		 * Modify whether a user that is already subscribed to your list/audience has their groups replaced when submitting the form a second time.
		 *
		 * @since 1.9
		 *
		 * @param bool   $keep_existing_interests Should user keep existing interest categories?
		 * @param array  $form                    The form object.
		 * @param array  $entry                   The entry object.
		 * @param array  $feed                    The feed object.
		 */
		$keep_existing_interests = gf_apply_filters( array( 'gform_mailchimp_keep_existing_groups', $form['id'] ), true, $form, $entry, $feed );

		// Initialize interests to keep array.
		$interests_to_keep = array();

		// Initialize interests array.
		$interests = $existing_interests = rgar( $member, 'interests', array() );

		// If member was found, has existing interests and we are not keeping existing interest categories, remove them.
		if ( $member_found && $existing_interests ) {

			// Loop through existing interests.
			foreach ( $existing_interests as $interest_id => $interest_enabled ) {

				// If interest is not enabled, skip it.
				if ( ! $interest_enabled ) {
					continue;
				}

				// If we are keeping existing interests, add to array.
				if ( $keep_existing_interests ) {

					$interests_to_keep[] = $interest_id;
					continue;

				} else if ( ! $keep_existing_interests ) {

					// Disable interest in new subscription.
					$interests[ $interest_id ] = false;

				}

			}

		}

		// Get interest categories.
		$categories = $this->get_feed_setting_conditions( $feed );

		// Loop through categories.
		foreach ( $categories as $category_id => $category_meta ) {

			// If category is not enabled or the category is one we are keeping, skip it.
			if ( ! rgar( $category_meta, 'enabled' ) || in_array( $category_id, $interests_to_keep ) ) {
				continue;
			}

			// Log that we are evaluating the category conditions.
			$this->log_debug( __METHOD__ . '(): Evaluating condition for interest category "' . $category_id . '": ' . print_r( $category_meta, true ) );

			// Get condition evaluation.
			$condition_evaluation = $this->is_category_condition_met( $category_meta, $form, $entry );

			// Set interest category based on evaluation.
			$interests[ $category_id ] = $condition_evaluation;

		}

		// Get tags.
		$tags = explode(',', rgars( $feed, 'meta/tags' ) );
		$tags = array_map( 'trim', $tags );

		// Prepare tags.
		if ( ! empty( $tags ) ) {

			// Loop through tags, replace merge tags.
			foreach ( $tags as &$tag ) {
				$tag = GFCommon::replace_variables( $tag, $form, $entry, false, false, false, 'text' );
				$tag = trim( $tag );
			}

			// Remove empty tags.
			$tags = array_filter( $tags );

		}

		// If member status is not defined or is anything other than pending, set to subscribed.
		$member_status = isset( $member_status ) && $member_status === 'pending' ? $member_status : 'subscribed';

		// Prepare subscription arguments.
		$subscription = array(
			'id'           => $feed['meta']['mailchimpList'],
			'email'        => array( 'email' => $email ),
			'merge_vars'   => $merge_vars,
			'interests'    => $interests,
			'email_type'   => 'html',
			'double_optin' => rgars( $feed, 'meta/double_optin' ) ? true : false,
			'status'       => $member_status,
			'ip_signup'    => rgar( $entry, 'ip' ),
			'vip'          => rgars( $feed, 'meta/markAsVIP' ) ? true : false,
			'note'         => rgars( $feed, 'meta/note' ),
			'tags'         => array(),
		);

		// Get existing tags.
		$existing_tags = $member ? wp_list_pluck( $member['tags'], 'name' ) : array();

		// Add tags to subscription.
		if ( ! empty( $tags ) ) {
			$subscription['tags'] = $member ? array_merge( $existing_tags, $tags ) : $tags;
			$subscription['tags'] = array_unique( $subscription['tags'] );
		} else {
			$subscription['tags'] = $member ? $existing_tags : $subscription['tags'];
			$subscription['tags'] = array_unique( $subscription['tags'] );
		}

		// Prepare transaction type for filter.
		$transaction = $member_found ? 'Update' : 'Subscribe';

		/**
		 * Modify the subscription object before it is executed.
		 *
		 * @deprecated 4.0 @use gform_mailchimp_subscription
		 *
		 * @param array  $subscription Subscription arguments.
		 * @param array  $form         The form object.
		 * @param array  $entry        The entry object.
		 * @param array  $feed         The feed object.
		 * @param string $transaction  Transaction type. Defaults to Subscribe.
		 */
		$subscription = gf_apply_filters( array( 'gform_mailchimp_args_pre_subscribe', $form['id'] ), $subscription, $form, $entry, $feed, $transaction );

		// Convert merge vars.
		$subscription['merge_fields'] = $subscription['merge_vars'];
		unset( $subscription['merge_vars'] );

		// Convert double optin.
		$subscription['status'] = $subscription['double_optin'] ? 'pending' : $subscription['status'];
		unset( $subscription['double_optin'] );

		// Extract list/audience ID.
		$list_id = $subscription['id'];
		unset( $subscription['id'] );

		// Convert email address.
		$subscription['email_address'] = $subscription['email']['email'];
		unset( $subscription['email'] );

		/**
		 * Modify the subscription object before it is executed.
		 *
		 * @since 4.1.9 Added existing member object as $member parameter.
		 *
		 * @param array       $subscription Subscription arguments.
		 * @param string      $list_id      Mailchimp list/audience ID.
		 * @param array       $form         The form object.
		 * @param array       $entry        The entry object.
		 * @param array       $feed         The feed object.
		 * @param array|false $member       The existing member object. (False if member does not currently exist in Mailchimp.)
		 */
		$subscription = gf_apply_filters( array( 'gform_mailchimp_subscription', $form['id'] ), $subscription, $list_id, $form, $entry, $feed, $member );

		// Remove merge_fields if none are defined.
		if ( empty( $subscription['merge_fields'] ) ) {
			unset( $subscription['merge_fields'] );
		}

		// Remove interests if none are defined.
		if ( empty( $subscription['interests'] ) ) {
			unset( $subscription['interests'] );
		}

		// Remove VIP if not enabled.
		if ( ! $subscription['vip'] ) {
			unset( $subscription['vip'] );
		}

		// Remove tags from subscription object.
		$tags = $subscription['tags'];
		unset( $subscription['tags'] );
		foreach ( $tags as &$tag ) {
			$tag = array( 'name' => $tag, 'status' => 'active' );
		}

		// Add Marketing Permissions.
		if ( $permissions = $this->get_feed_setting_conditions( $feed, 'marketingPermissions' ) ) {

			// If member already exists, only update newly enabled permissions.
			if ( $member_found ) {

				// Loop through existing Marketing Permissions, check condition.
				foreach ( $member['marketing_permissions'] as $existing_permission ) {

					// If permission is already enabled, keep it that way.
					if ( $existing_permission['enabled'] ) {
						$subscription['marketing_permissions'][] = $existing_permission;
						continue;
					}

					// If this permission is not configured, skip.
					if ( ! rgar( $permissions, $existing_permission['marketing_permission_id'] ) ) {
						continue;
					}

					// Check condition and add to subscription.
					$subscription['marketing_permissions'][] = array(
						'marketing_permission_id' => $existing_permission['marketing_permission_id'],
						'enabled'                 => $this->is_marketing_permission_condition_met( $permissions[ $existing_permission['marketing_permission_id'] ], $form, $entry ),
					);

				}

			} else {

				// Loop through permissions, add if enabled.
				foreach ( $permissions as $permission_id => $permission ) {

					// Add to subscription.
					$subscription['marketing_permissions'][] = array(
						'marketing_permission_id' => $permission_id,
						'enabled'                 => $this->is_marketing_permission_condition_met( $permission, $form, $entry ),
					);

				}

			}

		}

		// Remove note from the subscription object and process any merge tags.
		$note = GFCommon::replace_variables( $subscription['note'], $form, $entry, false, true, false, 'text' );
		unset( $subscription['note'] );

		$action = $member_found ? 'updated' : 'added';

		try {

			// Log the subscriber to be added or updated.
			$this->log_debug( __METHOD__ . "(): Subscriber to be {$action}: " . print_r( $subscription, true ) );

			// Add or update subscriber.
			$this->api->update_list_member( $list_id, $subscription['email_address'], $subscription );

			// Log that the subscription was added or updated.
			$this->log_debug( __METHOD__ . "(): Subscriber successfully {$action}." );

		} catch ( Exception $e ) {

			// Log that subscription could not be added or updated.
			$this->add_feed_error( sprintf( esc_html__( 'Unable to add/update subscriber: %s', 'gravityformsmailchimp' ), $e->getMessage() ), $feed, $entry, $form );

			// Log field errors.
			if ( $e->hasErrors() ) {
				$this->log_error( __METHOD__ . '(): Field errors when attempting subscription: ' . print_r( $e->getErrors(), true ) );
			}

			return $entry;

		}

		try {

			// Log the subscriber tags to be added or updated.
			$this->log_debug( __METHOD__ . "(): Subscriber tags to be {$action}: " . print_r( $tags, true ) );

			// Update tags.
			$this->api->update_member_tags( $list_id, $subscription['email_address'], $tags );

			// Log that the subscriber tags was added or updated.
			$this->log_debug( __METHOD__ . "(): Subscriber tags successfully {$action}." );

		} catch ( Exception $e ) {

			// Log that subscription could not be added or updated.
			$this->add_feed_error( sprintf( esc_html__( 'Unable to add/update subscriber tags: %s', 'gravityformsmailchimp' ), $e->getMessage() ), $feed, $entry, $form );

		}

		if ( ! $note ) {
			// Abort as there is no note to process.
			return $entry;
		}

		try {

			// Add the note to the member.
			$this->api->add_member_note( $list_id, $subscription['email_address'], $note );
			$this->log_debug( __METHOD__ . '(): Note successfully added to subscriber.' );

		} catch ( Exception $e ) {

			// Log that the note could not be added.
			$this->add_feed_error( sprintf( esc_html__( 'Unable to add note to subscriber: %s', 'gravityformsmailchimp' ), $e->getMessage() ), $feed, $entry, $form );

			return $entry;

		}

	}

	/**
	 * Returns the value of the selected field.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param array  $form     The form object currently being processed.
	 * @param array  $entry    The entry object currently being processed.
	 * @param string $field_id The ID of the field being processed.
	 *
	 * @uses GFAddOn::get_full_name()
	 * @uses GF_Field::get_value_export()
	 * @uses GFFormsModel::get_field()
	 * @uses GFFormsModel::get_input_type()
	 * @uses GFMailChimp::get_full_address()
	 * @uses GFMailChimp::maybe_override_field_value()
	 *
	 * @return array|string
	 */
	public function get_field_value( $form, $entry, $field_id ) {

		// Set initial field value.
		$field_value = '';

		// Set field value based on field ID.
		switch ( strtolower( $field_id ) ) {

			// Form title.
			case 'form_title':
				$field_value = rgar( $form, 'title' );
				break;

			// Entry creation date.
			case 'date_created':

				// Get entry creation date from entry.
				$date_created = rgar( $entry, strtolower( $field_id ) );

				// If date is not populated, get current date.
				$field_value = empty( $date_created ) ? gmdate( 'Y-m-d H:i:s' ) : $date_created;
				break;

			// Entry IP and source URL.
			case 'ip':
			case 'source_url':
				$field_value = rgar( $entry, strtolower( $field_id ) );
				break;

			default:

				// Get field object.
				$field = GFFormsModel::get_field( $form, $field_id );

				if ( is_object( $field ) ) {

					// Check if field ID is integer to ensure field does not have child inputs.
					$is_integer = $field_id == intval( $field_id );

					// Get field input type.
					$input_type = GFFormsModel::get_input_type( $field );

					if ( $is_integer && 'address' === $input_type ) {

						// Get full address for field value.
						$field_value = $this->get_full_address( $entry, $field_id );

					} else if ( $is_integer && 'name' === $input_type ) {

						// Get full name for field value.
						$field_value = $this->get_full_name( $entry, $field_id );

					} else if ( $is_integer && 'checkbox' === $input_type ) {

						// Initialize selected options array.
						$selected = array();

						// Loop through checkbox inputs.
						foreach ( $field->inputs as $input ) {
							$index = (string) $input['id'];
							if ( ! rgempty( $index, $entry ) ) {
								$selected[] = $this->maybe_override_field_value( rgar( $entry, $index ), $form, $entry, $index );
							}
						}

						// Convert selected options array to comma separated string.
						$field_value = implode( ', ', $selected );

					} else if ( 'phone' === $input_type && $field->phoneFormat == 'standard' ) {

						// Get field value.
						$field_value = rgar( $entry, $field_id );

						// Reformat standard format phone to match Mailchimp format.
						// Format: NPA-NXX-LINE (404-555-1212) when US/CAN.
						if ( ! empty( $field_value ) && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches ) ) {
							$field_value = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
						}

					} else {

						// Use export value if method exists for field.
						if ( is_callable( array( 'GF_Field', 'get_value_export' ) ) ) {
							$field_value = $field->get_value_export( $entry, $field_id );
						} else {
							$field_value = rgar( $entry, $field_id );
						}

					}

				} else {

					// Get field value from entry.
					$field_value = rgar( $entry, $field_id );

				}

		}

		return $this->maybe_override_field_value( $field_value, $form, $entry, $field_id );

	}

	/**
	 * Use the legacy gform_mailchimp_field_value filter instead of the framework gform_SLUG_field_value filter.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param string $field_value The field value.
	 * @param array  $form        The form object currently being processed.
	 * @param array  $entry       The entry object currently being processed.
	 * @param string $field_id    The ID of the field being processed.
	 *
	 * @return string
	 */
	public function maybe_override_field_value( $field_value, $form, $entry, $field_id ) {

		return gf_apply_filters( 'gform_mailchimp_field_value', array( $form['id'], $field_id ), $field_value, $form['id'], $field_id, $entry, $this->merge_var_name );

	}





	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * Initializes Mailchimp API if credentials are valid.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $api_key Mailchimp API key.
	 *
	 * @uses GFAddOn::get_plugin_setting()
	 * @uses GFAddOn::log_debug()
	 * @uses GFAddOn::log_error()
	 * @uses GF_MailChimp_API::account_details()
	 *
	 * @return bool|null
	 */
	public function initialize_api( $api_key = null ) {

		// If API is already initialized, return true.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Get the API key.
		if ( rgblank( $api_key ) ) {
			$api_key = $this->get_plugin_setting( 'apiKey' );
		}

		// If the API key is blank, do not run a validation check.
		if ( rgblank( $api_key ) ) {
			return null;
		}

		// Log validation step.
		$this->log_debug( __METHOD__ . '(): Validating API Info.' );

		// Setup a new Mailchimp object with the API credentials.
		$mc = new GF_MailChimp_API( $api_key );

		try {

			// Retrieve account information.
			$mc->account_details();

			// Assign API library to class.
			$this->api = $mc;

			// Log that authentication test passed.
			$this->log_debug( __METHOD__ . '(): Mailchimp successfully authenticated.' );

			return true;

		} catch ( Exception $e ) {

			// Log that authentication test failed.
			$this->log_error( __METHOD__ . '(): Unable to authenticate with Mailchimp; '. $e->getMessage() );

			return false;

		}

	}

	/**
	 * Retrieve the interest groups for the list/audience.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param string $list_id Mailchimp list/audience ID.
	 *
	 * @return array|bool
	 */
	private function get_interest_categories( $list_id = null ) {

		// If API is not initialized, return false.
		if ( ! $this->initialize_api() ) {
			return false;
		}

		// Get Mailchimp list/audience ID.
		if ( rgblank( $list_id ) ) {
			$list_id = $this->get_setting( 'mailchimpList' );
		}

		// If Mailchimp list/audience ID is not defined, return.
		if ( rgblank( $list_id ) ) {

			// Log that list/audience ID was not defined.
			$this->log_error( __METHOD__ . '(): Could not get Mailchimp interest categories because audience ID was not defined.' );

			return false;

		}

		try {

			// Get groups.
			$categories = $this->api->get_list_interest_categories( $list_id );

		} catch ( Exception $e ) {

			// Log error.
			$this->log_error( __METHOD__ . '(): Unable to get interest categories for audience "' . $list_id . '"; ' . $e->getMessage() );

			return array();

		}

		return $categories;

	}

	/**
	 * Get available marketing permissions for a list/audience.
	 *
	 * @since 4.6
	 * @access public
	 *
	 * @param string $list_id Mailchimp List/Audience ID.
	 *
	 * @return array|bool
	 */
	private function get_marketing_permissions( $list_id ) {

		$cache_key = 'gravityformsmailchimp_permissions_' . $list_id;

		// Check cache for permissions.
		if ( $permissions = GFCache::get( $cache_key ) ) {
			return $permissions;
		}

		try {

			// Get Mailchimp list/audience.
			$list = $this->api->get_list( $list_id );

		} catch ( Exception $e ) {

			// Log that list/audience could not be retrieved.
			$this->log_error( __METHOD__ . '(): Unable to get marketing permissions because audience could not be retrieved; ' . $e->getMessage() );

			return false;

		}

		// If marketing permissions are disabled, return.
		if ( ! rgar( $list, 'marketing_permissions' ) ) {
			return false;
		}

		try {

			// Get a list/audience member.
			$members = $this->api->get_list_members( $list_id, array( 'count' => 1 ) );
			$member  = rgar( $members, 'members' ) ? $members['members'][0] : false;


		} catch ( Exception $e ) {

			// Log that list/audience could not be retrieved.
			$this->log_error( __METHOD__ . '(): Unable to get marketing permissions because audience members could not be retrieved; ' . $e->getMessage() );

			return false;

		}

		// If list/audience has no members, create one.
		if ( ! $member ) {

			try {

				// Prepare member parameters.
				$member_params = array(
					'email_address' => 'mailchimp@gravityforms.com',
					'status'        => 'subscribed',
				);

				// Add member to list/audience.
				$member = $this->api->update_list_member( $list_id, $member_params['email_address'], $member_params );

				// Delete member.
				$this->api->delete_list_member( $list_id, $member_params['email_address'] );

			} catch ( Exception $e ) {

				// Log that we could not create test member.
				$this->log_error( __METHOD__ . '(): Unable to create test audience member to retrieve marketing permissions; ' . $e->getMessage() );

				return false;

			}

		}

		// Get marketing permissions from first member.
		$permissions = $member['marketing_permissions'];

		// Loop through permissions, remove enabled flag.
		foreach ( $permissions as $i => $permission ) {
			unset( $permissions[ $i ]['enabled'] );
		}

		// Cache permissions.
		GFCache::set( $cache_key, $permissions, true, 5 * MINUTE_IN_SECONDS );

		return $permissions;

	}

	/**
	 * Determines if Mailchimp list/audience has any defined interest categories.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @return bool
	 */
	public function has_interest_categories() {

		// Get interest categories.
		$categories = $this->get_interest_categories();

		return ! empty( $categories );

	}

	/**
	 * Retrieve the enabled conditions for a feed.
	 *
	 * @since  4.6 Update to be more generic to support marketing permissions.
	 * @since  4.0
	 *
	 * @param array  $feed    The feed object.
	 * @param string $name    The feed setting to get conditions for.
	 * @param bool   $enabled Return only enabled categories. Defaults to true.
	 *
	 * @return array
	 */
	public function get_feed_setting_conditions( $feed, $name = 'interestCategory', $enabled = true ) {

		// Initialize conditions array.
		$conditions = array();

		// Loop through feed meta.
		foreach ( $feed['meta'] as $key => $value ) {

			// If this is not the setting we're looking for, skip.
			if ( 0 !== strpos( $key, $name . '_' ) ) {
				continue;
			}

			// Explode the meta key.
			$key = explode( '_', $key );

			// Add value to conditions array.
			$conditions[ $key[1] ][ $key[2] ] = $value;

		}

		// If we are only returning enabled conditions, remove disabled conditions.
		if ( $enabled ) {

			// Loop through conditions.
			foreach ( $conditions as $condition_id => $condition_meta ) {

				// If condition is enabled, skip it.
				if ( '1' == $condition_meta['enabled'] ) {
					continue;
				}

				// Remove condition.
				unset( $conditions[ $condition_id ] );

			}

		}

		return $conditions;

	}

	/**
	 * Determine if the user should be subscribed to the interest category.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @param array $category The interest category properties.
	 * @param array $form     The form currently being processed.
	 * @param array $entry    The entry currently being processed.
	 *
	 * @uses GFFormsModel::get_field()
	 * @uses GFFormsModel::is_value_match()
	 * @uses GFMailChimp::get_field_value()
	 *
	 * @return bool
	 */
	public function is_category_condition_met( $category, $form, $entry ) {

		if ( ! $category['enabled'] ) {

			$this->log_debug( __METHOD__ . '(): Interest category not enabled. Returning false.' );

			return false;

		} else if ( $category['decision'] == 'always' ) {

			$this->log_debug( __METHOD__ . '(): Interest category decision is always. Returning true.' );

			return true;

		}

		$field = GFFormsModel::get_field( $form, $category['field'] );

		if ( ! is_object( $field ) ) {

			$this->log_debug( __METHOD__ . "(): Field #{$category['field']} not found. Returning true." );

			return true;

		} else {

			$field_value    = GFFormsModel::get_lead_field_value( $entry, $field );
			$is_value_match = GFFormsModel::is_value_match( $field_value, $category['value'], $category['operator'] );

			$this->log_debug( __METHOD__ . "(): Add to interest category if field #{$category['field']} value {$category['operator']} '{$category['value']}'. Is value match? " . var_export( $is_value_match, 1 ) );

			return $is_value_match;

		}

	}


	/**
	 * Determine if the Marketing Permission should be enabled for user.
	 *
	 * @since  4.6
	 * @access public
	 *
	 * @param array $permission The Marketing Permission properties.
	 * @param array $form       The form currently being processed.
	 * @param array $entry      The entry currently being processed.
	 *
	 * @return bool
	 */
	public function is_marketing_permission_condition_met( $permission, $form, $entry ) {

		if ( ! $permission['enabled'] ) {
			$this->log_debug( __METHOD__ . '(): Marketing Permission not enabled. Returning false.' );
			return false;
		}

		// Get field.
		$field = GFFormsModel::get_field( $form, $permission['field'] );

		if ( ! is_object( $field ) ) {

			$this->log_debug( __METHOD__ . "(): Field #{$permission['field']} not found. Returning true." );
			return true;

		} else {

			$field_value    = GFFormsModel::get_lead_field_value( $entry, $field );
			$is_value_match = GFFormsModel::is_value_match( $field_value, $permission['value'], $permission['operator'] );

			$this->log_debug( __METHOD__ . "(): Enable Marketing Permission if field #{$permission['field']} value {$permission['operator']} '{$permission['value']}'. Is value match? " . var_export( $is_value_match, 1 ) );

			return $is_value_match;

		}

	}

	/**
	 * Returns the combined value of the specified Address field.
	 * Street 2 and Country are the only inputs not required by Mailchimp.
	 * If other inputs are missing Mailchimp will not store the field value, we will pass a hyphen when an input is empty.
	 * Mailchimp requires the inputs be delimited by 2 spaces.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param array  $entry    The entry currently being processed.
	 * @param string $field_id The ID of the field to retrieve the value for.
	 *
	 * @return array|null
	 */
	public function get_full_address( $entry, $field_id ) {

		// Initialize address array.
		$address = array(
			'addr1'   => str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.1' ) ) ),
			'addr2'   => str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.2' ) ) ),
			'city'    => str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.3' ) ) ),
			'state'   => str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.4' ) ) ),
			'zip'     => trim( rgar( $entry, $field_id . '.5' ) ),
			'country' => trim( rgar( $entry, $field_id . '.6' ) ),
		);

		// Get address parts.
		$address_parts = array_values( $address );

		// Remove empty address parts.
		$address_parts = array_filter( $address_parts );

		// If no address parts exist, return null.
		if ( empty( $address_parts ) ) {
			return null;
		}

		// Replace country with country code.
		if ( ! empty( $address['country'] ) ) {
			$address['country'] = GF_Fields::get( 'address' )->get_country_code( $address['country'] );
		}

		return $address;

	}

	/**
	 * Get Mailchimp merge fields for list/audience.
	 *
	 * @since  4.2.4
	 * @access public
	 *
	 * @param string $list_id List/Audience ID to get merge fields for.
	 *
	 * @uses GFMailChimp::initialize_api()
	 * @uses GF_MailChimp_API::get_list_merge_fields()
	 *
	 * @return array
	 */
	public function get_list_merge_fields( $list_id = '' ) {

		// If no list/audience ID was provided or if API cannot be initialized, return.
		if ( rgblank( $list_id ) || ! $this->initialize_api() ) {
			return array();
		}

		// If merge fields have already been retrieved, return.
		if ( isset( $this->merge_fields[ $list_id ] ) ) {
			return $this->merge_fields[ $list_id ];
		}

		try {

			// Get merge fields.
			$this->merge_fields[ $list_id ] = $this->api->get_list_merge_fields( $list_id );

		} catch ( Exception $e ) {

			// Log error.
			$this->log_error( __METHOD__ . '(): Unable to get merge fields for Mailchimp audience; ' . $e->getMessage() );

			$this->merge_fields[ $list_id ] = array();

		}

		return $this->merge_fields[ $list_id ];

	}

	/**
	 * Get specific Mailchimp merge field by tag.
	 *
	 * @since  4.2.4
	 * @access public
	 *
	 * @param string $list_id List/Audience ID to get merge fields for.
	 * @param string $tag     Merge field tag.
	 *
	 * @uses GFMailChimp::get_list_merge_fields()
	 *
	 * @return array
	 */
	public function get_list_merge_field( $list_id = '', $tag = '' ) {

		// Get the merge fields for list/audience.
		$merge_fields = $this->get_list_merge_fields( $list_id );

		// If no merge fields were provided, return.
		if ( empty( $merge_fields ) || ! isset( $merge_fields['merge_fields'] ) ) {
			return;
		}

		// Loop through merge fields.
		foreach ( $merge_fields['merge_fields'] as $merge_field ) {

			// If this is not the merge field we are looking for, skip.
			if ( $tag !== $merge_field['tag'] ) {
				continue;
			}

			return $merge_field;

		}

		return array();

	}





	// # UPGRADES ------------------------------------------------------------------------------------------------------

	/**
	 * Checks if a previous version was installed and if the feeds need migrating to the framework structure.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param string $previous_version The version number of the previously installed version.
	 */
	public function upgrade( $previous_version ) {

		// If previous version is not defined, set it to the version stored in the options table.
		if ( empty( $previous_version ) ) {
			$previous_version = get_option( 'gf_mailchimp_version' );
		}

		// Run upgrade routine checks.
		$previous_is_pre_40              = ! empty( $previous_version ) && version_compare( $previous_version, '4.0', '<' );
		$previous_is_pre_addon_framework = ! empty( $previous_version ) && version_compare( $previous_version, '3.0.dev1', '<' );

		if ( $previous_is_pre_addon_framework ) {
			$this->upgrade_to_addon_framework();
		}

		if ( $previous_is_pre_40 ) {
			$this->convert_groups_to_categories();
		}

	}

	/**
	 * Convert groups in feed meta to interest categories.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @uses GFAddOn::log_error()
	 * @uses GFAddOn::get_plugin_settings()
	 * @uses GFAddOn::update_plugin_settings()
	 * @uses GFCache::delete()
	 * @uses GFFeedAddOn::get_feeds()
	 * @uses GFFeedAddOn::update_feed_meta()
	 * @uses GFMailChimp::initialize_api()
	 * @uses GF_MailChimp_API::get_interest_category_interests()
	 * @uses GF_MailChimp_API::get_list_interest_categories()
	 */
	public function convert_groups_to_categories() {

		// If API cannot be initialized, exit.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to convert Mailchimp groups to interest categories because API could not be initialized.' );
			return;
		}

		// Get plugin settings.
		$settings = $this->get_plugin_settings();

		// Get Mailchimp feeds.
		$feeds = $this->get_feeds();

		$list_interest_categories    = array();
		$interest_category_interests = array();

		// Loop through Mailchimp feeds.
		foreach ( $feeds as $feed ) {

			// If no list/audience ID is set, skip it.
			if ( ! rgars( $feed, 'meta/mailchimpList' ) ) {
				continue;
			}

			// Initialize categories array.
			$categories = array();

			try {

				$list_id = $feed['meta']['mailchimpList'];

				if ( ! isset( $list_interest_categories[ $list_id ] ) ) {
					// Get interest categories for list/audience.
					$list_interest_categories[ $list_id ] = $this->api->get_list_interest_categories( $list_id );
				}

				$interest_categories = rgar( $list_interest_categories, $list_id, array() );

			} catch ( Exception $e ) {

				// Log that we could not get interest categories.
				$this->log_error( __METHOD__ . '(): Unable to updated feed #' . $feed['id'] . ' because interest categories could not be retrieved for Mailchimp audience ' . $feed['meta']['mailchimpList'] );

				continue;

			}

			// Loop through interest categories.
			foreach ( $interest_categories as $interest_category ) {

				$category_id = $interest_category['id'];

				if ( ! isset( $interest_category_interests[ $category_id ] ) ) {
					// Get interests for interest category.
					$interest_category_interests[ $category_id ] = $this->api->get_interest_category_interests( $list_id, $category_id );
				}

				$interests = rgar( $list_interest_categories, $category_id, array() );

				// Loop through interests.
				foreach ( $interests as $interest ) {

					// Add interest to categories array using sanitized name.
					$categories[ $interest['id'] ] = sanitize_title_with_dashes( $interest['name'] );

				}

			}

			// Loop through feed meta.
			foreach ( $feed['meta'] as $key => $value ) {

				// If this is not a Mailchimp group key, skip it.
				if ( 0 !== strpos( $key, 'mc_group_' ) ) {
					continue;
				}

				// Explode meta key.
				$exploded_key = explode( '_', $key );

				// Get Mailchimp group key.
				$mc_key = $exploded_key[0] . '_' . $exploded_key[1] . '_' . $exploded_key[2];
				unset( $exploded_key[0], $exploded_key[1], $exploded_key[2] );

				// Get meta key without group name.
				$meta_key = implode( '_', $exploded_key );

				// Get settings key for Mailchimp group key.
				$settings_key = array_search( $mc_key, $settings );

				// Get sanitized group name.
				$sanitized_group_name = substr( $settings_key, strrpos( $settings_key, '_' ) + 1 );

				// Get new category ID.
				$category_id = array_search( $sanitized_group_name, $categories );

				// If category ID exists, migrate group setting.
				if ( $category_id ) {
					$feed['meta'][ 'interestCategory_' . $category_id . '_' . $meta_key ] = $value;
					unset( $feed['meta'][ $key ] );
				}

			}

			// Save feed.
			$this->update_feed_meta( $feed['id'], $feed['meta'] );

		}

		// Reset plugin settings to just API key.
		$settings = array( 'apiKey' => $settings['apiKey'] );

		// Save plugin settings.
		$this->update_plugin_settings( $settings );

		// Delete cache.
		GFCache::delete( 'mailchimp_plugin_settings' );

	}

	/**
	 * Upgrade versions of Mailchimp Add-On before 3.0 to the Add-On Framework.
	 *
	 * @since  4.0
	 * @access public
	 */
	public function upgrade_to_addon_framework() {

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
					$mailchimp_groupings = $this->get_interest_categories( $list_id );

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

		// Delete old options.
		delete_option( 'gf_mailchimp_settings' );
		delete_option( 'gf_mailchimp_version' );

	}

	/**
	 * Migrate the delayed payment setting for the PayPal add-on integration.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param string $old_delay_setting_name Old PayPal delay settings name.
	 *
	 * @uses GFAddon::log_debug()
	 * @uses GFFeedAddOn::get_feeds_by_slug()
	 * @uses GFFeedAddOn::update_feed_meta()
	 * @uses GFMailChimp::get_old_paypal_feeds()
	 * @uses wpdb::update()
	 */
	public function update_paypal_delay_settings( $old_delay_setting_name ) {

		global $wpdb;

		// Log that we are checking for delay settings for migration.
		$this->log_debug( __METHOD__ . '(): Checking to see if there are any delay settings that need to be migrated for PayPal Standard.' );

		$new_delay_setting_name = 'delay_' . $this->_slug;

		// Get paypal feeds from old table.
		$paypal_feeds_old = $this->get_old_paypal_feeds();

		// Loop through feeds and look for delay setting and create duplicate with new delay setting for the framework version of PayPal Standard
		if ( ! empty( $paypal_feeds_old ) ) {
			$this->log_debug( __METHOD__ . '(): Old feeds found for ' . $this->_slug . ' - copying over delay settings.' );
			foreach ( $paypal_feeds_old as $old_feed ) {
				$meta = $old_feed['meta'];
				if ( ! rgempty( $old_delay_setting_name, $meta ) ) {
					$meta[ $new_delay_setting_name ] = $meta[ $old_delay_setting_name ];
					//Update paypal meta to have new setting
					$meta = maybe_serialize( $meta );
					$wpdb->update( "{$wpdb->prefix}rg_paypal", array( 'meta' => $meta ), array( 'id' => $old_feed['id'] ), array( '%s' ), array( '%d' ) );
				}
			}
		}

		// Get paypal feeds from new framework table.
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
	 * @since  3.0
	 * @access public
	 *
	 * @uses GFAddOn::log_debug()
	 * @uses GFAddOn::table_exists()
	 * @uses GFFormsModel::get_form_table_name()
	 * @uses wpdb::get_results()
	 *
	 * @return bool|array
	 */
	public function get_old_paypal_feeds() {

		global $wpdb;

		// Get old PayPal Add-On table name.
		$table_name = $wpdb->prefix . 'rg_paypal';

		// If the table does not exist, exit.
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

		$count = count( $results );

		$this->log_debug( __METHOD__ . "(): count: {$count}" );

		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;

	}

	/**
	 * Retrieve any old feeds which need migrating to the Feed Add-On Framework.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @uses GFAddOn::table_exists()
	 * @uses GFFormsModel::get_form_table_name()
	 * @uses wpdb::get_results()
	 *
	 * @return bool|array
	 */
	public function get_old_feeds() {

		global $wpdb;

		// Get pre-3.0 table name.
		$table_name = $wpdb->prefix . 'rg_mailchimp';

		// If the table does not exist, exit.
		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		$form_table_name = GFFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
					FROM $table_name s
					INNER JOIN $form_table_name f ON s.form_id = f.id";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$count = count( $results );
		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;

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

}
