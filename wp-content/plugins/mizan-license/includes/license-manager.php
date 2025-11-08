<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * مدیر لایسنس:
 * - تولید کلید لایسنس
 * - تولید JWT امضاشده (RS256)
 * - اعتبارسنجی JWT (برای استفاده سروری یا تست)
 *
 * توجه: برای امنیت بیشتر، private.pem را خارج از webroot قرار دهید و مسیر MIZAN_LICENSE_PRIVATE_KEY_PATH را به آن اشاره کنید.
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

    // تولید JWT امضاشده با RS256
    public static function generate_jwt( $payload ) {
        // header
        $header = array( 'alg' => 'RS256', 'typ' => 'JWT' );
        $header_b64 = self::base64url_encode( wp_json_encode( $header ) );
        $payload_b64 = self::base64url_encode( wp_json_encode( $payload ) );
        $data = $header_b64 . '.' . $payload_b64;

        // خواندن کلید خصوصی
        $private_key_path = defined( 'MIZAN_LICENSE_PRIVATE_KEY_PATH' ) ? MIZAN_LICENSE_PRIVATE_KEY_PATH : ( MIZAN_LICENSE_PLUGIN_DIR . 'keys/private.pem' );
        if ( ! file_exists( $private_key_path ) ) {
            error_log( 'Mizan License: private key not found at ' . $private_key_path );
            return null;
        }
        $private_key = file_get_contents( $private_key_path );
        $pkey_resource = openssl_pkey_get_private( $private_key );
        if ( ! $pkey_resource ) {
            error_log( 'Mizan License: invalid private key.' );
            return null;
        }

        // امضا با SHA256
        $signature = '';
        $ok = openssl_sign( $data, $signature, $pkey_resource, OPENSSL_ALGO_SHA256 );
        openssl_free_key( $pkey_resource );
        if ( ! $ok ) {
            error_log( 'Mizan License: signing failed.' );
            return null;
        }

        $sig_b64 = self::base64url_encode( $signature );
        return $data . '.' . $sig_b64;
    }

    // اعتبارسنجی JWT با کلید عمومی (می‌تواند برای endpoint validate استفاده شود)
    public static function verify_jwt( $jwt, $public_key_path = null ) {
        if ( ! $jwt ) return false;
        $parts = explode( '.', $jwt );
        if ( count( $parts ) !== 3 ) return false;

        list( $header_b64, $payload_b64, $sig_b64 ) = $parts;
        $data = $header_b64 . '.' . $payload_b64;
        $signature = self::base64url_decode( $sig_b64 );

        if ( ! $public_key_path ) {
            $public_key_path = defined( 'MIZAN_LICENSE_PUBLIC_KEY_PATH' ) ? MIZAN_LICENSE_PUBLIC_KEY_PATH : ( MIZAN_LICENSE_PLUGIN_DIR . 'keys/public.pem' );
        }
        if ( ! file_exists( $public_key_path ) ) {
            error_log( 'Mizan License: public key not found at ' . $public_key_path );
            return false;
        }
        $public_key = file_get_contents( $public_key_path );
        $pkey_resource = openssl_pkey_get_public( $public_key );
        if ( ! $pkey_resource ) {
            error_log( 'Mizan License: invalid public key.' );
            return false;
        }

        $ok = openssl_verify( $data, $signature, $pkey_resource, OPENSSL_ALGO_SHA256 );
        openssl_free_key( $pkey_resource );
        if ( $ok === 1 ) {
            // بررسی expiry داخل payload
            $payload_json = json_decode( self::base64url_decode( $payload_b64 ), true );
            if ( isset( $payload_json['expires_at'] ) && $payload_json['expires_at'] ) {
                if ( time() > intval( $payload_json['expires_at'] ) ) {
                    return false; // منقضی شده
                }
            }
            return $payload_json;
        }

        return false;
    }

    // base64url encode/decode برای JWT
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

    // فرمت نمایش تاریخ قابل خواندن
    public static function format_time( $timestamp ) {
        if ( ! $timestamp ) return '';
        return date_i18n( 'Y-m-d H:i:s', $timestamp );
    }
}