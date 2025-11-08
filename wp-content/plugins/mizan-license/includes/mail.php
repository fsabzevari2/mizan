<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * توابع ارسال ایمیل لایسنس
 */

// ارسال ایمیل لایسنس به کاربر پس از فعال‌سازی
function mizan_send_license_email( $email, $first_name, $license_key, $expires_at ) {
    $subject = 'کلید فعال‌سازی برنامه میزان - تاریخ انقضا و توضیحات';
    $expires_text = $expires_at ? Mizan_License_Manager::format_time( $expires_at ) : __('بدون تاریخ انقضا','mizan-license');
    $message = "سلام " . esc_html( $first_name ) . " عزیز,\n\n";
    $message .= "کلید فعال‌سازی برنامه میزان برای شما تولید شد:\n\n";
    $message .= "کلید لایسنس: " . esc_html( $license_key ) . "\n";
    $message .= "تاریخ شروع: " . Mizan_License_Manager::format_time( time() ) . "\n";
    $message .= "تاریخ انقضا: " . esc_html( $expires_text ) . "\n\n";
    $message .= "لطفاً این کلید را در اپ وارد کنید تا برنامه فعال شود. در صورت نیاز به تمدید یا سوال با پشتیبانی تماس بگیرید.\n\n";
    $message .= "با احترام\nمدیریت سایت " . get_bloginfo( 'name' );

    // استفاده از wp_mail (فرض بر این است که WP Mail یا SMTP تنظیم شده است)
    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    wp_mail( $email, $subject, $message, $headers );
}