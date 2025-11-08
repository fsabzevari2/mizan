<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * توابع ارسال ایمیل لایسنس
 */

// ارسال ایمیل لایسنس به کاربر پس از فعال‌سازی (حاوی license_key و license_token)
function mizan_send_license_email( $email, $first_name, $license_key, $license_token, $expires_at ) {
    $subject = 'کلید فعال‌سازی برنامه میزان - اطلاعات لایسنس';
    $expires_text = $expires_at ? Mizan_License_Manager::format_time( $expires_at ) : __('بدون تاریخ انقضا','mizan-license');
    $message = "سلام " . esc_html( $first_name ) . " عزیز,\n\n";
    $message .= "لایسنس برنامه میزان برای شما ایجاد شد.\n\n";
    $message .= "کلید لایسنس: " . esc_html( $license_key ) . "\n";
    $message .= "توکن لایسنس (برای اپ):\n" . esc_html( $license_token ) . "\n\n";
    $message .= "تاریخ شروع: " . Mizan_License_Manager::format_time( time() ) . "\n";
    $message .= "تاریخ انقضا: " . esc_html( $expires_text ) . "\n\n";
    $message .= "برای فعال‌سازی برنامه، این توکن را در اپ وارد کنید (یا اپ باید آن را از سرور دریافت کند).\n\n";
    $message .= "با احترام\nمدیریت سایت " . get_bloginfo( 'name' );

    // استفاده از wp_mail (فرض بر این است که WP Mail یا SMTP تنظیم شده است)
    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    wp_mail( $email, $subject, $message, $headers );
}