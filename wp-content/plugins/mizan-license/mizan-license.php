<?php
/**
 * Plugin Name: Mizan License Manager
 * Plugin URI:  https://cofeclick.ir/
 * Description: افزونه مدیریت درخواست‌های ثبت‌نام و تولید لایسنس ۱۴ روزه برای اپ میزان — با JWT امضاشده (RS256)
 * Version:     1.1.0
 * Author:      fsabzevari2
 * Text Domain: mizan-license
 *
 * توجه امنیتی مهم:
 * - فایل keys/private.pem حاوی کلید خصوصی است. حتماً پس از آپلود آن را به مسیر امن خارج از webroot منتقل کنید و مسیر فایل را در includes/license-manager.php تنظیم کنید.
 * - public.pem (کلید عمومی) را در اپ (lib/src/core/security/public.pem) قرار دهید برای اعتبارسنجی آفلاین.
 */

// امنیت پایه
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ثابت‌ها
define( 'MIZAN_LICENSE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MIZAN_LICENSE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MIZAN_LICENSE_TABLE', 'mizan_licenses' ); // نام جدول (با prefix در زمان اجرا ترکیب می‌شود)

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
    // در حال حاضر کاری نمی‌کنیم تا رکوردها حفظ شوند
}

// بارگذاری متن‌های ترجمه در صورت نیاز
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'mizan-license', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
});