<?php

/**
 * Get : mailchimp API key
 * @return {string} $key
 */
function get_mailchimp_api_key() {
    $key = 'ABCDEFGGHIGHJKL';
    return $key;
}

/**
 * Get : date range for exporting csv
 * @return string $range
 */
function get_report_csv_date_range() {
    $range = get_option('mailchimp_donate_date_range');
    return $range;
}

/**
 * Get : email that receives the csv report of pickups/ordrers
 * @return string $email
 */
function get_mailchimp_master_email() {
    $mails = get_option('mailchimp_donate_admin_mail');
    $list = explode(',', $mails);
    $result = [];
    foreach ($list as $item) {
        $result[] = str_replace(' ', '', $item);
    }

    if (count($result) == 1) {
        return $result[0];
    }

    return $result;
}

/**
 * Get : if dev mode is enabled for emails of mailchimp integration
 * @return boolean $dev_mode
 */
function get_mailchimp_master_dev_status() {
    return  get_option('mailchimp_master_dev_mode_status');
}

/**
 * Get : list of regions
 * 
 * @return array $regions
 */
function getRegions() {
    $regions = [];

    $terms = get_terms( array(
        'taxonomy' => 'charity_region',
        'hide_empty' => false,
    ) );

    foreach ($terms as $term) {
        $keyname = str_replace(' ', '_', $term->name);
        $keyname = strtolower($keyname);
        $regions[$keyname] = $term->name;
    }
    
    return $regions;
}

/**
 * Get : display name of the region
 * @param {string} $region
 * @return {string} $label
 */
function getRegionLabel($region) {

    // if already label, sent as it is
    if (strpos($region, ' ') !== false) {
        return $region;
    }

    $terms = get_terms( array(
        'taxonomy' => 'charity_region',
        'hide_empty' => false,
    ) );
    foreach ($terms as $term) {
        $keyname = str_replace(' ', '_', $term->name);
        $keyname = strtolower($keyname);
        if ($keyname == $region) {
            return $term->name;
        }
    }
    return '';
}

/**
 * Cancel : mailchimp scheduled emails
 * @param {string} $send_email
 */
function cancel_mailchimp_mail($send_email) {
    require_once(get_stylesheet_directory() . '/vendor/autoload.php');
    $api_key = get_mailchimp_api_key();
    $mailchimp = new MailchimpTransactional\ApiClient();
    $mailchimp->setApiKey($api_key);

    $send_email_id = '';
    $list_options = [
        'key' => $api_key,
    ];
    $list = $mailchimp->messages->listScheduled($list_options);
    
    foreach ($list as $item) {
        if ($item->to == $send_email) {
            $send_email_id = $item->_id;
            break;
        }
    }

    if (!empty($send_email_id)) {
        $cancel_options = [
            'key' => $api_key,
            'id' => $send_email_id,
        ];
        $mailchimp->messages->cancelScheduled($cancel_options);
    }
}

/**
 * Reschedule : reschedule a scheduled mail if pickup order is edited
 * @param {string} $send_email
 * @param {string} $new_pickkup_time
 */
function reschedule_mailchimp_mail($send_email, $new_pickup_time) {
    require_once(get_stylesheet_directory() . '/vendor/autoload.php');
    $api_key = get_mailchimp_api_key();
    $mailchimp = new MailchimpTransactional\ApiClient();
    $mailchimp->setApiKey($api_key);

    $send_email_id = '';
    $list_options = [
        'key' => $api_key,
    ];
    $list = $mailchimp->messages->listScheduled($list_options);
    
    foreach ($list as $item) {
        if ($item->to == $send_email) {
            $send_email_id = $item->_id;
            break;
        }
    }

    if (!empty($send_email_id) && !empty($new_pickup_time)) {
        $reschedule_options = [
            'key' => $api_key,
            'id' => $send_email_id,
            'sent_at' => $new_pickup_time,
        ];
        $mailchimp->messages->reschedule($reschedule_options);
    }
}

/**
 * Send Email - using mailchimp library - used in cron job
 * @param {string} $mail
 * @param {string} $region
 * @param {string} $from
 * @param {string} $to
 * @return {object} $response
 */
function send_order_report_as_email($mail, $region='', $from='', $to='') {
    require_once(get_stylesheet_directory() . '/vendor/autoload.php');
    $api_key = get_mailchimp_api_key();
    $report_token = get_report_generator_api_key();
    $date_range = get_report_csv_date_range();

    $mailchimp = new MailchimpTransactional\ApiClient();
    $mailchimp->setApiKey($api_key);

    // date
    $send_date = date('Y-m-d', strtotime("yesterday")) . " 00:00:01"; // send date yesterday means, email send immediately
    $date_to = date('Y-m-d', strtotime("yesterday"));
    // positive date
    if (strpos($date_range, '+') !== false) {
        $date_from = date('Y-m-d', strtotime('today'));
        $date_to = date('Y-m-d', strtotime('today ' . $date_range));
    } else {
        $date_from = date('Y-m-d', strtotime($date_to . ' ' . $date_range));
    }
    if (!empty($from)) {
        $date_from = $from;
    }
    if (!empty($to)) {
        $date_to = $to;
    }

    // Display name of region
    $regionLabel = getRegionLabel($region);
    $regionMachineName = '';
    if (!empty($region)) {
        $regionMachineName = str_replace(' ', '_', $regionLabel);
        $regionMachineName = strtolower($regionMachineName);
    }

    // link
    // $base_url = "https://donatestage.wpengine.com";
    $base_url = get_site_url();
    $link = "<a href=\"$base_url/wp-json/charity/region/reports?from=$date_from&to=$date_to&region=$region&region_machine_name=$regionMachineName&token=$report_token\">download</a>";

    // prepare message
    $message = "Click this link to download the report from $regionLabel $date_from to $date_to $link.";
    $message_html = "Click this link to download the report from $regionLabel $date_from to $date_to $link.";

    $data = [
        'message' => [
            "html" => $message_html,
            "text"=> $message,
            "subject"=> "Charities Pickup Report",
            "from_email"=> sendFrom(false),
            "from_name"=> "The DonateStuff.com Team",
            "to"=> [[
                "email"=> $mail,
                "name" => "Donate Stuff Customer Name",
                "type" => "to"
            ]],
        ],
        "send_at" => $send_date,
    ];

    $response = $mailchimp->messages->send($data);
    return $response;
}

/**
 * Send Donar List Report Email - using mailchimp library - used in cron job
 * @param {string} $mail
 * @param {string} $filter_charity
 * @param {string} $filter_date
 * @return {object} $response
 */
function send_donar_report_as_email($mail, $filter_charity='', $filter_date='') {
    require_once(get_stylesheet_directory() . '/vendor/autoload.php');
    $api_key = get_mailchimp_api_key();
    $report_token = get_report_generator_api_key();

    $mailchimp = new MailchimpTransactional\ApiClient();
    $mailchimp->setApiKey($api_key);

    $exploded_date = explode('-', $filter_date);
    $year = $exploded_date[0];
    $month = $exploded_date[1];
    $dateObj   = DateTime::createFromFormat('!m', $month);
    $monthName = $dateObj->format('F'); // March

    // link
    $base_url = get_site_url();
    $link = "<a href=\"$base_url/wp-json/charity/donar/reports?date=$filter_date&charity=$filter_charity&token=$report_token\">download</a>";

    // prepare message
    $message = "Click this link to download the donar list for the month of $monthName, $year $link.";
    $message_html = "Click this link to download the donar list for the month of $monthName, $year $link.";

    $data = [
        'message' => [
            "html" => $message_html,
            "text"=> $message,
            "subject"=> "Donars Report - $monthName",
            "from_email"=> sendFrom(false),
            "from_name"=> "The DonateStuff.com Team",
            "to"=> [[
                "email"=> $mail,
                "name" => "Donate Stuff Customer Name",
                "type" => "to"
            ]],
        ],
        "send_at" => $send_date,
    ];

    $response = $mailchimp->messages->send($data);
    return $response;
}


/**
 * Send Email for order - using mailchimp library
 * send order confirmation mail
 * 
 * @param {object} $order
 * @param {string} $mail
 * @return {object} $response
 */
function send_report_for_order_edit($order, $mail) {
    require_once(get_stylesheet_directory() . '/vendor/autoload.php');
    $api_key = get_mailchimp_api_key();

    $mailchimp = new MailchimpTransactional\ApiClient();
    $mailchimp->setApiKey($api_key);
    
    // date
    $pickup_date = $order->get_meta('_billing_pickup_date');
    $firstname = $order->get_billing_first_name();
    $lastname = $order->get_billing_last_name();
    
    // prepare message
    $message = "Your pickup order was edited and the new pickup date has been changed to $pickup_date.";
    $message_html = "Your pickup order was edited and the new pickup date has been changed to $pickup_date.";

    $data = [
        'message' => [
            "html" => $message_html,
            "text"=> $message,
            "subject"=> "Your pickup order was edited",
            "from_email"=> sendFrom(false),
            "from_name"=> "The DonateStuff.com Team",
            "to"=> [[
                "email"=> $mail,
                "name" => $firstname . " " . $lastname,
                "type" => "to"
            ]],
        ],
        "send_at" => $send_date,
    ];
    $response = $mailchimp->messages->send($data);
    return $response;
}

/**
 * Pickup Edited / Updated : send email
 * 
 * @param {integer} $post_id
 * @param {object} $post
 */
function send_email_on_order_edit($meta_id, $post_id, $meta_key, $meta_value) {

    $type = get_post_type( $post_id );

    if ($type == 'shop_order') {
        if ($meta_key == '_billing_pickup_date') {
            $order = new WC_Order($post_id);
            $pickup_time = get_post_meta($post_id, '_billing_pickup_date', true);
            $send_email = $order->get_billing_email();

            // use admin mail for dev mode
            $dev_mode = get_mailchimp_master_dev_status();
            if ($dev_mode) {
                $send_email = get_mailchimp_master_email();
            }

            if (!empty($send_email) && (!empty($pickup_time))) {

                if (is_array($send_email)) {
                    // send all mails in list
                    foreach ($send_email as $mail) {
                        // reschedule old mailchimp mail
                        reschedule_mailchimp_mail($mail, $pickup_time);

                        // send email informing about rescheduling
                        send_report_for_order_edit($order, $mail);
                    }
                } else {
                    // single mail

                    // reschedule old mailchimp mail
                    reschedule_mailchimp_mail($send_email, $pickup_time);

                    // send email informing about rescheduling
                    send_report_for_order_edit($order, $send_email);
                }
                
            }
        }
    }
}

/**
 * Action : send email when the pickup/order is edited
 */
// add_action('updated_post_meta', 'send_email_on_order_edit', 10, 4);


?>