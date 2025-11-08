<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاس دیتابیس افزونه
 * - ایجاد جدول
 * - درج درخواست
 * - پاسخ‌دهی به admin UI و api
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

    // فعال‌سازی: ایجاد license_key، issued_at، expires_at و تغییر status
    public static function activate_request( $id, $duration_days = 14 ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $now = time();
        $expires = $now + ( $duration_days * 24 * 60 * 60 );

        $license_key = Mizan_License_Manager::generate_license_key();

        $updated = $wpdb->update(
            $table,
            array(
                'license_key' => $license_key,
                'status'      => 'active',
                'issued_at'   => $now,
                'expires_at'  => $expires,
            ),
            array( 'id' => intval( $id ) ),
            array( '%s','%s','%d','%d' ),
            array( '%d' )
        );

        if ( $updated !== false ) {
            return array(
                'license_key' => $license_key,
                'issued_at'   => $now,
                'expires_at'  => $expires,
            );
        }

        return false;
    }

    // رد کردن درخواست
    public static function reject_request( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . MIZAN_LICENSE_TABLE;
        $updated = $wpdb->update(
            $table,
            array( 'status' => 'rejected' ),
            array( 'id' => intval( $id ) ),
            array( '%s' ),
            array( '%d' )
        );

        return $updated !== false;
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

        $updated = $wpdb->update(
            $table,
            array(
                'expires_at' => $new_expires,
                'status'     => 'active',
            ),
            array( 'id' => intval( $id ) ),
            array( '%d','%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

}