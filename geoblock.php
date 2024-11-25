<?php
/*
Plugin Name: Geoblocking Pluginhttps://dev.teleantillas.com.do/wp-admin/edit.php?post_type=noticias
Description: A simple plugin to block access to the site based on the user's location.
Version: 1.0
Author: Comunique Digital Agency
*/

require_once __DIR__ . '/vendor/autoload.php';

use GeoIp2\Database\Reader;

if (!defined('ABSPATH')) {
    exit;
}

class GeoblockingPlugin {

    //create table to save the token and duration and creation daily motion

    public function __construct() {
        add_action('init', array($this, 'check_user_location'));
        register_activation_hook(__FILE__, array($this, 'create_table'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'geoblocking_register_settings'));
    }

    public function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'geoblocking';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            token TEXT NOT NULL,
            duration varchar(100) NOT NULL,
            creation datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            status varchar(100) DEFAULT 'not_blocked' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    //add item to the admin menu
    public function add_admin_menu() {
        add_menu_page(
            'Geoblocking', 
            'Geoblocking', 
            'manage_options', 
            'geoblocking-plugin', 
            array($this, 'admin_page'), 
            'dashicons-admin-site'
        );
    }

    //admin page
    public function admin_page() {
        include __DIR__ . '/geoblock-admin.php';
    }

    public function check_user_location() {
        $user_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        $user_country = $this->get_country_by_ip($user_ip);
        $allowed_countries = get_option('geoblocking_allowed_countries', '');
        return in_array($user_country, explode(',', $allowed_countries));
    }

    private function authenticateDailyMotion($apiKey, $apiSecret) {
        $response = wp_remote_post('https://partner.api.dailymotion.com/oauth/v1/token', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $apiKey,
                'client_secret' => $apiSecret,
                'scope' => 'manage_videos'
            )
        ));
        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body, true);
        global $wpdb;
        $table_name = $wpdb->prefix . 'geoblocking';
        $wpdb->update($table_name, array(
            'token' => $data['access_token'],
            'duration' => $data['expires_in'],
            'creation' => current_time('mysql', 1),
        ),array("id"=>1));
    }

    private function get_country_by_ip($ip) {
        $db_path = __DIR__ . '/GeoLite2-Country.mmdb';
        try {
            $reader = new Reader($db_path);
            $record = $reader->country($ip);
            $country = $record->country->isoCode;
            return $country;
        } catch (Exception $e) {
            return "sin pais";
        }
    }

    public function geoblocking_register_settings() {
        register_setting('geoblocking_settings_group', 'geoblocking_iframe_main');
        register_setting('geoblocking_settings_group', 'geoblocking_iframe_alternate');
        register_setting('geoblocking_settings_group', 'geoblocking_allowed_countries');
        register_setting('geoblocking_settings_group', 'geoblocking_blocked_schedule');

        // Campos para configuración de API
        register_setting('geoblocking_settings_group', 'geoblocking_livestream_id');
        register_setting('geoblocking_settings_group', 'geoblocking_api_key');
        
        // Cifrado del API Secret
        register_setting('geoblocking_settings_group', 'geoblocking_api_secret');
    }

    public function detectIfShouldUpdateDailyMotionVideo() {
        $current_time = new DateTime('now', new DateTimeZone('America/Santo_Domingo'));
        $current_hour = $current_time->format('H:i');

        $days = array('Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado');
        $today = $days[(new DateTime)->format('N') % 7];
        global $wpdb;
        $table_name = $wpdb->prefix . 'geoblocking';
        $result = $wpdb->get_results("SELECT * FROM $table_name WHERE id = 1 ORDER BY creation DESC LIMIT 1");
        $creation = new DateTime($result[0]->creation);
        $creation->add(new DateInterval('PT' . $result[0]->duration . 'S'));
        $blocked_schedule = get_option('geoblocking_blocked_schedule', []);
        $apiKey = get_option('geoblocking_api_key', '');
        $apiSecret = get_option('geoblocking_api_secret', '');
        if (isset($blocked_schedule[$today])) {
            foreach ($blocked_schedule[$today] as $time_range) {
                if ($current_hour >= $time_range['start'] && $current_hour <= $time_range['end']) {
                    if ($creation < $current_time) {
                        $this->authenticateDailyMotion($apiKey, $apiSecret);
                    }
                    return $this->geoblockDailyMotion();
                }
            }
        }
        if($result[0]->status == 'blocked'){
            if ($creation < $current_time) {
                $this->authenticateDailyMotion($apiKey, $apiSecret);
            }
            $this->unblockDailyMotion();
        }
        return;
    }

    public function geoblockDailyMotion(){
        global $wpdb;
        $allowed_countries = get_option('geoblocking_allowed_countries', '');
        $allowed_countries = strtolower($allowed_countries);

        $table_name = $wpdb->prefix . 'geoblocking';
        $result = $wpdb->get_results("SELECT * FROM $table_name ORDER BY creation DESC LIMIT 1");
        $token = $result[0]->token;
        $liveID = get_option("geoblocking_livestream_id");
        $response = wp_remote_post('https://partner.api.dailymotion.com/rest/video/'.$liveID, array(
            "headers" => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                "Authorization" => "Bearer " . $token,
            ),
            "body" => array(
                "geoblocking" => 'allow, ' . $allowed_countries,
            )
        ));

        $status = wp_remote_retrieve_response_code($response);
    
        if($status == 200){
            global $wpdb;
            $table_name = $wpdb->prefix . 'geoblocking';
            $wpdb->update($table_name, array('status' => 'blocked'), array('id' => 1));
        }
    }

    public function unblockDailyMotion(){
        global $wpdb;
        $table_name = $wpdb->prefix . 'geoblocking';
        $result = $wpdb->get_results("SELECT * FROM $table_name ORDER BY creation DESC LIMIT 1");
        $token = $result[0]->token;
        $liveID = get_option("geoblocking_livestream_id");
        $response = wp_remote_post('https://partner.api.dailymotion.com/rest/video/'.$liveID, array(
            "headers" => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                "Authorization" => "Bearer " . $token,
            ),
            "body" => array(
                "geoblocking" => 'allow'
            )
        ));
        
        $status = wp_remote_retrieve_response_code($response);

        if($status == 200){
            global $wpdb;
            $table_name = $wpdb->prefix . 'geoblocking';
            $wpdb->update($table_name, array('status' => 'not_blocked'), array('id' => 1));
        }
    }

    function geoblocking_encrypt_api_secret($input) {
        $encryption_key = 'supersecurekey123';
        return openssl_encrypt($input, 'AES-128-CTR', $encryption_key, 0, '1234567891011121');
    }
    
    // Función para descifrar el API Secret
    function geoblocking_decrypt_api_secret($encrypted) {
        $encryption_key = 'supersecurekey123';
        return openssl_decrypt($encrypted, 'AES-128-CTR', $encryption_key, 0, '1234567891011121');
    }
    

    public function geoblocking_render_iframe() {
        $iframe_main = get_option('geoblocking_iframe_main', '');
        $iframe_alternate = get_option('geoblocking_iframe_alternate', '');
        $blocked_schedule = get_option('geoblocking_blocked_schedule', []);

        $current_time = new DateTime('now', new DateTimeZone('America/Santo_Domingo'));
        $current_hour = $current_time->format('H:i');

        $days = array('Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado');
        $today = $days[(new DateTime)->format('N') % 7];

        if (isset($blocked_schedule[$today])) {
            foreach ($blocked_schedule[$today] as $time_range) {
                if ($current_hour >= $time_range['start'] && $current_hour <= $time_range['end'] && !$this->check_user_location()) {
                    echo $iframe_alternate;
                    return;
                }
            }
        }
        echo $iframe_main;
    }
}

$geo = new GeoblockingPlugin();

add_action('detect_if_should_update_dailymotion_video', array($geo, 'detectIfShouldUpdateDailyMotionVideo'));

add_shortcode('geoblocking_iframe', array($geo, 'geoblocking_render_iframe'));
?>