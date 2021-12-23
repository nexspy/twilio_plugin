<?php

/**
 * * Twilio Features for Woocommerce
 * ? All actions related to twilio and woocommerce
 *
 * Settings comes from the plugin : Twilio Donate
 */


/**
 * Class : Provide data saved in twilio plugin backend settings form
 */
class TwilioWpPlugin {

    static public function get_information() {
        $account_sid = get_option('twilio_donate_account_sid');
        $auth_token = get_option('twilio_donate_auth_token');
        $twilio_number = get_option('twilio_donate_twilio_number');

        return array(
            // Your Account SID and Auth Token from twilio.com/console
            'account_sid' => $account_sid,
            'auth_token' => $auth_token,
            // A Twilio number you own with SMS capabilities
            'twilio_number' => $twilio_number,
        );
    }

    /**
     * Check if developer mode is enabled
     * @return bool
     */
    static public function get_status() {
        $status = get_option('twilio_donate_twilio_status');
        return ($status == "1");
        
    }

    /**
     * Get : token for access to api
     * @param string $token
     */
    static public function get_token() {
        $token = get_option('twilio_donate_rest_api_token');
        return $token;
    }

    /**
     * Get : remarketing time
     *  - expected return example : "-3 months" ( later will be passed to strtotime )
     * @return string $time
     */
    static public function get_remarketing_time() {
        $time = get_option('twilio_donate_remarketing_time');
        return $time;
    }
}

/**
 * Class : Provide all features for interacting with WooCOmmerce data
 *  - get, create, cancel orders
 *  - get customers
 */
class TwilioCommerce {

    /**
     * Get : orders from last 3 month from today
     */
    static public function get_orders($time) {
        global $wpdb;

        $date = date("Y-m-d", strtotime($time));

        $post_status = implode("','", array('wc-processing', 'wc-completed'));

        $result = $wpdb->get_results(
            "SELECT * FROM $wpdb->posts
                WHERE post_type = 'shop_order'
                AND post_status IN ('{$post_status}')
                AND post_date BETWEEN '{$date}  00:00:00' AND '{$date} 23:59:59'
                ORDER BY post_date DESC "
        );

        return $result;
    }
}

/**
 * Get : Twilio Information
 * @return array $config
 */
function get_twilio_information_custom()
{
    $account_sid = get_option('twilio_donate_account_sid');
    $auth_token = get_option('twilio_donate_auth_token');
    $twilio_number = get_option('twilio_donate_twilio_number');

    return array(
        // Your Account SID and Auth Token from twilio.com/console
        'account_sid' => $account_sid,
        'auth_token' => $auth_token,
        // A Twilio number you own with SMS capabilities
        'twilio_number' => $twilio_number,
    );
}

/**
 * Check if developer mode is enabled
 * @return bool
 */
function get_twilio_integration_status()
{
    $status = get_option('twilio_donate_twilio_status');
    return ($status == "1");
}

/**
 * Get : list of allowed phone numbers in developer mode
 * @param boolean $firstonly
 * @return array $allowed_numbers
 */
function get_twilio_allowed_number_in_dev($firstonly = false)
{
    $allowed_numbers = [];
    $numbers = get_option('twilio_donate_allowed_numbers');

    if (!empty($numbers)) {
        $numbers = str_replace(' ', '', $numbers);
        $numbers = str_replace('-', '', $numbers);
        $allowed_numbers = explode(',', $numbers);
    }

    if ($firstonly) {
        return (isset($allowed_numbers[0])) ? $allowed_numbers[0] : '';
    }

    return $allowed_numbers;
}

/**
 * Check : if given number is allowed for sending sms
 *  - for now, all numbers are allowed, the dev mode will be checked on sending sms only
 *  - dev mode is off : always send true (allowing all numbers to work)
 *  - dev mod is on : only allow numbers input in the plugin
 * @param string $number
 * @return bool $allowed
 */
function check_twilio_phone_is_allowed($number)
{
    $allowed = true;
    //    $dev_mode = get_twilio_integration_dev_mode_status();
    //    if ($dev_mode) {
    //        $allowed_numbers = get_twilio_allowed_number_in_dev();
    //        $allowed = (in_array($number, $allowed_numbers));
    //    }
    return $allowed;
}

/**
 * Get : timezone to use while comparing dates/times
 * @return string $timezone
 */
function get_twilio_timezone()
{
    $timezone = 'America/New_York';
    return $timezone;
}

/**
 * Check : if twilio integration developer mode is enabled
 * @return bool $status
 */
function get_twilio_integration_dev_mode_status()
{
    $status = get_option('twilio_donate_dev_mode_status');
    return ($status == "1");
}

/**
 * Get : Pickup time
 * @return string $time
 */
function get_twilio_pickup_time()
{
    $time = get_option('twilio_donate_pickup_time');
    return $time;
}

/**
 * Action : Send SMS notification to customer about their scheduled pickup
 * - sms is sent once pickup order is placed (in thank you page)
 * - Scheduled Pickup Confirmation Text
 * @param int $order_id
 */
function action_woocommerce_send_pickup_reminder($order_id)
{

    // read order
    $order = new WC_Order($order_id);
    $order_data = $order->get_data(); // The Order data

    // get customer phone number and schedules
    $receiver_number = (isset($order_data['billing']['phone'])) ? $order_data['billing']['phone'] : '';
    $send_sms = get_post_meta($order_id, '_billing_send_sms', true);

    // check if mobile number is allowed
    $allowed = check_twilio_phone_is_allowed($receiver_number);

    // send sms only if checkbox is checked
    if ($send_sms == "1" && $allowed) {
        $pickup_date = get_post_meta($order_id, '_billing_pickup_date', true);
        $pickup_time = get_twilio_pickup_time();

        // these fields must exists (number to send, date)
        if (!empty($receiver_number) && !empty($pickup_date)) {
            // get charity name using order id
            // $charity_name = get_post_meta( $order_id, '_billing_charity', true );

            // Opt-in Confirmation Text only for new customer (with no previous order)
            $customer = get_twilio_customer_by_phone($receiver_number);
            $wc_customer = new WC_Customer($customer->ID);
            $last_order = $wc_customer->get_last_order();
            if ($last_order) {
                $message = "Welcome to the pickup program for gently used clothing and small household items! We’ll alert you via text messages on your phone with details about your upcoming pickups. Please reply ‘STOP’ to stop receiving text messages.";
                send_sms_scheduled_pickup($receiver_number, $message);
            }

            // Scheduled Pickup Confirmation Text
            // @fix : added two empty space character for two part sms ( pick_date length is 10 )
            $message = "Thank you for scheduling a pickup! Your pickup date is " . $pickup_date . ". Please leave your gently used clothing and small household items outside your front   door by " . $pickup_time . ".";

            // send SMS, with some error handling
            send_sms_scheduled_pickup($receiver_number, $message);
        }
    }
}

/**
 * Action : Send SMS on the pickup order has been cancelled
 * - send on order cancel
 * - Scheduled Pickup Cancelled Text
 * @param int $order_id
 */
function action_woocommerce_cancel_pickup_sms($order_id)
{
    // read order
    $order = new WC_Order($order_id);
    $order_data = $order->get_data(); // The Order data

    // get customer phone number and schedules
    $receiver_number = (isset($order_data['billing']['phone'])) ? $order_data['billing']['phone'] : '';
    $pickup_date = get_post_meta($order_id, '_billing_pickup_date', true);

    // check if mobile number is allowed
    $allowed = check_twilio_phone_is_allowed($receiver_number);

    // these fields must exists (number to send, date)
    if (!empty($receiver_number) && !empty($pickup_date) && $allowed) {
        // Scheduled Pickup Cancelled Text
        $message = "Your pickup has been cancelled. Please reschedule your pickup for the day that works best for you.";

        // send SMS, with some error handling
        send_sms_scheduled_pickup($receiver_number, $message);
    }
}

/**
 * Action : Send SMS on the pickup order has been completed
 * - order was completed
 * - Pickup successful
 * @param int $order_id
 */
function action_woocommerce_completed_pickup_sms($order_id)
{
    // read order
    $order = new WC_Order($order_id);
    $order_data = $order->get_data(); // The Order data

    // get customer phone number and schedules
    $receiver_number = (isset($order_data['billing']['phone'])) ? $order_data['billing']['phone'] : '';

    // check if mobile number is allowed
    $allowed = check_twilio_phone_is_allowed($receiver_number);

    // these fields must exists (number to send, date)
    if (!empty($receiver_number) && $allowed) {
        // Scheduled Pickup Cancelled Text
        $message = "Your pickup was completed successfully. We sincerely appreciate the support and hope to stop by again soon!";

        // send SMS, with some error handling
        send_sms_scheduled_pickup($receiver_number, $message);
    }
}

/**
 * Action : Send SMS when the pickup status fails
 * - Scheduled Pickup Failed Text
 * @param int $order_id
 */
function action_woocommerce_pickup_status_failed($order_id)
{
    // read order
    $order = new WC_Order($order_id);
    $order_data = $order->get_data(); // The Order data

    // get customer phone number and schedules
    $receiver_number = (isset($order_data['billing']['phone'])) ? $order_data['billing']['phone'] : '';

    // these fields must exists (number to send, date)
    if (!empty($receiver_number)) {

        // check if mobile number is allowed
        $allowed = check_twilio_phone_is_allowed($receiver_number);

        if ($allowed) {
            // Scheduled Pickup Failed Text
            $message = "Oh no! We were unable to complete your pickup. Please reschedule your pickup for the day that works best for you.";

            // send SMS, with some error handling
            send_sms_scheduled_pickup($receiver_number, $message);
        }
    }
}

/**
 * * Send SMS notification
 * - simple function to send sms
 * @param string $receiver_number
 * @param string $message
 */
function send_sms_scheduled_pickup($receiver_number, $message)
{

    // need to enable the plugin and checkbox for sending sms
    $status = get_twilio_integration_status();
    if ($status == false) {
        return;
    }

    // check dev mode and send to dev number
    $dev_mode = get_twilio_integration_dev_mode_status();
    $first_dev_nubmer = get_twilio_allowed_number_in_dev(true);
    if ($dev_mode && !empty($first_dev_nubmer)) {
        $receiver_number = $first_dev_nubmer;
    }

    // load twilio libraries
    require_once(get_stylesheet_directory() . '/vendor/autoload.php');

    // get information of twilio SDK
    $twilio_info = get_twilio_information_custom();

    // Your Account SID and Auth Token from twilio.com/console
    $account_sid = $twilio_info['account_sid'];
    $auth_token = $twilio_info['auth_token'];
    // A Twilio number you own with SMS capabilities
    $twilio_number = $twilio_info['twilio_number'];

    if (!empty($receiver_number) && !empty($message)) {
        $client = new Twilio\Rest\Client($account_sid, $auth_token);
        $client->messages->create(
            // Where to send a text message (your cell phone?)
            $receiver_number,
            array(
                'from' => $twilio_number,
                'body' => $message,
            )
        );
    }
}

/**
 * * Route : webhook : handle sms reply from Twilio when user replies 'yes'
 * - handle 'yes', pickup request
 */
function handle_twilio_replysms()
{
    // response as xml
    header("content-type: text/xml");

    // load libraries dependencies
    require_once(get_stylesheet_directory() . '/vendor/autoload.php');

    // Incoming data
    $number = (isset($_POST['From'])) ? $_POST['From'] : '';
    $body = (isset($_POST['Body'])) ? $_POST['Body'] : '';

    $body = strtolower($body);

    // check if mobile number is allowed
    $allowed = check_twilio_phone_is_allowed($number);

    // check for 'yes' reply
    if ($body == 'yes' && $allowed) {

        // create order
        webhook_create_order($number);
    }
    // check with 'cancel' reply
    else if ($body == 'cancel' && $allowed) {

        // cancel order using phone number in billing information
        webhook_cancel_order($number);
        
    } else {

        $return = array(
            'message' => 'Route not found',
            'success' => false
        );

        wp_send_json($return);

        return;
    }
}


/**
 * Create order for customer whose number is given
 *  - finds customer, it's last order, and create pickup order according to
 *  - last donation information from last order
 * @param int $number
 */
function webhook_create_order($number) {
    // schedule an upcoming pickup for previous customer
    // create new order (pickup) for given customer
    $pickup_date = create_new_pickup_request_replysms($number);
    $pickup_time = get_twilio_pickup_time();

    if (!empty($pickup_date)) {
        // prepare message to send back to twilio
        $response = new Twilio\TwiML\MessagingResponse();
        $response->message(
            "Thank you for scheduling a pickup! Your pickup date is " . $pickup_date . ". Please leave your gently used clothing and small household items outside your front door by " . $pickup_time . ".",
        );

        echo $response;
        die;
    } else {
        // prepare message to send back to twilio
        $response = new Twilio\TwiML\MessagingResponse();
        $response->message(
            "Oh no! That zip code does not have the pickup dates available. Please visit our website and choose the time that works best for you!"
        );

        echo $response;
        die;
    }
}

/**
 * Cancel order for given phone number
 * @param int $number
 * @param bool $update
 */
function webhook_cancel_order($number, $update=false) {
    $order_id = 0;
    $order_cancelled = false;
    // sms notification is disabled, because sms is sent when order is cancelled itself
    // so, sending sms here will be duplicate sms
    $send_sms_notification = false;

    // find last order by phone number 
    $order_id = get_order_id_by_number($number);

    if ($order_id) {
        // we will try to find order using billing phone directly
        // cancel order
        $order = new WC_Order($order_id);

        if (!empty($order)) {
            $status = $order->get_status();
            // dont sent sms if already cancelled
            if ($status != 'cancelled') {
                if (!$update) {
                    $order->update_status( 'cancelled' );
                }
                $order_cancelled = true;
            }
        }
    }

    if ($send_sms_notification) {
        if ($order_cancelled && $order_id) {
            // prepare message to send back to twilio
            $response = new Twilio\TwiML\MessagingResponse();
            $response->message(
                "Your pickup has been cancelled. Please reschedule your pickup for the day that works best for you.",
            );
    
            echo $response;
            die;
        }  else {
            // prepare message to send back to twilio
            $response = new Twilio\TwiML\MessagingResponse();
            $response->message(
                "Oh no! No pickup was found. Please visit the website at donatestuff.com to cancel your pickup."
            );
    
            echo $response;
            die;
        }
    }
}

/**
 * Get : customer by using its phone (also tries without +1)
 * @param string $number
 * @return WC_Customer $customer
 */
function get_twilio_customer_by_phone($number)
{
    $customer = get_twilio_customer_by_phone_number($number);

    // if no customer found by this number, we try removing the "+1"
    if (!$customer) {
        // try checking if user number was saved with "+1"
        $number = str_replace('+1', '', $number);
        $customer = get_twilio_customer_by_phone_number($number);
    }

    return $customer;
}

/**
 * Get : customer by using its phone value
 * @param string $number
 * @return WC_Customer $customer
 */
function get_twilio_customer_by_phone_number($number) {
    $args = array('meta_key' => 'billing_phone', 'meta_value' => $number);
    $user_query = new WP_User_Query($args);
    $results = $user_query->get_results();
    $customer = (!empty($results)) ? $results[0] : false;
    return $customer;
}

/**
 * Create Pickup order
 *  - used in twilio "yes" response from sms
 *  - returns empty if fail to create order
 * @param string $number
 * @return string $pickup_date
 */
function create_new_pickup_request_replysms($number)
{
    $pickup_date = '';

    // find user by phone number
    $customer = get_twilio_customer_by_phone($number);

    if ($customer) {
        $user_id = $customer->ID;

        // get last charity done by the customer and the time of schedule
        $wc_customer = new WC_Customer($user_id);
        $last_order = $wc_customer->get_last_order();

        if ($last_order) {

            $order_id = $last_order->get_id();

            $address = $last_order->get_address();

            $products = $last_order->get_items();

            $product_id = 0;
            foreach ($products as $product) {
                $product_id = $product->get_product_id();
            }

            // pickup date
            $tomorrow = date('Ymd', strtotime("tomorrow"));
            // new york time
            $today = date('Y-m-d H:i:s');
            $dt = new DateTime($today);
            $dt->setTimezone(new DateTimeZone(get_twilio_timezone()));
            $current_hour = (int) $dt->format('G');

            $pickup_date_str = get_product_next_pickup_date($product_id, $user_id);
            $pickup_date = strtotime($pickup_date_str);

            // avoid pickup date that is tomorrow after 2pm (14 = 12 + 2)
            if ($tomorrow == $pickup_date_str && $current_hour >= 14) {
                $message = "Your pickup tomorrow cannot be completed please check our site and schedule it for another day.";
                send_sms_scheduled_pickup($number, $message);
                $pickup_date = '';
            } else {
                if ($order_id && $product_id && !empty($pickup_date_str)) {
                    $product = wc_get_product($product_id);

                    // create the order
                    $order = wc_create_order();

                    // add products
                    $order->add_product($product, 1);

                    $order->set_address($address, 'billing');
                    $order->calculate_totals();
                    $order->update_status("processing", 'order created from sms reply by user', TRUE);
                    $order->set_date_created($pickup_date);
                    $order->save();
                }
            }
        }
    }

    if (!empty($pickup_date)) {
        return date("Y-m-d", $pickup_date);
    }

    return $pickup_date;
}

/**
 * Route : reminder of tomorrow orders run via external cron
 */
function pickup_custom_cron_sms_tomorrow_reminder_rest()
{
    global $wp_query;
    $token = TwilioWpPlugin::get_token();

    if (isset($_GET['apitoken']) && $_GET['apitoken'] == $token) {
        pickup_custom_cron_sms_tomorrow_reminder();
    } else {
        $wp_query->set_404();
        status_header(404);
        get_template_part(404);
        exit();
    }
}

/**
 * Route : remarketing run via external cron
 */
function pickup_custom_cron_sms_three_months_rest()
{
    global $wp_query;
    $token = TwilioWpPlugin::get_token();

    if (isset($_GET['apitoken']) && $_GET['apitoken'] == $token) {
        pickup_custom_cron_sms_three_months();
    } else {
        $wp_query->set_404();
        status_header(404);
        get_template_part(404);
        exit();
    }
}

/**
 * Cron job : send reminder sms to all order of tomorrow
 *  - Scheduled Pickup Reminder Text
 */
function pickup_custom_cron_sms_tomorrow_reminder()
{

    // find all orders scheduled for tomorrow
    $orders = get_orders_from_time_filter("+1 day");

    // send all orders reminder sms
    foreach ($orders as $item) {
        $order_id = $item->ID;
        $order = new WC_Order($order_id);
        $order_data = $order->get_data(); // The Order data

        // get customer phone number and schedules
        $receiver_number = (isset($order_data['billing']['phone'])) ? $order_data['billing']['phone'] : '';

        // check if mobile number is allowed
        $allowed = check_twilio_phone_is_allowed($receiver_number);

        if ($allowed) {
            $pickup_time = get_twilio_pickup_time();

            if ($receiver_number) {
                // Scheduled Pickup Reminder Text
                // $message = "Your pickup is tomorrow! Please place the gently used goods outside your front door with a clearly visible sign by " . $pickup_time . ". Our friendly driver will be coming by to collect your donation.";
                $message = "Here is a friendly reminder that Your donation pickup is scheduled for tomorrow. If you would like to cancel this pickup, reply CANCEL. Thank you for your support - The DonateStuff.com Team.";
                send_sms_scheduled_pickup($receiver_number, $message);
            }
        }
    }
}

/**
 * Cron job : send customers about charity reminders every 3 month
 * - Quick Reschedule Text
 * @param bool $forlog
 * @return array $logs
 */
function pickup_custom_cron_sms_three_months($forlog = false)
{

    // get the length of time (eg. -3 months)
    $remarketing_time = TwilioWpPlugin::get_remarketing_time();

    // send users on remarketing list for log
    $logs = [];
    $log_count = 10;

    // load all orders from exactly 3 months ago
    $orders = get_orders_from_time_filter($remarketing_time);

    // make list unique by allowing only unique user_id
    // since the last order will be same for all entries
    $listed_uid = [];

    // send sms to each customer in the order list
    foreach ($orders as $item) {

        // get customer from order
        $order_id = $item->ID;
        $order = new WC_Order($order_id);

        if ($order) {
            // build order data in array
            $data = create_sms_pickup_order_data($order);
            $number = (isset($data['phone'])) ? $data['phone'] : '';

            // check if mobile number is allowed
            $allowed = check_twilio_phone_is_allowed($number);

            if ($allowed && !empty($number)) {
                // get customer from order and find the last order's pickup date
                // pickup date should not be recent (within remarketing time)
                $customer = get_twilio_customer_by_phone($number);
                $user_id = ($customer) ? $customer->ID : 0;
                if ($user_id) {

                    // only allow unique user id
                    if (in_array($user_id, $listed_uid)) {
                        continue;
                    } else {
                        $listed_uid[] = $user_id;
                    }

                    $wc_customer = new WC_Customer($user_id);
                    $last_order = $wc_customer->get_last_order();
                    $customer_last_pickup_date = get_post_meta($last_order->ID, '_billing_pickup_date', true);
                    $customer_last_pickup_date = date('Ymd', strtotime($customer_last_pickup_date));
                    $remarketing_time_date = date('Ymd', strtotime($remarketing_time));

                    // last pickup date should not be less than remarketing date (eg. -3 months)
                    if ($customer_last_pickup_date > $remarketing_time_date) {
                        // too soon for remarketing, because the customer ordered recently according to remarketing time
                    } else {
                        if ($data) {
                            // date will be picked from the first/closest pickupdate scheduled for users post zone
                            // gets the pickup order date "Ymd" for currently logged in user
                            $data['pickup_date'] = get_next_pickupdate_for_user($order_id, $user_id);

                            // Send SMS
                            // - Quick Reschedule Text
                            if (!empty($data['pickup_date'])) {
                                if ($forlog === false) {
                                    $message = "We greatly appreciated your support for " . $data['charity_name'] . " and wanted to let you know that we are coming by again on " . $data['pickup_date'] . ". If you have more gently used goods that you would like us to pick up, please reply ‘YES’ and we’ll stop by!";
                                    send_sms_scheduled_pickup($number, $message);
                                } else if ($forlog) {
                                    // only show few results for log at max
                                    $log_count--;
                                    if ($forlog && $log_count <= 0) {
                                        break;
                                    }
                                    $logs[] = (object) array(
                                        'user_id' => $user_id,
                                        'last_order_id' => $last_order->ID,
                                        'displayname' => $wc_customer->get_display_name(),
                                        'firstname' => $wc_customer->get_first_name(),
                                        'lastname' => $wc_customer->get_last_name(),
                                        'pickup_date' => $data['pickup_date'],
                                        'charity_name' => $data['charity_name'],
                                    );
                                }
                            }
                        }
                    }
                } //user-id
            } //allowed
        }
    } //order-loop

    if ($forlog) {
        return $logs;
    }
}

/**
 * Get : sms logs from twilio
 *
 * @param $from
 * @param string $to
 * @return array $sms_logs
 * @throws \Twilio\Exceptions\ConfigurationException
 */
function get_twilio_sms_logs($from, $to = "")
{
    require_once(get_stylesheet_directory() . '/vendor/autoload.php');
    // Sample message : fetched from twilio

    // get information of twilio SDK
    $twilio_info = get_twilio_information_custom();
    // Your Account SID and Auth Token from twilio.com/console
    $account_sid = $twilio_info['account_sid'];
    $auth_token = $twilio_info['auth_token'];
    $client = new Twilio\Rest\Client($account_sid, $auth_token);

    // date filter
    if (empty($to)) {
        // set $to as next day of $from
        $datetime = new DateTime($from);
        $datetime->modify('+1 day');
        $to = $datetime->format('Y-m-d');
    }

    // fetch
    $messages = $client->messages->stream(
        array(
            'dateSentAfter' => $from,
            'dateSentBefore' => $to,
        )
    );
    $rows = [];
    foreach ($messages as $sms) {
        $row = (object) array(
            'sid' => $sms->sid,
            'from' => $sms->from,
            'to' => $sms->to,
            'date_sent' => $sms->dateSent->format('Y-m-d H:i:s'),
            'status' => $sms->status,
            'direciton' => $sms->direction,
            'price' => $sms->price,
            'message' => $sms->body
        );
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Get : order ID from number (phone) from billing address
 *  - fetches the last order's ID
 * @param string $number
 * @return int $order_id
 */
function get_order_id_by_number($number) {
    // find user by phone number    
    $order_id = get_order_with_phone_number($number);
    if (!$order_id) {
        // twilio always add "+1" in number so try finding order with no "+1"
        $number = str_replace('+1', '', $number);
        $order_id = get_order_with_phone_number($number);
    }

    return $order_id;
}

/**
 * Get : Order ID from phone number in billing information
 *  - it is sorted by Order ID in descending so it will fetch the last order
 * 
 * @param string $number
 */
function get_order_with_phone_number($number) {
    global $wpdb;
    $result = $wpdb->get_results( 
    $wpdb->prepare( 
        'SELECT post_id FROM wp_postmeta 
        WHERE meta_key = "_billing_phone" AND meta_value = %s
        ORDER BY post_id DESC', $number));
    $record = (!empty($result)) ? $result[0] : false;

    $order_id = ($record) ? $record->post_id : false;

    return $order_id;
}

/**
 * Get : pickup date : gets the pickup order date "Ymd" for currently logged in user
 * @param int $order_id
 * @param int $user_id
 * @return string $pickup_date
 */
function get_next_pickupdate_for_user($order_id, $user_id)
{
    $order = new WC_Order($order_id);
    $pickup_date_found = '';

    if ($order) {
        $products = $order->get_items();

        $product_id = 0;
        foreach ($products as $product) {
            $product_id = $product->get_product_id();
        }

        $pickup_date_found = get_product_next_pickup_date($product_id, $user_id);

        if (!empty($pickup_date_found)) {
            // format the date
            $date = strtotime($pickup_date_found);
            $pickup_date_found = date('F jS, Y', $date);
        }
    }

    return $pickup_date_found;
}

/**
 * Get : date : next pickup date for given product for logged in customer
 * @param int $product_id
 * @param int $user_id
 * @return string $pickup_date
 */
function get_product_next_pickup_date($product_id, $user_id = 0)
{
    global $woocommerce;

    $customer_postcode = '';

    // get post code for given user id
    if ($user_id) {
        $customer = new WC_Customer($user_id);
        $customer_postcode = $customer->get_shipping_postcode();
        $customer_postcode = (!empty($customer_postcode)) ? $customer_postcode : $customer->get_billing_postcode();
    } else {
        // get logged in customer post code
        if ($woocommerce->customer) {
            $customer_postcode = $woocommerce->customer->get_shipping_postcode();
            $customer_postcode = (!empty($customer_postcode)) ? $customer_postcode : $woocommerce->get_billing_postcode();
        }
    }

    $pickup_date_found = '';
    $matched_dates = array();

    if ($product_id) {
        $product = wc_get_product($product_id);
        $today = date("Ymd");

        for ($i = 0; $i < 1000; $i++) {
            $info_id = 'pickup_schedule_' . $i . '_zip_codes';
            $info_id_day = 'pickup_schedule_' . $i . '_day';
            $info = get_post_meta($product_id, $info_id, true);
            $info_date = get_post_meta($product_id, $info_id_day, true);

            if (!empty($info)) {
                // date must not be less or equal to today
                $date_is_valid = (intval($info_date) > intval($today));

                // customer zip code must match
                $zipcodes = explode(',', $info);
                if (in_array($customer_postcode, $zipcodes) && $date_is_valid) {
                    $matched_dates[] = intval($info_date);
                }
            } else {
                break;
            }
        }
    }
    if (!empty($matched_dates)) {
        $pickup_date_found = min($matched_dates);
    }

    return $pickup_date_found;
}


/**
 * Get : orders from last 3 month from today
 * @param string $time_filter
 * @return array $orders
 */
function get_orders_from_time_filter($time_filter)
{
    global $wpdb;

    $date = date("Y-m-d", strtotime($time_filter));

    $post_status = implode("','", array('wc-processing', 'wc-completed'));

    $orders = $wpdb->get_results(
        "SELECT * FROM $wpdb->posts
            WHERE post_type = 'shop_order'
            AND post_status IN ('{$post_status}')
            AND post_date BETWEEN '{$date}  00:00:00' AND '{$date} 23:59:59'
            ORDER BY post_date DESC "
    );

    return $orders;
}

/**
 * Get : data like (phone,charity_name,pickup_date) for given order object
 * @param WC_Order $object
 * @return array $data
 */
function create_sms_pickup_order_data($order)
{
    $order_id = $order->get_id();
    $order_data = $order->get_data();

    if (!$order_data) {
        return [];
    }

    // $receiver_number = (isset($order_data['billing']['phone'])) ? $order_data['billing']['phone'] : '';
    $receiver_number = $order->get_billing_phone();
    $pickup_date = get_post_meta($order_id, '_billing_pickup_date', true);

    $last_charity_name = get_post_meta($order_id, '_billing_charity', true);
    $last_pickup_date = $pickup_date;

    // build data
    return array(
        'phone' => $receiver_number,
        'charity_name' => $last_charity_name,
        'pickup_date' => $last_pickup_date,
    );
}

/**
 * Properly format a number to make it usuable
 * @return string $formatted_number
 */
function twilio_format_phone_number($number, $zip = '')
{
    $formatted_number = $number;
    return $formatted_number;
}

/**
 * Add fields : to checkout : add "send sms" checkbox field in the checkout page
 * @param array $fields
 * @return mixed
 */
function twilio_add_checkout_fields($fields)
{

    $fields['billing']['billing_send_sms'] = array(
        'label'     => __('Opt-in to receive text message updates and reminders regarding your pickup.', 'woocommerce'),
        'type'      => 'checkbox',
        'required'  => false,
        'class'     => array('form-item form-item-send-sms'),
        'clear'     => true
    );

    return $fields;
}

/**
 * Pickup Edited / Updated : send sms
 * @param int $meta_id
 * @param int $post_id
 * @param string $meta_key
 * @param string $meta_value
 */
function send_sms_on_order_edit($meta_id, $post_id, $meta_key, $meta_value)
{
    $type = get_post_type( $post_id );

    if ($type == 'shop_order') {
        if ($meta_key == '_billing_pickup_date') {
            // load pickup order
            $order = new WC_Order($post_id);

            // get customer phone number and schedules
            $receiver_number = $order->get_billing_phone();
            $dev_mode = get_twilio_integration_dev_mode_status();
            if ($dev_mode) {
                $receiver_number = get_twilio_allowed_number_in_dev(true);
            }
            $pickup_date = get_post_meta($post_id, '_billing_pickup_date', true);

            if (!empty($pickup_date) || !empty($receiver_number)) {
                // check if mobile number is allowed
                $allowed = check_twilio_phone_is_allowed($receiver_number);

                if ($allowed) {
                    // Scheduled Pickup Failed Text
                    $pickup_date_formatted  = date('F jS, Y', strtotime($pickup_date));
                    $message = "Your pickup order ($post_id) was recheduled to $pickup_date_formatted.";

                    // send SMS, with some error handling
                    send_sms_scheduled_pickup($receiver_number, $message);
                }
            }
        }
    }
}

/**
 * Field Display : show send sms option on the edit page of order
 * @param object $order
 */
function twilio_donate_field_send_sms_meta($order)
{
    $status = get_post_meta($order->get_id(), '_billing_send_sms', true);
    $show = "False";
    if ($status == "1") {
        $show = "True";
    }
    echo '<p><strong>' . __('Send SMS Option') . ':</strong> ' . $status . '</p>';
}

/**
 * Filter :
 * @param array $filters
 */
function twilio_sms_logs_view($filters)
{
    $from = $filters['from'];
    $to = (!empty($filters['to'])) ? $filters['to'] : date("Y-m-d", strtotime("today"));

    $data = [];
    if (!empty($from)) {
        $data = get_twilio_sms_logs($from, $to);
    }

    return $data;
}

/**
 * Filter :
 * @param array $filters
 */
function twilio_sms_remarketing_logs_view($filters)
{

    $data = pickup_custom_cron_sms_three_months(true);

    return $data;
}

/**
 * * ACTIONS
 * ? Add all actions below this section
 */

/**
 * * Add action to thank you page
 *  - after use schedules a pickup, a sms is sent to customer
 */
add_action('woocommerce_thankyou', 'action_woocommerce_send_pickup_reminder', 112, 1);

/**
 * * Add action when order is cancelled
 *  - user has cancelled the order
 */
add_action('woocommerce_order_status_cancelled', 'action_woocommerce_cancel_pickup_sms', 11, 1);

/**
 * * Add action when order Pickup successful
 *  - order was completed
 */
add_action('woocommerce_order_status_completed', 'action_woocommerce_completed_pickup_sms', 11, 1);

/**
 * * Add SMS Routes - define routes for twilio using namespaces
 *   The route starst with wp-json and looks like this : /wp-json/twilio/sms/v1/replysms
 *  - add '/wp-json/' at start of the route
 */
add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'twilio/sms/v1',
            '/replysms',
            array(
                'methods' => 'POST',
                'callback' => 'handle_twilio_replysms',
            )
        );
    }
);

/**
 * Route : cron job : reminder tomorrow
 *  - add '/wp-json/' at start of the route
 */
add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'twilio/sms/v1',
            '/cron/tomorrow',
            array(
                'methods' => 'GET',
                'callback' => 'pickup_custom_cron_sms_tomorrow_reminder_rest',
            )
        );
    }
);

/**
 * Route : cron job : remarketing
 * - add '/wp-json/' at start of the route
 */
add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'twilio/sms/v1',
            '/cron/remarketing',
            array(
                'methods' => 'GET',
                'callback' => 'pickup_custom_cron_sms_three_months_rest',
            )
        );
    }
);

/**
 * * Action : cron job for sending reminders to order of tomorrow
 */
add_action('pickup_custom_cron_reminder', 'pickup_custom_cron_sms_tomorrow_reminder');

/**
 * * Action : cron job for sending past customers the sms (looks up orders from 3 months back fron today)
 */
add_action('pickup_custom_cron', 'pickup_custom_cron_sms_three_months');

/**
 * * Action : order status failed
 */
add_action('woocommerce_order_status_failed', 'action_woocommerce_pickup_status_failed', 10, 1);

/**
 * Fields : add "send sms" checkbox field in the checkout page
 */
add_filter('woocommerce_checkout_fields', 'twilio_add_checkout_fields');

/**
 * Display Fields : show field on edit page
 */
add_action('woocommerce_admin_order_data_after_billing_address', 'twilio_donate_field_send_sms_meta', 10, 1);

/**
 * Custom Filter : show list of sms logs
 */
add_filter('twilio_sms_logs', 'twilio_sms_logs_view');

/**
 * Custom Filter : show list of remarketing clients
 */
add_filter('twilio_sms_remarketing_logs', 'twilio_sms_remarketing_logs_view');

/**
 * Action : send sms when the pickup/order is edited
 */
add_action('updated_post_meta', 'send_sms_on_order_edit', 10, 4);

