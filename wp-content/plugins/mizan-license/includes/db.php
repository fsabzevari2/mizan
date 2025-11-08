<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * شامل توابع پایگاه‌داده افزونه Mizan License (نسخه مقاوم‌تر)
 * - activate_request اکنون با SQL آماده‌سازی‌شده کار می‌کند و حالت expires=null را پشتیبانی می‌کند
 * - اگر update با خطا مواجه شود، در error_log پیام ثبت می‌شود
 * - تابع کمکی force_set_status برای تغییر مستقیم وضعیت اضافه شده
 */

class Mizan_License_DB {

    // ایجاد جدول اختصاصی mizan_licenses
    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_email VARCHAR(191) NOT NULL,
            username VARCHAR(100) DEFAULT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            store_name VARCHAR(191) DEFAULT NULL,
            device_hash VARCHAR(255) DEFAULT NULL,
            license_key VARCHAR(255) DEFAULT NULL,
            license_token TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending', /* pending, active, revoked, expired, rejected */
            issued_at BIGINT(20) DEFAULT NULL,
            expires_at BIGINT(20) DEFAULT NULL,
            order_id VARCHAR(100) DEFAULT NULL,
            created_at BIGINT(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY device_hash (device_hash),
            KEY status (status)
        ) {$charset_collate};";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    // درج درخواست ثبت‌نام (از REST API)
    public static function insert_request( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $now = time();

        $inserted = $wpdb->insert(
            $table,
            array(
                'user_email' => sanitize_email( $data['email'] ?? '' ),
                'username'   => sanitize_text_field( $data['username'] ?? '' ),
                'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
                'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
                'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
                'store_name' => sanitize_text_field( $data['store_name'] ?? '' ),
                'device_hash'=> sanitize_text_field( $data['device_hash'] ?? '' ),
                'status'     => 'pending',
                'created_at' => $now,
            ),
            array( '%s','%s','%s','%s','%s','%s','%s','%d' )
        );

        if ( $inserted ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    // گرفتن لیست درخواست‌ها (قابل تعیین فیلتر)
    public static function get_requests( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;

        $status = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : '';
        if ( $status ) {
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status ) );
        } else {
            $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
        }

        return $rows;
    }

    // گرفتن یک رکورد بر اساس id
    public static function get_request_by_id( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $id = intval( $id );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    // فعال‌سازی: ایجاد license_key، license_token و تغییر status به active
    public static function activate_request( $id, $duration_days = 14 ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $now = time();
        $expires = $duration_days > 0 ? $now + ( $duration_days * 24 * 60 * 60 ) : null;

        // گرفتن ردیف
        $row = self::get_request_by_id( $id );
        if ( ! $row ) {
            error_log( "Mizan License: activate_request - row not found for id={$id}" );
            return false;
        }

        // تولید کلید لایسنس
        $license_key = Mizan_License_Manager::generate_license_key();

        // payload برای JWT
        $payload = array(
            'license_key' => $license_key,
            'device_hash' => $row->device_hash ?: '',
            'issued_at'   => $now,
            'expires_at'  => $expires,
            'email'       => $row->user_email,
            'request_id'  => intval( $row->id )
        );

        // تلاش برای تولید JWT امضاشده
        $license_token = Mizan_License_Manager::generate_jwt( $payload );

        // اگر تولید JWT ناموفق بود، fallback به توکن unsigned (تا کلاینت حداقل payload را ببیند)
        if ( empty( $license_token ) ) {
            $header = array( 'alg' => 'none', 'typ' => 'JWT' );
            $header_b64 = self::base64url_encode( wp_json_encode( $header ) );
            $payload_b64 = self::base64url_encode( wp_json_encode( $payload ) );
            $license_token = $header_b64 . '.' . $payload_b64 . '.';
            error_log( "Mizan License: generate_jwt failed for id={$id}, using unsigned token fallback." );
        }

        // ساختن SQL ایمن برای آپدیت؛ اگر expires null باشد از NULL استفاده می‌کنیم
        if ( $expires === null ) {
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET license_key = %s, license_token = %s, status = %s, issued_at = %d, expires_at = NULL WHERE id = %d",
                $license_key, $license_token, 'active', $now, intval( $id )
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET license_key = %s, license_token = %s, status = %s, issued_at = %d, expires_at = %d WHERE id = %d",
                $license_key, $license_token, 'active', $now, intval( $expires ), intval( $id )
            );
        }

        $res = $wpdb->query( $sql );

        if ( $res === false ) {
            error_log( "Mizan License: DB update failed on activate_request for id={$id}. SQL: {$sql}" );
            return false;
        }

        // اگر بروزرسانی انجام شد یا حتی 0 (یعنی مقادیر قبل برابر بوده) برمی‌گردانیم جزئیات
        return array(
            'license_key'  => $license_key,
            'license_token'=> $license_token,
            'issued_at'    => $now,
            'expires_at'   => $expires,
        );
    }

    // رد کردن درخواست
    public static function reject_request( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;

        $sql = $wpdb->prepare( "UPDATE {$table} SET status = %s WHERE id = %d", 'rejected', intval( $id ) );
        $res = $wpdb->query( $sql );
        if ( $res === false ) {
            error_log( "Mizan License: reject_request failed for id={$id}" );
            return false;
        }
        return true;
    }

    // تغییر وضعیت مستقیم (کمک‌کننده)
    public static function force_set_status( $id, $status ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $sql = $wpdb->prepare( "UPDATE {$table} SET status = %s WHERE id = %d", sanitize_text_field($status), intval($id) );
        $res = $wpdb->query( $sql );
        if ( $res === false ) {
            error_log( "Mizan License: force_set_status failed for id={$id}, status={$status}" );
            return false;
        }
        return true;
    }

    // گرفتن لایسنس فعال بر اساس device_hash یا email
    public static function get_active_by_device_or_email( $device_hash = '', $email = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $where = array();
        if ( $device_hash ) {
            $where[] = $wpdb->prepare( "device_hash = %s", $device_hash );
        }
        if ( $email ) {
            $where[] = $wpdb->prepare( "user_email = %s", $email );
        }
        if ( empty( $where ) ) {
            return null;
        }
        $where_sql = implode( ' OR ', $where );
        $row = $wpdb->get_row( "SELECT * FROM {$table} WHERE ( {$where_sql} ) AND status = 'active' LIMIT 1" );
        if ( $row ) {
            // بررسی منقضی شدن در سمت سرور
            if ( $row->expires_at && time() > intval( $row->expires_at ) ) {
                // اگر منقضی شده، بروزرسانی وضعیت
                $wpdb->update( $table, array( 'status' => 'expired' ), array( 'id' => $row->id ), array( '%s' ), array( '%d' ) );
                return null;
            }
        }
        return $row;
    }

    // تمدید دستی لایسنس یا فعال‌سازی دائمی (expires_at می‌تواند null برای دائمی باشد)
    public static function extend_license( $id, $additional_days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $row = self::get_request_by_id( $id );
        if ( ! $row ) return false;

        $now = time();
        $new_expires = $row->expires_at ? intval( $row->expires_at ) + ( $additional_days * 24 * 60 * 60 ) : ( $now + ( $additional_days * 24 * 60 * 60 ) );
        if ( $additional_days === 0 ) {
            // دائمی
            $sql = $wpdb->prepare( "UPDATE {$table} SET expires_at = NULL, status = %s WHERE id = %d", 'active', intval( $id ) );
        } else {
            $sql = $wpdb->prepare( "UPDATE {$table} SET expires_at = %d, status = %s WHERE id = %d", intval( $new_expires ), 'active', intval( $id ) );
        }

        $res = $wpdb->query( $sql );
        if ( $res === false ) {
            error_log( "Mizan License: extend_license failed for id={$id}" );
            return false;
        }
        return true;
    }

    // برگرداندن رکورد بر اساس license_key
    public static function get_by_license_key( $license_key ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE license_key = %s LIMIT 1", $license_key ) );
    }

    // helper: base64url encode/decode (برای ساخت توکن unsigned)
    private static function base64url_encode( $data ) {
        $b64 = base64_encode( $data );
        $b64 = str_replace( array('+','/','='), array('-','_',''), $b64 );
        return rtrim( $b64, '=' );
    }

    private static function base64url_decode( $data ) {
        $remainder = strlen( $data ) % 4;
        if ( $remainder ) {
            $padlen = 4 - $remainder;
            $data .= str_repeat( '=', $padlen );
        }
        $b64 = str_replace( array('-','_'), array('+','/'), $data );
        return base64_decode( $b64 );
    }

}