<?php
namespace Kravodaritel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class User_Registration {

    public function __construct() {
        // Умишлено празен – шорткодовете са в основния плъгин файл.
    }

    public static function activate() {
        add_role( 'kr_donor', 'Кръводарител', [ 'read' => true ] );
        add_role( 'kr_seeker', 'Търсещ кръв', [ 'read' => true ] );
    }

    public static function deactivate() {
        remove_role( 'kr_donor' );
        remove_role( 'kr_seeker' );
    }
}
