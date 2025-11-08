<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * صفحه مدیریت درخواست‌ها در پنل ادمین — نسخهٔ پیشرفته
 * - فیلتر بر اساس وضعیت / جستجو / کلیک روی ستون‌ها برای فیلتر خاص
 * - مودالِ رد با ارسال پیام پیش‌فرض یا متن دلخواه
 * - طراحی ساده و مرتب برای نمایش بهتر
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

    // تأیید عادی (با انتخاب مدت)
    if ( $action === 'approve' && $id ) {
        $duration_days = isset( $_POST['duration_days'] ) ? intval( $_POST['duration_days'] ) : 14;
        $res = Mizan_License_DB::activate_request( $id, $duration_days );
        if ( $res ) {
            // ارسال ایمیل فعال‌سازی
            $row = Mizan_License_DB::get_request_by_id( $id );
            if ( $row && $row->user_email ) {
                mizan_send_license_email( $row->user_email, $row->first_name ?: $row->user_email, $res['license_key'], $res['license_token'], $res['expires_at'] );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=mizan-licenses&msg=approved' ) );
            exit;
        }
    }

    // تأیید سریع به‌صورت تست 14 روزه (یک کلیک)
    if ( $action === 'approve_trial' && $id ) {
        $duration_days = 14;
        $res = Mizan_License_DB::activate_request( $id, $duration_days );
        if ( $res ) {
            $row = Mizan_License_DB::get_request_by_id( $id );
            if ( $row && $row->user_email ) {
                mizan_send_license_email( $row->user_email, $row->first_name ?: $row->user_email, $res['license_key'], $res['license_token'], $res['expires_at'] );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=mizan-licenses&msg=approved_trial' ) );
            exit;
        }
    }

    // رد کردن درخواست (با ارسال پیام دلخواه یا پیش‌فرض)
    if ( $action === 'reject' && $id ) {
        // پیام دلخواه از فرم
        $reject_message = isset( $_POST['reject_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reject_message'] ) ) : '';
        // انجام عملیات رد در DB
        Mizan_License_DB::reject_request( $id );
        // ارسال ایمیل اطلاع‌رسانی رد (در صورت وجود ایمیل)
        $row = Mizan_License_DB::get_request_by_id( $id );
        if ( $row && $row->user_email ) {
            // اگر پیام خالی بود یک متن پیش‌فرض قرار بده
            if ( empty( $reject_message ) ) {
                $reject_message = "درخواست شما بررسی شد و متأسفانه تأیید نگردید. برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.";
            }
            mizan_send_reject_email( $row->user_email, $row->first_name ?: $row->user_email, $reject_message );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=mizan-licenses&msg=rejected' ) );
        exit;
    }

    // تمدید دستی لایسنس یا فعال‌سازی دائمی
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

    // پیام‌های اطلاع‌رسانی
    if ( $msg === 'approved' ) {
        echo '<div class="notice notice-success"><p>درخواست تأیید شد و لایسنس ارسال شد.</p></div>';
    } elseif ( $msg === 'approved_trial' ) {
        echo '<div class="notice notice-success"><p>تست ۱۴ روزه فعال شد و لایسنس ارسال شد.</p></div>';
    } elseif ( $msg === 'rejected' ) {
        echo '<div class="notice notice-warning"><p>درخواست رد شد و ایمیل اطلاع‌رسانی ارسال شد.</p></div>';
    } elseif ( $msg === 'extended' ) {
        echo '<div class="notice notice-success"><p>لایسنس تمدید شد.</p></div>';
    } elseif ( $msg === 'nonce' ) {
        echo '<div class="notice notice-error"><p>خطای امنیتی (nonce) رخ داد.</p></div>';
    }

    // پارامترهای فیلتر (از GET)
    $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
    $search_term = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
    $filter_field = isset( $_GET['filter_field'] ) ? sanitize_text_field( $_GET['filter_field'] ) : '';
    $filter_value = isset( $_GET['filter_value'] ) ? sanitize_text_field( $_GET['filter_value'] ) : '';

    // گرفتن رکوردها از DB
    $requests = Mizan_License_DB::get_requests( array( 'status' => $status_filter ) );

    // اعمال فیلترهای client-side ساده (نام، ایمیل، شماره، store, device_hash)
    if ( $filter_field && $filter_value ) {
        $requests = array_filter( $requests, function( $r ) use ( $filter_field, $filter_value ) {
            $field = '';
            switch ( $filter_field ) {
                case 'email': $field = $r->user_email; break;
                case 'name': $field = trim( $r->first_name . ' ' . $r->last_name ); break;
                case 'phone': $field = $r->phone; break;
                case 'store': $field = $r->store_name; break;
                case 'device': $field = $r->device_hash; break;
                default: $field = ''; break;
            }
            if ( $field === null ) return false;
            return mb_stripos( $field, $filter_value ) !== false;
        } );
    }

    // اعمال جستجوی عمومی
    if ( $search_term ) {
        $requests = array_filter( $requests, function( $r ) use ( $search_term ) {
            $hay = implode( ' ', array( $r->user_email, $r->first_name, $r->last_name, $r->phone, $r->store_name, $r->device_hash ) );
            return mb_stripos( $hay, $search_term ) !== false;
        } );
    }

    // برای نگهداری پارامترها در فرم‌ها
    $current_page_url = esc_url( admin_url( 'admin.php?page=mizan-licenses' ) );
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-admin-network" style="font-size:22px;color:#0073aa"></span>
            <span>Mizan License Requests</span>
        </h1>

        <!-- فیلترها و جستجو -->
        <div style="display:flex;gap:12px;margin:14px 0;align-items:center;">
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="page" value="mizan-licenses">
                <select name="status" style="padding:6px 8px;border-radius:6px;border:1px solid #ddd;">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending" <?php selected( $status_filter, 'pending' ); ?>>در انتظار</option>
                    <option value="active" <?php selected( $status_filter, 'active' ); ?>>فعال</option>
                    <option value="rejected" <?php selected( $status_filter, 'rejected' ); ?>>رد شده</option>
                    <option value="expired" <?php selected( $status_filter, 'expired' ); ?>>منقضی</option>
                </select>
                <input type="text" name="s" placeholder="جستجو (ایمیل/نام/فروشگاه...)" value="<?php echo esc_attr( $search_term ); ?>" style="padding:6px 8px;border-radius:6px;border:1px solid #ddd;min-width:300px;">
                <button class="button" type="submit">اعمال</button>
                <a href="<?php echo $current_page_url; ?>" class="button" style="margin-left:8px;">پاکسازی</a>
            </form>

            <!-- راهنمای فیلتر با کلیک -->
            <div style="margin-left:auto;color:#666;font-size:13px;">
                برای فیلتر سریع روی ایمیل/نام/شماره کلیک کنید.
            </div>
        </div>

        <!-- جدول درخواست‌ها -->
        <table class="widefat fixed striped" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>ایمیل</th>
                    <th>نام</th>
                    <th>فروشگاه</th>
                    <th>شماره</th>
                    <th>Device Hash</th>
                    <th>وضعیت</th>
                    <th>تاریخ ثبت</th>
                    <th style="width:300px;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $requests ) : foreach ( $requests as $r ) : 
                    // استانداردسازی وضعیت برای نمایش
                    $status_raw = isset( $r->status ) ? (string) $r->status : '';
                    if ( $status_raw === '0' || $status_raw === '' ) {
                        $display_status = 'pending';
                    } else {
                        $display_status = $status_raw;
                    }
                    ?>
                    <tr>
                        <td><?php echo intval( $r->id ); ?></td>

                        <!-- کلیک‌پذیر: ایمیل -->
                        <td>
                            <a href="<?php echo esc_url( add_query_arg( array( 'page'=>'mizan-licenses','filter_field'=>'email','filter_value'=>$r->user_email ), admin_url('admin.php') ) ); ?>">
                                <?php echo esc_html( $r->user_email ); ?>
                            </a>
                        </td>

                        <!-- کلیک‌پذیر: نام -->
                        <td>
                            <a href="<?php echo esc_url( add_query_arg( array( 'page'=>'mizan-licenses','filter_field'=>'name','filter_value'=>trim($r->first_name . ' ' . $r->last_name) ), admin_url('admin.php') ) ); ?>">
                                <?php echo esc_html( $r->first_name . ' ' . $r->last_name ); ?>
                            </a>
                        </td>

                        <td>
                            <a href="<?php echo esc_url( add_query_arg( array( 'page'=>'mizan-licenses','filter_field'=>'store','filter_value'=>$r->store_name ), admin_url('admin.php') ) ); ?>">
                                <?php echo esc_html( $r->store_name ); ?>
                            </a>
                        </td>

                        <!-- کلیک‌پذیر: شماره -->
                        <td>
                            <a href="<?php echo esc_url( add_query_arg( array( 'page'=>'mizan-licenses','filter_field'=>'phone','filter_value'=>$r->phone ), admin_url('admin.php') ) ); ?>">
                                <?php echo esc_html( $r->phone ); ?>
                            </a>
                        </td>

                        <td style="font-family:monospace;word-break:break-all;">
                            <a href="<?php echo esc_url( add_query_arg( array( 'page'=>'mizan-licenses','filter_field'=>'device','filter_value'=>$r->device_hash ), admin_url('admin.php') ) ); ?>">
                                <?php echo esc_html( $r->device_hash ); ?>
                            </a>
                        </td>

                        <td><?php echo esc_html( $display_status ); ?></td>
                        <td><?php echo esc_html( date_i18n( 'Y-m-d H:i', intval( $r->created_at ) ) ); ?></td>

                        <td>
                            <?php if ( $display_status === 'pending' || $display_status === 'rejected' ) : ?>
                                <!-- تأیید با انتخاب مدت -->
                                <form style="display:inline-block;margin-bottom:6px;" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                    <?php wp_nonce_field( 'mizan_admin_action', 'mizan_nonce' ); ?>
                                    <input type="hidden" name="action" value="mizan_license_action">
                                    <input type="hidden" name="action_type" value="approve">
                                    <input type="hidden" name="request_id" value="<?php echo intval( $r->id ); ?>">
                                    <select name="duration_days" aria-label="مدت لایسنس">
                                        <option value="14">14 روز</option>
                                        <option value="30">30 روز</option>
                                        <option value="365">1 سال</option>
                                    </select>
                                    <button class="button button-primary" type="submit" style="margin-left:6px;">تأیید و ارسال لایسنس</button>
                                </form>

                                <!-- دکمهٔ تست 14 روزه -->
                                <form style="display:inline-block;margin-bottom:6px;" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                    <?php wp_nonce_field( 'mizan_admin_action', 'mizan_nonce' ); ?>
                                    <input type="hidden" name="action" value="mizan_license_action">
                                    <input type="hidden" name="action_type" value="approve_trial">
                                    <input type="hidden" name="request_id" value="<?php echo intval( $r->id ); ?>">
                                    <button class="button" type="submit" style="background:#0B6E4F;color:#fff;border:none;padding:6px 10px;margin-left:6px;">تست ۱۴ روزه</button>
                                </form>

                                <!-- دکمهٔ رد (باز کردن مودال برای انتخاب پیام) -->
                                <button class="button button-secondary" style="margin-left:6px;" onclick="openRejectModal(<?php echo intval($r->id); ?>, '<?php echo esc_js($r->user_email); ?>', '<?php echo esc_js(trim($r->first_name . ' ' . $r->last_name)); ?>')">رد</button>

                            <?php else: ?>
                                <!-- اگر فعال یا منقضی -->
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

                            <?php if ( $display_status === 'active' ) : ?>
                                <div style="margin-top:8px;">
                                    <strong>لایسنس:</strong>
                                    <div style="font-family:monospace;word-break:break-all;max-width:300px;"><?php echo esc_html( $r->license_key ); ?></div>
                                    <strong>توکن:</strong>
                                    <div style="font-family:monospace;word-break:break-all;max-width:300px;"><?php echo esc_html( $r->license_token ); ?></div>
                                    <div><small>انقضا: <?php echo esc_html( Mizan_License_Manager::format_time( intval( $r->expires_at ) ) ); ?></small></div>
                                </div>
                            <?php elseif ( $display_status === 'rejected' ) : ?>
                                <div style="margin-top:8px;color:#a00;"><strong>این درخواست رد شده است.</strong></div>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="9">درخواستی وجود ندارد.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- مودال رد — فرم مشترک برای ارسال reject با پیام -->
        <div id="mizan-reject-modal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;">
            <div style="max-width:700px;margin:80px auto;background:#fff;border-radius:8px;padding:18px;box-shadow:0 6px 24px rgba(0,0,0,0.2);">
                <h2 style="margin-top:0;">رد درخواست و ارسال پیام</h2>
                <p id="mizan-reject-info" style="color:#333;margin-bottom:8px;">اطلاعات کاربر</p>

                <form id="mizan-reject-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                    <?php wp_nonce_field( 'mizan_admin_action', 'mizan_nonce' ); ?>
                    <input type="hidden" name="action" value="mizan_license_action">
                    <input type="hidden" name="action_type" value="reject">
                    <input type="hidden" id="mizan_reject_request_id" name="request_id" value="">
                    <div style="margin-bottom:8px;">
                        <label><input type="radio" name="reject_mode" value="default" checked> ارسال پیام پیش‌فرض</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="reject_mode" value="custom"> نوشتن پیام دلخواه</label>
                    </div>

                    <div id="mizan-reject-default" style="margin-bottom:8px;">
                        <div style="background:#f7f7f7;padding:10px;border-radius:6px;color:#333;">
                            پیام پیش‌فرض: "درخواست شما بررسی شد و متأسفانه تأیید نگردید. برای اطلاعات بیشتر با پشتیبانی تماس بگیرید."
                        </div>
                    </div>

                    <div id="mizan-reject-custom" style="display:none;margin-bottom:8px;">
                        <textarea name="reject_message" id="mizan_reject_message" rows="6" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;" placeholder="پیام دلخواه برای کاربر..."></textarea>
                    </div>

                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
                        <button type="button" class="button" onclick="closeRejectModal()">انصراف</button>
                        <button type="submit" class="button button-primary">ارسال و رد</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
    // JS ساده برای کنترل مودال و نمایش فرم پیام دلخواه
    function openRejectModal(requestId, email, name) {
        document.getElementById('mizan_reject_request_id').value = requestId;
        var info = 'ID: ' + requestId + ' — ' + (name ? name + ' / ' : '') + (email ? email : '');
        document.getElementById('mizan-reject-info').innerText = info;
        document.getElementById('mizan-reject-modal').style.display = 'block';
        // reset to default
        document.querySelector('input[name="reject_mode"][value="default"]').checked = true;
        document.getElementById('mizan-reject-default').style.display = 'block';
        document.getElementById('mizan-reject-custom').style.display = 'none';
        document.getElementById('mizan_reject_message').value = '';
    }

    function closeRejectModal() {
        document.getElementById('mizan-reject-modal').style.display = 'none';
    }

    // نمایش textarea اگر گزینه custom انتخاب شد
    document.addEventListener('click', function(e){
        if (e.target && (e.target.name === 'reject_mode')) {
            var val = e.target.value;
            if (val === 'custom') {
                document.getElementById('mizan-reject-default').style.display = 'none';
                document.getElementById('mizan-reject-custom').style.display = 'block';
            } else {
                document.getElementById('mizan-reject-default').style.display = 'block';
                document.getElementById('mizan-reject-custom').style.display = 'none';
            }
        }
    });

    // بستن مودال با Esc
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            closeRejectModal();
        }
    });
    </script>

    <?php
}