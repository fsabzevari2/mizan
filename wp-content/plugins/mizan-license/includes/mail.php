<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * توابع ارسال ایمیل لایسنس و اطلاع‌رسانی رد
 * - mizan_send_license_email : ارسال لایسنس پس از فعال‌سازی
 * - mizan_send_reject_email  : ارسال پیام اطلاع‌رسانی پس از رد درخواست
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

    // استفاده از wp_mail (WP Mail یا SMTP باید پیکربندی شده باشد)
    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    wp_mail( $email, $subject, $message, $headers );
}

// ارسال ایمیل اطلاع‌رسانی رد درخواست (با متن دلخواه یا پیش‌فرض)
function mizan_send_reject_email( $email, $first_name, $message_text ) {
    $subject = 'اطلاع‌رسانی درخواست برنامه میزان';
    $message = "سلام " . esc_html( $first_name ) . " عزیز,\n\n";
    $message .= "پیام مدیریت در رابطه با درخواست شما:\n\n";
    $message .= esc_html( $message_text ) . "\n\n";
    $message .= "در صورت نیاز به توضیحات بیشتر با پشتیبانی تماس بگیرید.\n\n";
    $message .= "با احترام\nمدیریت سایت " . get_bloginfo( 'name' );

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    wp_mail( $email, $subject, $message, $headers );
}