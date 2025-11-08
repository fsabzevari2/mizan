<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * صفحه مدیریت درخواست‌ها در پنل ادمین
 * - نمایش لیست درخواست‌ها
 * - امکانات Approve / Reject / Extend
 * - نمایش license_token برای هر رکورد فعال
 */

/* افزودن منو در بخش مدیریت */
add_action( 'admin_menu', function() {
    add_menu_page(
        'Mizan Licenses',
        'Mizan Licenses',
        'manage_options',
        'mizan-licenses',
        'mizan_admin_page',
        'dashicons-admin-network',
        56
    );
} );

/* پردازش اکشن‌های فرم (admin-post) */
add_action( 'admin_post_mizan_license_action', 'mizan_handle_admin_actions' );

function mizan_handle_admin_actions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'دسترسی غیرمجاز' );
    }

    // nonce بررسی
    if ( empty( $_POST['mizan_nonce'] ) || ! wp_verify_nonce( $_POST['mizan_nonce'], 'mizan_admin_action' ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=mizan-licenses&msg=nonce' ) );
        exit;
    }

    $action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
    $id = isset( $_POST['request_id'] ) ? intval( $_POST['request_id'] ) : 0;

    if ( $action === 'approve' && $id ) {
        $duration_days = isset( $_POST['duration_days'] ) ? intval( $_POST['duration_days'] ) : 14;
        $res = Mizan_License_DB::activate_request( $id, $duration_days );
        if ( $res ) {
            // ارسال ایمیل
            $row = Mizan_License_DB::get_request_by_id( $id );
            if ( $row && $row->user_email ) {
                mizan_send_license_email( $row->user_email, $row->first_name ?: $row->user_email, $res['license_key'], $res['license_token'], $res['expires_at'] );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=mizan-licenses&msg=approved' ) );
            exit;
        }
    }

    if ( $action === 'reject' && $id ) {
        Mizan_License_DB::reject_request( $id );
        wp_safe_redirect( admin_url( 'admin.php?page=mizan-licenses&msg=rejected' ) );
        exit;
    }

    if ( $action === 'extend' && $id ) {
        $days = isset( $_POST['extend_days'] ) ? intval( $_POST['extend_days'] ) : 30;
        Mizan_License_DB::extend_license( $id, $days );
        wp_safe_redirect( admin_url( 'admin.php?page=mizan-licenses&msg=extended' ) );
        exit;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=mizan-licenses' ) );
    exit;
}

function mizan_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'دسترسی غیرمجاز' );
    }

    $msg = isset( $_GET['msg'] ) ? sanitize_text_field( $_GET['msg'] ) : '';

    // پیغام‌ها ساده
    if ( $msg === 'approved' ) {
        echo '<div class="notice notice-success"><p>درخواست تایید و لایسنس ارسال شد.</p></div>';
    } elseif ( $msg === 'rejected' ) {
        echo '<div class="notice notice-warning"><p>درخواست رد شد.</p></div>';
    } elseif ( $msg === 'extended' ) {
        echo '<div class="notice notice-success"><p>لایسنس تمدید شد.</p></div>';
    } elseif ( $msg === 'nonce' ) {
        echo '<div class="notice notice-error"><p>خطای امنیتی (nonce) رخ داد.</p></div>';
    }

    // فیلتر وضعیت
    $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

    $requests = Mizan_License_DB::get_requests( array( 'status' => $status_filter ) );

    ?>
    <div class="wrap">
        <h1>Mizan License Requests</h1>

        <div style="margin-bottom:20px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mizan-licenses&status=pending' ) ); ?>" class="button">درخواست‌های در انتظار</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mizan-licenses&status=active' ) ); ?>" class="button">لایسنس‌های فعال</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mizan-licenses' ) ); ?>" class="button">همه</a>
        </div>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ایمیل</th>
                    <th>نام</th>
                    <th>فروشگاه</th>
                    <th>شماره</th>
                    <th>Device Hash</th>
                    <th>وضعیت</th>
                    <th>تاریخ ثبت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $requests ) : foreach ( $requests as $r ) : ?>
                    <tr>
                        <td><?php echo intval( $r->id ); ?></td>
                        <td><?php echo esc_html( $r->user_email ); ?></td>
                        <td><?php echo esc_html( $r->first_name . ' ' . $r->last_name ); ?></td>
                        <td><?php echo esc_html( $r->store_name ); ?></td>
                        <td><?php echo esc_html( $r->phone ); ?></td>
                        <td style="font-family:monospace;"><?php echo esc_html( $r->device_hash ); ?></td>
                        <td><?php echo esc_html( $r->status ); ?></td>
                        <td><?php echo esc_html( date_i18n( 'Y-m-d H:i', intval( $r->created_at ) ) ); ?></td>
                        <td>
                            <?php if ( $r->status === 'pending' ) : ?>
                                <form style="display:inline-block;" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                    <?php wp_nonce_field( 'mizan_admin_action', 'mizan_nonce' ); ?>
                                    <input type="hidden" name="action" value="mizan_license_action">
                                    <input type="hidden" name="action_type" value="approve">
                                    <input type="hidden" name="request_id" value="<?php echo intval( $r->id ); ?>">
                                    <select name="duration_days">
                                        <option value="14">14 روز (پیش‌فرض)</option>
                                        <option value="30">30 روز</option>
                                        <option value="365">1 سال</option>
                                    </select>
                                    <button class="button button-primary" type="submit">تأیید و ارسال لایسنس</button>
                                </form>

                                <form style="display:inline-block;margin-left:5px;" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                    <?php wp_nonce_field( 'mizan_admin_action', 'mizan_nonce' ); ?>
                                    <input type="hidden" name="action" value="mizan_license_action">
                                    <input type="hidden" name="action_type" value="reject">
                                    <input type="hidden" name="request_id" value="<?php echo intval( $r->id ); ?>">
                                    <button class="button button-secondary" type="submit">رد</button>
                                </form>
                            <?php else: ?>
                                <!-- برای رکوردهای فعال یا منقضی، امکان تمدید -->
                                <form style="display:inline-block;" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                    <?php wp_nonce_field( 'mizan_admin_action', 'mizan_nonce' ); ?>
                                    <input type="hidden" name="action" value="mizan_license_action">
                                    <input type="hidden" name="action_type" value="extend">
                                    <input type="hidden" name="request_id" value="<?php echo intval( $r->id ); ?>">
                                    <select name="extend_days">
                                        <option value="30">30 روز</option>
                                        <option value="365">1 سال</option>
                                        <option value="0">بدون تاریخ انقضا (دائمی)</option>
                                    </select>
                                    <button class="button" type="submit">تمدید / فعال‌سازی</button>
                                </form>
                            <?php endif; ?>

                            <?php if ( $r->status === 'active' ) : ?>
                                <div style="margin-top:8px;">
                                    <strong>لایسنس:</strong>
                                    <div style="font-family:monospace;word-break:break-all;max-width:300px;"><?php echo esc_html( $r->license_key ); ?></div>
                                    <strong>توکن:</strong>
                                    <div style="font-family:monospace;word-break:break-all;max-width:300px;"><?php echo esc_html( $r->license_token ); ?></div>
                                    <div><small>انقضا: <?php echo esc_html( Mizan_License_Manager::format_time( intval( $r->expires_at ) ) ); ?></small></div>
                                </div>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="9">درخواستی وجود ندارد.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>
    <?php
}