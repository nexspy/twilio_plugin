<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://myprojectdev.com
 * @since      1.0.0
 *
 * @package    Twilio_donate
 * @subpackage Twilio_donate/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Twilio_donate
 * @subpackage Twilio_donate/admin
 * @author     My Project <myproject@gmail.com>
 */
class Twilio_donate_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $twilio_donate    The ID of this plugin.
	 */
	private $twilio_donate;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $twilio_donate       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $twilio_donate, $version ) {

		$this->twilio_donate = $twilio_donate;
		$this->version = $version;

        add_action('admin_menu', array( $this, 'addPluginAdminMenu' ), 9);
        add_action('admin_init', array( $this, 'registerAndBuildFields' ));

    }

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * An instance of this class should be passed to the run() function
		 * defined in Twilio_donate_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Twilio_donate_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

        wp_enqueue_style( $this->twilio_donate, plugin_dir_url( __FILE__ ) . 'css/simplePagination.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->twilio_donate . '-style', plugin_dir_url( __FILE__ ) . 'css/twilio_donate-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * An instance of this class should be passed to the run() function
		 * defined in Twilio_donate_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Twilio_donate_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

        wp_enqueue_script( $this->twilio_donate, plugin_dir_url( __FILE__ ) . 'js/jquery.simplePagination.js', array( 'jquery' ), $this->version, true );
		wp_enqueue_script( $this->twilio_donate . '-script', plugin_dir_url( __FILE__ ) . 'js/twilio_donate-admin.js', array( 'jquery' ), $this->version, true );

	}

    public function addPluginAdminMenu() {
        //add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
        add_menu_page(  $this->twilio_donate, 'Twilio Integration', 'administrator', $this->twilio_donate, array( $this, 'displayPluginAdminDashboard' ), 'dashicons-chart-area', 26 );

        //add_submenu_page( '$parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
        add_submenu_page( $this->twilio_donate, 'Twilio Testing Page', 'Logs', 'administrator', $this->twilio_donate.'-settings', array( $this, 'displayPluginAdminSettings' ));

        add_submenu_page( $this->twilio_donate, 'Twilio Remarking Page', 'Remarketing Logs', 'administrator', $this->twilio_donate.'-remarketing', array( $this, 'displayPluginAdminRemarketing' ));
    }

    public function displayPluginAdminDashboard() {
	    require_once 'partials/'.$this->twilio_donate.'-admin-display.php';
    }

    public function displayPluginAdminSettings() {
        // set this var to be used in the settings-display view
        $active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';
        if(isset($_GET['error_message'])){
            add_action('admin_notices', array($this,'pluginNameSettingsMessages'));
            do_action( 'admin_notices', $_GET['error_message'] );
        }

        // get logs from filters
        $filters = [];
        $filters['from'] = (isset($_GET['from'])) ? $_GET['from'] : '';
        $filters['to'] = (isset($_GET['to'])) ? $_GET['to'] : '';
        if (empty($filters['from'])) {
            $filters['from'] = date('Y-m-d');
        }
        if (empty($filters['to'])) {
            // set $to as next day of $from
            $datetime = new DateTime($filters['from']);
            $datetime->modify('+1 day');
            $filters['to'] = $datetime->format('Y-m-d');
        }
        $logs = apply_filters( 'twilio_sms_logs', $filters );

        require_once 'partials/'.$this->twilio_donate.'-admin-settings-display.php';

        $link = get_admin_url();
        $default_from = $filters['from'];
        echo '<a href="' . $link . 'admin.php?page=twilio_donate-settings" id="link-twilio-settings" style="display: none;">Reload</a>';
        echo '<input id="txt-from-date" type="text" placeholder="Date (eg. 2021-01-01)" value="' . $default_from . '" />';
        echo '<button id="btn-apply-date">Apply</button>';

        if (!empty($logs)) {
            echo '<div class="pagination-page"></div>';
            echo '<div id="tbl-sms">';
            foreach ($logs as $log) {
                echo '<div class="sms">';
                echo ' <div>' . $log->sid . '</div>';
                echo ' <div>' . $log->from . '</div>';
                echo ' <div>' . $log->to . '</div>';
                echo ' <div>' . $log->date_sent . '</div>';
                echo ' <div>' . $log->message . '</div>';
                echo ' <div class="' . $log->status . '">' . $log->status . '</div>';
                echo ' <div class="' . $log->direciton . '">' . $log->direciton . '</div>';
                echo ' <div>' . $log->price . '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '<div class="pagination-page"></div>';
        } else {
            echo '<p>No logs found for the date' . $filters['from'] . '.</p>';
        }
    }

    public function displayPluginAdminRemarketing() {
        require_once 'partials/'.$this->twilio_donate.'-admin-settings-remarketing.php';

        $remarketing_time = get_option('twilio_donate_remarketing_time');

        $time = date('Y-m-d', strtotime($remarketing_time));

        $filters = array();

        $logs = apply_filters( 'twilio_sms_remarketing_logs', $filters );

        echo "Remarketing Date : " . $time . "<br>";
        echo "Remarketing time : " . $remarketing_time . "<br>";

        if (!empty($logs)) {
            echo '<div class="pagination-page"></div>';
            echo '<div id="tbl-sms">';
            foreach ($logs as $log) {
              $hasPickupDate = (isset($log->pickup_date) && !empty($log->pickup_date));
              $pickupdate_class = ($hasPickupDate) ? 'yes-pickupdate' : 'no-pickupdate';
              $pickupdate_msg = ($hasPickupDate) ? $log->pickup_date : 'No data available';
                echo '<div class="sms ' . $pickupdate_class . '">';
                echo ' <div>User ID : ' . $log->user_id . '</div>';
                echo ' <div>Order ID : ' . $log->last_order_id . '</div>';
                echo ' <div>Display Name : ' . $log->displayname . '</div>';
                echo ' <div>Charity Name : ' . $log->charity_name . '</div>';
                echo ' <div>Pickup Date : ' . $pickupdate_msg . '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '<div class="pagination-page"></div>';
        } else {
            echo '<p>No logs found.</p>';
        }
    }

    public function pluginNameSettingsMessages($error_message){
        switch ($error_message) {
            case '1':
                $message = __( 'There was an error adding this setting. Please try again.  If this persists, shoot us an email.', 'my-text-domain' );
                $err_code = esc_attr( 'twilio_donate_example_setting' );
                $setting_field = 'twilio_donate_example_setting';
                break;
        }
        $type = 'error';
        add_settings_error(
            $setting_field,
            $err_code,
            $message,
            $type
        );
    }

    public function registerAndBuildFields() {
        /**
         * First, we add_settings_section. This is necessary since all future settings must belong to one.
         * Second, add_settings_field
         * Third, register_setting
         */
        add_settings_section(
        // ID used to identify this section and with which to register options
            'twilio_donate_general_section',
            // Title to be displayed on the administration page
            '',
            // Callback used to render the description of the section
            array( $this, 'twilio_donate_display_general_account' ),
            // Page on which to add this section of options
            'twilio_donate_general_settings'
        );

        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'   => 'text',
            'id'    => 'twilio_donate_account_sid',
            'name'      => 'twilio_donate_account_sid',
            'required' => 'true',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_account_sid',
            'Account SID',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );
        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'   => 'text',
            'id'    => 'twilio_donate_auth_token',
            'name'      => 'twilio_donate_auth_token',
            'required' => 'true',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_auth_token',
            'Auth Token',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );
        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'   => 'text',
            'id'    => 'twilio_donate_twilio_number',
            'name'      => 'twilio_donate_twilio_number',
            'required' => 'true',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_twilio_number',
            'Twilio Number',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );
        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'   => 'text',
            'id'    => 'twilio_donate_pickup_time',
            'name'      => 'twilio_donate_pickup_time',
            'required' => 'true',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_pickup_time',
            'Pick Up Time',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );
        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'      => 'checkbox',
            'id'    => 'twilio_donate_dev_mode_status',
            'name'      => 'twilio_donate_dev_mode_status',
            'required' => 'false',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_dev_mode_status',
            'Enable Dev Mode',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );
        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'   => 'text',
            'id'    => 'twilio_donate_allowed_numbers',
            'name'      => 'twilio_donate_allowed_numbers',
            'required' => 'true',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_allowed_numbers',
            'Allowed Phone Numbers (in dev mode)',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );
        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'      => 'checkbox',
            'id'    => 'twilio_donate_twilio_status',
            'name'      => 'twilio_donate_twilio_status',
            'required' => 'false',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_twilio_status',
            'Enable SMS',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );
        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'   => 'text',
            'id'    => 'twilio_donate_remarketing_time',
            'name'      => 'twilio_donate_remarketing_time',
            'required' => 'false',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_remarketing_time',
            'Remarketing time (eg. -3 months)',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );
        unset($args);
        $args = array (
            'type'      => 'input',
            'subtype'   => 'text',
            'id'    => 'twilio_donate_rest_api_token',
            'name'      => 'twilio_donate_rest_api_token',
            'required' => 'false',
            'get_options_list' => '',
            'value_type'=>'normal',
            'wp_data' => 'option'
        );
        add_settings_field(
            'twilio_donate_rest_api_token',
            'Rest API Token',
            array( $this, 'twilio_donate_render_settings_field' ),
            'twilio_donate_general_settings',
            'twilio_donate_general_section',
            $args
        );


        // store fields
        $fields = ['twilio_donate_account_sid', 'twilio_donate_auth_token', 'twilio_donate_twilio_number', 'twilio_donate_pickup_time',
            'twilio_donate_dev_mode_status', 'twilio_donate_allowed_numbers', 'twilio_donate_twilio_status', 'twilio_donate_remarketing_time',
            'twilio_donate_rest_api_token'];

        foreach ($fields as $field) {
            register_setting(
                'twilio_donate_general_settings',
                $field
            );
        }

    }

    public function twilio_donate_display_general_account() {
        echo '<p>These settings apply to all twilio functionality.</p>';
    }

    public function twilio_donate_render_settings_field($args) {
        /* EXAMPLE INPUT
                  'type'      => 'input',
                  'subtype'   => '',
                  'id'    => $this->twilio_donate.'_example_setting',
                  'name'      => $this->twilio_donate.'_example_setting',
                  'required' => 'required="required"',
                  'get_option_list' => "",
                    'value_type' = serialized OR normal,
        'wp_data'=>(option or post_meta),
        'post_id' =>
        */
        if($args['wp_data'] == 'option'){
            $wp_data_value = get_option($args['name']);
        } elseif($args['wp_data'] == 'post_meta'){
            $wp_data_value = get_post_meta($args['post_id'], $args['name'], true );
        }

        switch ($args['type']) {

            case 'input':
                $value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
                if($args['subtype'] != 'checkbox'){
                    $prependStart = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
                    $prependEnd = (isset($args['prepend_value'])) ? '</div>' : '';
                    $step = (isset($args['step'])) ? 'step="'.$args['step'].'"' : '';
                    $min = (isset($args['min'])) ? 'min="'.$args['min'].'"' : '';
                    $max = (isset($args['max'])) ? 'max="'.$args['max'].'"' : '';
                    if(isset($args['disabled'])){
                        // hide the actual input bc if it was just a disabled input the informaiton saved in the database would be wrong - bc it would pass empty values and wipe the actual information
                        echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'_disabled" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="'.$args['id'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
                    } else {
                        echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
                    }
                    /*<input required="required" '.$disabled.' type="number" step="any" id="'.$this->twilio_donate.'_cost2" name="'.$this->twilio_donate.'_cost2" value="' . esc_attr( $cost ) . '" size="25" /><input type="hidden" id="'.$this->twilio_donate.'_cost" step="any" name="'.$this->twilio_donate.'_cost" value="' . esc_attr( $cost ) . '" />*/

                } else {
                    $checked = ($value) ? 'checked' : '';
                    echo '<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" name="'.$args['name'].'" size="40" value="1" '.$checked.' />';
                }
                break;
//            case 'checkbox':
//                echo '<input type="checkbox" id="subscribeNews" name="subscribe" value="newsletter">';
//                break;
            default:
                # code...
                break;
        }
    }

}
