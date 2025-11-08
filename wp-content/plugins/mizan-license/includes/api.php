<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * تعریف مسیرهای REST API برای ثبت‌نام و بررسی لایسنس
 * Routes:
 * - POST /wp-json/mizan/v1/register      -> ثبت درخواست (status = pending)
 * - POST /wp-json/mizan/v1/check         -> بررسی وضعیت لایسنس بر اساس device_hash یا email
 */

add_action( 'rest_api_init', function () {
    register_rest_route( 'mizan/v1', '/register', array(
        'methods'             => 'POST',
        'callback'            => 'mizan_api_register',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'mizan/v1', '/check', array(
        'methods'             => 'POST',
        'callback'            => 'mizan_api_check',
        'permission_callback' => '__return_true',
    ) );
} );

// ثبت درخواست از اپ
function mizan_api_register( WP_REST_Request $request ) {
    $params = $request->get_json_params();

    // فیلدهای لازم
    $required = array( 'email', 'first_name', 'last_name', 'username', 'phone', 'store_name', 'device_hash' );
    foreach ( $required as $f ) {
        if ( empty( $params[ $f ] ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => "فیلد $f ضروری است." ), 400 );
        }
    }

    // درج درخواست
    $id = Mizan_License_DB::insert_request( $params );
    if ( $id ) {
        return new WP_REST_Response( array( 'success' => true, 'request_id' => $id, 'message' => 'درخواست ثبت شد. پس از تائید مدیریت کلید برای شما ارسال می‌شود.' ), 201 );
    }

    return new WP_REST_Response( array( 'success' => false, 'message' => 'خطا در ثبت درخواست.' ), 500 );
}

// بررسی وضعیت لایسنس (اپ فراخوانی می‌کند)
function mizan_api_check( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $device_hash = isset( $params['device_hash'] ) ? sanitize_text_field( $params['device_hash'] ) : '';
    $email = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';

    if ( empty( $device_hash ) && empty( $email ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'device_hash یا email لازم است.' ), 400 );
    }

    $row = Mizan_License_DB::get_active_by_device_or_email( $device_hash, $email );
    if ( $row ) {
        return new WP_REST_Response( array(
            'success' => true,
            'license_key' => $row->license_key,
            'expires_at'  => intval( $row->expires_at ),
            'issued_at'   => intval( $row->issued_at ),
            'message'     => 'لایسنس فعال است.'
        ), 200 );
    }

    // اگر رکورد وجود دارد ولی pending
    // بررسی رکورد مبتنی بر device_hash یا ایمیل
    global $wpdb;
    $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
    $row_pending = null;
    if ( $device_hash ) {
        $row_pending = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE device_hash = %s LIMIT 1", $device_hash ) );
    }
    if ( ! $row_pending && $email ) {
        $row_pending = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_email = %s LIMIT 1", $email ) );
    }

    if ( $row_pending ) {
        return new WP_REST_Response( array( 'success' => false, 'status' => $row_pending->status, 'message' => 'اطلاعات ثبت‌نام شما ثبت شده است، منتظر تأیید مدیر باشید.' ), 200 );
    }

    return new WP_REST_Response( array( 'success' => false, 'message' => 'لایسنس پیدا نشد.' ), 404 );
}