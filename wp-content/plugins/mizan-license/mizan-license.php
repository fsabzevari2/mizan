<?php
/**
 * Plugin Name: Mizan License Manager
 * Plugin URI:  https://cofeclick.ir/
 * Description: افزونه مدیریت درخواست‌های ثبت‌نام و تولید لایسنس ۱۴ روزه با JWT امضاشده (RS256) برای اپ میزان.
 * Version:     1.1.0
 * Author:      fsabzevari2
 * Text Domain: mizan-license
 *
 * توجه امنیتی: فایل private.pem (کلید خصوصی) را به محلی خارج از webroot منتقل کنید و مسیر را در MIZAN_LICENSE_PRIVATE_KEY_PATH تنظیم کنید.
 */

// امنیت پایه
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ثابت‌ها
define( 'MIZAN_LICENSE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MIZAN_LICENSE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MIZAN_LICENSE_TABLE', 'mizan_licenses' ); // نام جدول (با prefix در زمان اجرا ترکیب می‌شود)

// مسیر کلید خصوصی پیش‌فرض داخل پوشه افزونه (توصیه: پس از قرار دادن، این مسیر را به یک مسیر امن خارج از public_html تغییر دهید)
if ( ! defined( 'MIZAN_LICENSE_PRIVATE_KEY_PATH' ) ) {
    define( 'MIZAN_LICENSE_PRIVATE_KEY_PATH', MIZAN_LICENSE_PLUGIN_DIR . 'keys/private.pem' );
}

// مسیر کلید عمومی که برای ارسال به اپ استفاده می‌شود (فایل public.pem داخل افزونه)
define( 'MIZAN_LICENSE_PUBLIC_KEY_PATH', MIZAN_LICENSE_PLUGIN_DIR . 'keys/public.pem' );

// لود فایل‌های مورد نیاز
require_once MIZAN_LICENSE_PLUGIN_DIR . 'includes/db.php';
require_once MIZAN_LICENSE_PLUGIN_DIR . 'includes/api.php';
require_once MIZAN_LICENSE_PLUGIN_DIR . 'includes/license-manager.php';
require_once MIZAN_LICENSE_PLUGIN_DIR . 'includes/mail.php';
require_once MIZAN_LICENSE_PLUGIN_DIR . 'includes/admin-ui.php';

// Activation hook: ساخت جدول در دیتابیس وردپرس
register_activation_hook( __FILE__, 'mizan_license_activate' );
function mizan_license_activate() {
    Mizan_License_DB::create_tables();
}

// Deactivation (در صورت نیاز می‌توان جدول را حذف نکرد)
register_deactivation_hook( __FILE__, 'mizan_license_deactivate' );
function mizan_license_deactivate() {
    // در حال حاضر کاری نمیکنیم تا رکوردها حفظ شوند
}

// بارگذاری متن‌های ترجمه در صورت نیاز
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'mizan-license', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
});