<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * مدیر لایسنس: تولید کلید و توابع کمکی
 */

class Mizan_License_Manager {

    // تولید کلید لایسنس تصادفی امن (طول 40)
    public static function generate_license_key() {
        try {
            $bytes = random_bytes( 20 );
            return strtoupper( bin2hex( $bytes ) ); // کلید بزرگ حروف
        } catch ( Exception $e ) {
            // fallback امن
            return strtoupper( wp_generate_password( 40, false, false ) );
        }
    }

    // فرمت نمایش تاریخ قابل خواندن
    public static function format_time( $timestamp ) {
        if ( ! $timestamp ) return '';
        return date_i18n( 'Y-m-d H:i:s', $timestamp );
    }
}