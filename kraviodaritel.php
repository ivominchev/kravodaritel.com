<?php
/**
 * Plugin Name: Kravodaritel
 * Description: Плъгин за управление на кръводарители и търсещи кръв.
 * Version: 0.2
 * Author: Mikeda Digital
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KRV_PLUGIN_FILE', __FILE__ );
define( 'KRV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KRV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Основни класове (роли, търсене, чат)
require_once KRV_PLUGIN_DIR . 'includes/class-kr-user-registration.php';
require_once KRV_PLUGIN_DIR . 'includes/class-krv-donor-search.php';
require_once KRV_PLUGIN_DIR . 'includes/class-krv-chat.php';

// Модул за регистрация/логин/профил
require_once KRV_PLUGIN_DIR . 'includes/auth.php';

class Kravodaritel_Plugin {

    protected static $instance = null;
    /** @var \Kravodaritel\User_Registration */
    public $user_registration;

    private function __construct() {
        // засега само ролите; другото е във файловете със собствени hook-ове
        $this->user_registration = new \Kravodaritel\User_Registration();
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        \Kravodaritel\User_Registration::activate();
    }

    public static function deactivate() {
        \Kravodaritel\User_Registration::deactivate();
    }
}

register_activation_hook( __FILE__, [ 'Kravodaritel_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Kravodaritel_Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    Kravodaritel_Plugin::instance();
} );



// ВРЕМЕННО: деблокира всички чатове за тестове
add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( ! isset( $_GET['kr_unblock_all_chats'] ) ) {
        return;
    }

    $users = get_users( [
        'meta_key' => 'kr_blocked_threads',
        'fields'   => 'ID',
    ] );

    foreach ( $users as $uid ) {
        delete_user_meta( $uid, 'kr_blocked_threads' );
    }

    wp_die( 'Всички чат блокировки са изчистени.' );
} );

