<?php
/**
 * AUTH / ЛОГИН / РЕГИСТРАЦИЯ / ПРОФИЛ
 * Този файл съдържа:
 * - [kravo_login] – фронтенд форма за логин
 * - [kravo_set_password] – задаване на парола
 * - [kr_register_donor], [kr_register_seeker] – форми за регистрация
 * - [kr_register] – обща страница с превключване между двете форми
 * - [kr_profile] – табло на потребителя
 */

/**
 * Проверка за сила на парола – минимум:
 * - 8 символа
 * - поне една малка буква
 * - поне една голяма буква
 * - поне една цифра
 */
function kr_check_password_strength( $pass ) {
    if ( strlen( $pass ) < 8 ) {
        return 'Паролата трябва да е поне 8 символа.';
    }
    if ( ! preg_match( '/[a-z]/', $pass ) ) {
        return 'Паролата трябва да съдържа поне една малка буква.';
    }
    if ( ! preg_match( '/[A-Z]/', $pass ) ) {
        return 'Паролата трябва да съдържа поне една главна буква.';
    }
    if ( ! preg_match( '/[0-9]/', $pass ) ) {
        return 'Паролата трябва да съдържа поне една цифра.';
    }
    return '';
}

/**
 * Генериране на user_login от имейл,
 * ако се наложи да се избегне дублиране.
 */
function kr_generate_login_from_email( $email ) {
    $base  = sanitize_user( current( explode( '@', $email ) ), true );
    if ( ! $base ) {
        $base = 'user';
    }
    $login = $base;
    $i     = 1;
    while ( username_exists( $login ) ) {
        $login = $base . $i;
        $i++;
    }
    return $login;
}

// ===============================
// LOGIN SHORTCODE [kravo_login]
// ===============================
function kravo_login_form_shortcode() {

    if ( is_user_logged_in() ) {
        return '<div class="kr-info">Вече сте логнати. <a href="' . esc_url( site_url('/dashboard') ) . '">Към таблото</a></div>';
    }

    ob_start();

    if (
        isset($_POST['kravo_login_nonce']) &&
        wp_verify_nonce($_POST['kravo_login_nonce'], 'kravo_login_action')
    ) {

        $creds = array();
        $creds['user_login']    = sanitize_email( wp_unslash( $_POST['kravo_login_email'] ?? '' ) );
        $creds['user_password'] = $_POST['kravo_login_password'] ?? '';
        $creds['remember']      = true;

        $user = wp_signon($creds, false);

        if ( is_wp_error($user) ) {
            echo '<div class="kr-error">Грешен имейл или парола.</div>';
        } else {
            $redirect_url = apply_filters(
                'kravo_login_redirect',
                site_url('/dashboard'),
                $user
            );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }
    ?>

    <div class="kravo-login-wrapper">
        <form method="post" class="kravo-login-form">
            <h2>Вход в системата</h2>

            <label for="kravo_login_email">Имейл</label>
            <input type="email" id="kravo_login_email" name="kravo_login_email" required />

            <label for="kravo_login_password">Парола</label>
            <input type="password" id="kravo_login_password" name="kravo_login_password" required />

            <?php wp_nonce_field('kravo_login_action', 'kravo_login_nonce'); ?>

            <button type="submit" class="kr-button kr-button-primary">Вход</button>

            <div class="kravo-links">
                <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Забравена парола?</a> |
                <a href="<?php echo esc_url( site_url('/register') ); ?>">Регистрация</a>
            </div>
        </form>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('kravo_login', 'kravo_login_form_shortcode');

// ===============================
// SET PASSWORD SHORTCODE [kravo_set_password]
// ===============================
function kravo_set_password_shortcode() {

    if ( ! is_user_logged_in() ) {
        return '<div class="kr-error">Трябва да сте логнати, за да зададете парола.</div>';
    }

    $user    = wp_get_current_user();
    $message = '';

    if (
        isset($_POST['kravo_setpass_nonce']) &&
        wp_verify_nonce($_POST['kravo_setpass_nonce'], 'kravo_setpass_action')
    ) {
        $pass1 = $_POST['kravo_new_password'] ?? '';
        $pass2 = $_POST['kravo_new_password_repeat'] ?? '';

        if ( empty($pass1) || empty($pass2) ) {
            $message = '<div class="kr-error">Моля, въведете паролата два пъти.</div>';
        } elseif ( $pass1 !== $pass2 ) {
            $message = '<div class="kr-error">Двете пароли не съвпадат.</div>';
        } else {
            $strength_msg = kr_check_password_strength( $pass1 );
            if ( $strength_msg ) {
                $message = '<div class="kr-error">' . esc_html( $strength_msg ) . '</div>';
            } else {
                wp_set_password( $pass1, $user->ID );
                wp_set_current_user( $user->ID );
                wp_set_auth_cookie( $user->ID );
                wp_safe_redirect( site_url('/dashboard') );
                exit;
            }
        }
    }

    ob_start();
    echo $message;
    ?>
    <div class="kravo-setpass-wrapper">
        <form method="post" class="kravo-setpass-form">
            <h2>Задаване на парола</h2>

            <p>Моля, изберете парола, с която ще влизате в системата.</p>

            <label for="kravo_new_password">Нова парола</label>
            <input type="password" id="kravo_new_password" name="kravo_new_password" required>

            <label for="kravo_new_password_repeat">Повторете паролата</label>
            <input type="password" id="kravo_new_password_repeat" name="kravo_new_password_repeat" required>

            <?php wp_nonce_field('kravo_setpass_action', 'kravo_setpass_nonce'); ?>

            <button type="submit" class="kr-button kr-button-primary">Запази паролата</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('kravo_set_password', 'kravo_set_password_shortcode');

// =======================================
// Помощна функция – възраст от дата
// =======================================
function kravo_get_age_from_date( $date_str ) {
    $ts = strtotime( $date_str );
    if ( ! $ts ) {
        return 0;
    }
    $dob  = new DateTime( date( 'Y-m-d', $ts ) );
    $now  = new DateTime();
    $diff = $now->diff( $dob );
    return (int) $diff->y;
}

// =======================================
// Шорткод [kr_register_donor]
// =======================================
function kr_register_donor_shortcode() {

    $is_submit = isset($_POST['kr_reg_donor_nonce']) &&
                 wp_verify_nonce($_POST['kr_reg_donor_nonce'],'kr_reg_donor_action');

    if ( is_user_logged_in() && ! $is_submit ) {
        return '<div class="kr-info">Вече сте логнати.</div>';
    }

    $errors = [];

    if ( $is_submit ) {
        $first  = sanitize_text_field($_POST['first_name'] ?? '');
        $last   = sanitize_text_field($_POST['last_name'] ?? '');
        $email  = sanitize_email($_POST['email'] ?? '');
        $phone  = sanitize_text_field($_POST['phone'] ?? '');
        $dob    = sanitize_text_field($_POST['dob'] ?? '');
        $region = sanitize_text_field($_POST['region'] ?? '');
        $blood  = sanitize_text_field($_POST['blood'] ?? '');
        $last_donation = sanitize_text_field($_POST['last_donation'] ?? '');
        $pass1  = $_POST['password'] ?? '';
        $pass2  = $_POST['password_confirm'] ?? '';

        if (!$first || !$last)         $errors[] = 'Въведете име и фамилия.';
        if (!is_email($email))         $errors[] = 'Въведете валиден имейл.';
        if (email_exists($email))      $errors[] = 'Вече има регистрация с този имейл.';
        if (!$dob)                     $errors[] = 'Въведете дата на раждане.';
        if (!$region || !$blood)       $errors[] = 'Попълнете областен център и кръвна група.';

        if ($dob) {
            $age = date_diff(date_create($dob), date_create())->y;
            if ($age < 18) $errors[] = 'Кръводарителите трябва да са навършили 18 години.';
        }

        if ( empty($pass1) || empty($pass2) ) {
            $errors[] = 'Въведете паролата два пъти.';
        } elseif ( $pass1 !== $pass2 ) {
            $errors[] = 'Двете пароли не съвпадат.';
        } else {
            $strength_msg = kr_check_password_strength( $pass1 );
            if ( $strength_msg ) {
                $errors[] = $strength_msg;
            }
        }

        if (empty($errors)) {
            $userdata = [
                'user_login' => kr_generate_login_from_email( $email ),
                'user_email' => $email,
                'user_pass'  => $pass1,
                'first_name' => $first,
                'last_name'  => $last,
                'role'       => 'kr_donor',
            ];
            $user_id = wp_insert_user($userdata);

            if (!is_wp_error($user_id)) {
                update_user_meta($user_id,'phone',$phone);
                update_user_meta($user_id,'dob',$dob);
                update_user_meta($user_id,'region',$region);
                update_user_meta($user_id,'blood_group',$blood);
                update_user_meta($user_id,'last_donation',$last_donation);

                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);

                wp_safe_redirect( site_url('/dashboard') );
                exit;
            } else {
                $errors[] = 'Възникна грешка при създаване на профила.';
            }
        }
    }

    ob_start();

    if ($errors) {
        echo '<div class="kr-error"><ul>';
        foreach ($errors as $e) echo '<li>'.esc_html($e).'</li>';
        echo '</ul></div>';
    }
    ?>
    <div class="kr-form-wrapper">
        <h2 class="kr-form-title">Регистрация на кръводарител</h2>
        <form method="post">
            <div class="kr-form-row">
                <label>Име</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="kr-form-row">
                <label>Фамилия</label>
                <input type="text" name="last_name" required>
            </div>
            <div class="kr-form-row">
                <label>Имейл</label>
                <input type="email" name="email" required>
            </div>
            <div class="kr-form-row">
                <label>Телефон</label>
                <input type="text" name="phone">
            </div>
            <div class="kr-form-row">
                <label>Дата на раждане</label>
                <input type="date" name="dob" required>
            </div>
            <div class="kr-form-row">
                <label>Областен център</label>
                <input type="text" name="region" required>
            </div>
            <div class="kr-form-row">
                <label>Кръвна група</label>
                <input type="text" name="blood" required>
            </div>
            <div class="kr-form-row">
                <label>Дата на последно даряване</label>
                <input type="date" name="last_donation">
            </div>

            <div class="kr-form-row">
                <label>Парола</label>
                <input type="password" name="password" required>
            </div>
            <div class="kr-form-row">
                <label>Повторете паролата</label>
                <input type="password" name="password_confirm" required>
            </div>

            <?php wp_nonce_field('kr_reg_donor_action','kr_reg_donor_nonce'); ?>
            <div class="kr-form-actions">
                <button type="submit" class="kr-button kr-button-primary">Регистрация</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('kr_register_donor','kr_register_donor_shortcode');

// =======================================
// Шорткод [kr_register_seeker]
// =======================================
function kr_register_seeker_shortcode() {

    $is_submit = isset($_POST['kr_reg_seeker_nonce']) &&
                 wp_verify_nonce($_POST['kr_reg_seeker_nonce'], 'kr_reg_seeker_action');

    if ( is_user_logged_in() && ! $is_submit ) {
        return '<div class="kr-info">Вече сте логнати.</div>';
    }

    $errors = [];

    if ( $is_submit ) {
        $first  = sanitize_text_field($_POST['first_name'] ?? '');
        $last   = sanitize_text_field($_POST['last_name'] ?? '');
        $email  = sanitize_email($_POST['email'] ?? '');
        $pass1  = $_POST['password'] ?? '';
        $pass2  = $_POST['password_confirm'] ?? '';

        if (!$first || !$last)    $errors[] = 'Въведете име и фамилия.';
        if (!is_email($email))    $errors[] = 'Въведете валиден имейл.';
        if (email_exists($email)) $errors[] = 'Вече има регистрация с този имейл.';

        if ( empty($pass1) || empty($pass2) ) {
            $errors[] = 'Въведете паролата два пъти.';
        } elseif ( $pass1 !== $pass2 ) {
            $errors[] = 'Двете пароли не съвпадат.';
        } else {
            $strength_msg = kr_check_password_strength( $pass1 );
            if ( $strength_msg ) {
                $errors[] = $strength_msg;
            }
        }

        if (empty($errors)) {
            $userdata = [
                'user_login' => kr_generate_login_from_email( $email ),
                'user_email' => $email,
                'user_pass'  => $pass1,
                'first_name' => $first,
                'last_name'  => $last,
                'role'       => 'kr_seeker',
            ];

            $user_id = wp_insert_user($userdata);

            if (!is_wp_error($user_id)) {

                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);

                wp_safe_redirect( site_url('/dashboard') );
                exit;
            } else {
                $errors[] = 'Грешка при запис.';
            }
        }
    }

    ob_start();

    if ($errors) {
        echo '<div class="kr-error"><ul>';
        foreach ($errors as $e) { echo '<li>'.esc_html($e).'</li>'; }
        echo '</ul></div>';
    }
    ?>

    <div class="kr-form-wrapper">
        <h2 class="kr-form-title">Регистрация на търсещ</h2>
        <form method="post">
            <div class="kr-form-row">
                <label>Име</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="kr-form-row">
                <label>Фамилия</label>
                <input type="text" name="last_name" required>
            </div>
            <div class="kr-form-row">
                <label>Имейл</label>
                <input type="email" name="email" required>
            </div>

            <div class="kr-form-row">
                <label>Парола</label>
                <input type="password" name="password" required>
            </div>
            <div class="kr-form-row">
                <label>Повторете паролата</label>
                <input type="password" name="password_confirm" required>
            </div>

            <?php wp_nonce_field('kr_reg_seeker_action', 'kr_reg_seeker_nonce'); ?>
            <div class="kr-form-actions">
                <button type="submit" class="kr-button kr-button-primary">Регистрация</button>
            </div>
        </form>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('kr_register_seeker', 'kr_register_seeker_shortcode');

// ===============================
// ОБЩА СТРАНИЦА ЗА РЕГИСТРАЦИЯ [kr_register]
// ===============================
function kr_register_switcher_shortcode() {

    $has_post = ! empty($_POST['kr_reg_seeker_nonce']) || ! empty($_POST['kr_reg_donor_nonce']);

    if ( is_user_logged_in() && ! $has_post ) {
        return '<div class="kr-info">Вече сте логнати. <a href="' . esc_url( site_url('/dashboard') ) . '">Към таблото</a></div>';
    }

    ob_start();
    ?>
    <div class="kr-form-wrapper kr-register-switcher">
        <h2 class="kr-form-title">Регистрация</h2>

        <div class="kr-form-actions" style="text-align:center; margin-bottom:20px;">
            <button type="button" class="kr-button kr-button-primary" data-target="seeker">
                Търся кръводарител
            </button>
            <button type="button" class="kr-button" data-target="donor">
                Искам да дарявам
            </button>
        </div>

        <div class="kr-register-block" data-type="seeker">
            <?php echo do_shortcode('[kr_register_seeker]'); ?>
        </div>

        <div class="kr-register-block" data-type="donor" style="display:none;">
            <?php echo do_shortcode('[kr_register_donor]'); ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var wrapper = document.querySelector('.kr-register-switcher');
        if (!wrapper) return;

        var buttons = wrapper.querySelectorAll('[data-target]');
        var blocks  = wrapper.querySelectorAll('.kr-register-block');

        buttons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var target = this.getAttribute('data-target');

                buttons.forEach(function(b) {
                    b.classList.remove('kr-button-primary');
                });
                this.classList.add('kr-button-primary');

                blocks.forEach(function(block) {
                    block.style.display =
                        (block.getAttribute('data-type') === target) ? 'block' : 'none';
                });
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('kr_register', 'kr_register_switcher_shortcode');

// ===============================
// Профил / Табло [kr_profile]
// ===============================
function kr_profile_shortcode() {
    if ( ! is_user_logged_in() ) {
        ob_start(); ?>
        <div class="kr-error" style="margin-bottom:20px;">
            Трябва да сте логнати, за да виждате профила си.
        </div>
        <div class="kr-form-actions" style="text-align:center; margin-top:10px;">
            <a href="<?php echo esc_url( site_url('/login') ); ?>" class="kr-button kr-button-primary">Вход</a>
            <a href="<?php echo esc_url( site_url('/register') ); ?>" class="kr-button">Регистрация</a>
        </div>
        <?php
        return ob_get_clean();
    }

    $user    = wp_get_current_user();
    $role    = $user->roles[0] ?? '';
    $blocked = (bool) get_user_meta( $user->ID, 'kr_chat_profile_blocked', true );

    $phone        = get_user_meta($user->ID,'phone',true);
    $dob          = get_user_meta($user->ID,'dob',true);
    $region_meta  = get_user_meta($user->ID,'region',true);
    $blood_meta   = get_user_meta($user->ID,'blood_group',true);
    $last_don_meta= get_user_meta($user->ID,'last_donation',true);

    $regions = [
        'Благоевград', 'Бургас', 'Варна', 'Велико Търново', 'Видин', 'Враца',
        'Габрово', 'Добрич', 'Кърджали', 'Кюстендил', 'Ловеч', 'Монтана',
        'Пазарджик', 'Перник', 'Плевен', 'Пловдив', 'Разград', 'Русе',
        'Силистра', 'Сливен', 'Смолян', 'София', 'Стара Загора', 'Търговище',
        'Хасково', 'Шумен', 'Ямбол'
    ];
    $blood_groups = [ 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', '0+', '0-' ];

    $region_val   = $region_meta;
    $blood_val    = $blood_meta;
    $last_don_val = $last_don_meta;

    $message = '';

    // Станете кръводарител (само за търсещи и ако профилът не е блокиран)
    if (
        ! $blocked &&
        $role === 'kr_seeker' &&
        isset($_POST['kr_become_donor_nonce']) &&
        wp_verify_nonce($_POST['kr_become_donor_nonce'],'kr_become_donor_action')
    ) {
        $region_val   = sanitize_text_field($_POST['region'] ?? '');
        $blood_val    = sanitize_text_field($_POST['blood'] ?? '');
        $last_don_val = sanitize_text_field($_POST['last_donation'] ?? '');

        $errors = [];
        if ( ! $region_val )   $errors[] = 'Моля, изберете областен център.';
        if ( ! $blood_val )    $errors[] = 'Моля, изберете кръвна група.';

        if ( empty( $errors ) ) {
            update_user_meta($user->ID,'region',$region_val);
            update_user_meta($user->ID,'blood_group',$blood_val);
            update_user_meta($user->ID,'last_donation',$last_don_val);

            $wp_user = new WP_User($user->ID);
            $wp_user->set_role('kr_donor');
            $role    = 'kr_donor';

            $region_meta   = $region_val;
            $blood_meta    = $blood_val;
            $last_don_meta = $last_don_val;

            $message = '<div class="kr-info">Профилът ви е обновен до Кръводарител.</div>';
        } else {
            $message = '<div class="kr-error"><ul>';
            foreach ($errors as $e) {
                $message .= '<li>'.esc_html($e).'</li>';
            }
            $message .= '</ul></div>';
        }
    }

    // Обновяване на дата на даряване (само за кръводарители и ако профилът не е блокиран)
    if (
        ! $blocked &&
        $role === 'kr_donor' &&
        isset($_POST['kr_update_donation_nonce']) &&
        wp_verify_nonce($_POST['kr_update_donation_nonce'], 'kr_update_donation_action')
    ) {
        $new_date = sanitize_text_field($_POST['last_donation'] ?? '');
        if ( $new_date && strtotime($new_date) ) {
            update_user_meta( $user->ID, 'last_donation', $new_date );
            $last_don_meta = $new_date;
            $message = '<div class="kr-info">Датата на последно даряване е обновена.</div>';
        } else {
            $message = '<div class="kr-error">Моля, въведете валидна дата.</div>';
        }
    }

    // Статус спрямо търсачката (2 месеца)
    $available_text = '';
    if ( $last_don_meta ) {
        $ts_last = strtotime($last_don_meta);
        $ts_lim  = strtotime('-2 months');
        $can_donate = ( $ts_last <= $ts_lim );
        $available_text = $can_donate
            ? 'В момента профилът ви е видим в търсачката.'
            : 'В момента сте извън търсачката, защото последното даряване е било преди по-малко от 2 месеца.';
    }

    $donor_search_url = site_url('/find-donor');
    $messages_page    = site_url('/messages');

    // Разговори (нишки)
    $threads = [];
    if ( ! $blocked ) {
        $q = new WP_Query( [
            'post_type'      => 'krv_message',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'kr_sender_id',
                    'value'   => $user->ID,
                    'compare' => '=',
                ],
                [
                    'key'     => 'kr_receiver_id',
                    'value'   => $user->ID,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'date',
            'order'   => 'ASC',
        ] );
        if ( $q->have_posts() ) {
            foreach ( $q->posts as $msg ) {
                $sender_id   = (int) get_post_meta( $msg->ID, 'kr_sender_id', true );
                $receiver_id = (int) get_post_meta( $msg->ID, 'kr_receiver_id', true );

                if ( $sender_id === $user->ID ) {
                    $partner_id = $receiver_id;
                } else {
                    $partner_id = $sender_id;
                }
                if ( ! $partner_id ) {
                    continue;
                }

                $date_ts = strtotime( $msg->post_date );
                if ( ! isset( $threads[ $partner_id ] ) || $date_ts > $threads[ $partner_id ]['date'] ) {
                    $threads[ $partner_id ] = [
                        'date'        => $date_ts,
                        'msg'         => $msg,
                        'last_sender' => $sender_id,
                    ];
                }
            }
            wp_reset_postdata();
        }
    }

    ob_start();
    echo $message;
    ?>
    <div class="kr-form-wrapper">
        <h2 class="kr-form-title">Моето табло</h2>

        <h3>Основна информация</h3>
        <p><strong>Име:</strong> <?php echo esc_html($user->first_name.' '.$user->last_name); ?></p>
        <p><strong>Имейл:</strong> <?php echo esc_html($user->user_email); ?></p>
        <p><strong>Роля:</strong> <?php echo ($role==='kr_donor') ? 'Кръводарител' : 'Търсещ кръв'; ?></p>

        <h3>Данни за контакт и кръвна информация</h3>
        <p><strong>Телефон:</strong> <?php echo $phone ? esc_html($phone) : '—'; ?></p>
        <p><strong>Дата на раждане:</strong> <?php echo $dob ? esc_html($dob) : '—'; ?></p>
        <p><strong>Областен център:</strong> <?php echo $region_meta ? esc_html($region_meta) : '—'; ?></p>
        <p><strong>Кръвна група:</strong> <?php echo $blood_meta ? esc_html($blood_meta) : '—'; ?></p>
        <p><strong>Последно даряване:</strong> <?php echo $last_don_meta ? esc_html($last_don_meta) : '—'; ?></p>

        <?php if ( $blocked ): ?>
            <div class="kr-error" style="margin-top:15px;">
                Профилът ви е блокиран от администратор. Нямате достъп до функционалностите на платформата.
            </div>
        <?php else: ?>

            <?php if ( $role === 'kr_seeker' ): ?>
                <div class="kr-form-actions" style="margin-top:20px; text-align:left;">
                    <a href="<?php echo esc_url( $donor_search_url ); ?>" class="kr-button kr-button-primary">
                        Намери кръводарител
                    </a>
                </div>

                <hr>
                <h3>Стани кръводарител</h3>
                <p>Ако желаете, можете да попълните допълнителни данни и профилът ви ще бъде отбелязан като кръводарител.</p>
                <form method="post">
                    <div class="kr-form-row">
                        <label>Областен център</label>
                        <select name="region" required>
                            <option value="">-- Изберете областен център --</option>
                            <?php foreach ( $regions as $r ): ?>
                                <option value="<?php echo esc_attr($r); ?>" <?php selected( $region_val, $r ); ?>>
                                    <?php echo esc_html($r); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kr-form-row">
                        <label>Кръвна група</label>
                        <select name="blood" required>
                            <option value="">-- Изберете кръвна група --</option>
                            <?php foreach ( $blood_groups as $bg ): ?>
                                <option value="<?php echo esc_attr($bg); ?>" <?php selected( $blood_val, $bg ); ?>>
                                    <?php echo esc_html($bg); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kr-form-row">
                        <label>Дата на последно даряване</label>
                        <input type="date" name="last_donation" value="<?php echo esc_attr($last_don_val); ?>">
                    </div>
                    <?php wp_nonce_field('kr_become_donor_action','kr_become_donor_nonce'); ?>
                    <div class="kr-form-actions">
                        <button type="submit" class="kr-button kr-button-primary">Стани кръводарител</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ( $role === 'kr_donor' ): ?>
                <hr>
                <h3>Отбележи ново даряване</h3>
                <?php if ( $last_don_meta ): ?>
                    <p>Последно отбелязано даряване: <strong><?php echo esc_html($last_don_meta); ?></strong></p>
                    <?php if ( $available_text ) : ?>
                        <p><?php echo esc_html( $available_text ); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <form method="post">
                    <div class="kr-form-row">
                        <label>Нова дата на даряване</label>
                        <input type="date" name="last_donation"
                               value="<?php echo esc_attr( date('Y-m-d') ); ?>" required>
                    </div>
                    <?php wp_nonce_field('kr_update_donation_action','kr_update_donation_nonce'); ?>
                    <div class="kr-form-actions">
                        <button type="submit" class="kr-button kr-button-primary">Запиши</button>
                    </div>
                </form>
            <?php endif; ?>

            <hr>
            <h3>Моите разговори</h3>
            <?php if ( empty( $threads ) ): ?>
                <p>Все още нямате разговори. Когато изпратите или получите съобщение, те ще се появят тук.</p>
            <?php else: ?>
                <div class="kr-conversations-list">
                    <?php foreach ( $threads as $partner_id => $data ): ?>
                        <?php
                        $partner = get_user_by( 'ID', $partner_id );
                        if ( ! $partner ) {
                            continue;
                        }
                        $unread  = ( $data['last_sender'] !== $user->ID );
                        $excerpt = wp_trim_words( $data['msg']->post_content, 15, '...' );
                        $chat_url = add_query_arg( 'partner', $partner_id, $messages_page );
                        ?>
                        <div class="kr-conversation-card">
                            <div class="kr-conv-header">
                                <span class="kr-conv-name">
                                    <?php echo esc_html( $partner->first_name . ' ' . $partner->last_name ); ?>
                                </span>
                                <span class="kr-conv-date">
                                    <?php echo esc_html( date_i18n( 'd.m.Y H:i', $data['date'] ) ); ?>
                                </span>
                            </div>
                            <div class="kr-conv-body">
                                <span class="kr-conv-label">Последно съобщение:</span>
                                <span class="kr-conv-excerpt"><?php echo esc_html( $excerpt ); ?></span>
                            </div>
                            <div class="kr-conv-footer">
                                <?php if ( $unread ): ?>
                                    <span class="kr-conv-unread">Ново съобщение</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url( $chat_url ); ?>" class="kr-button kr-button-primary">
                                    Отвори чата
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; // !blocked ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('kr_profile','kr_profile_shortcode');

/**
 * Общи стилове
 */
function kr_enqueue_form_styles() {
    ?>
    <style>
        .kr-form-wrapper { max-width:600px; margin:40px auto; padding:20px; border:1px solid #ddd; border-radius:8px; }
        .kr-form-title { margin-bottom:20px; text-align:center; }
        .kr-form-row { margin-bottom:12px; }
        .kr-form-row label { display:block; font-weight:600; margin-bottom:4px; }
        .kr-form-row input,
        .kr-form-row select { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; }
        .kr-form-actions { text-align:right; margin-top:20px; }
        .kr-button { padding:10px 20px; border:none; border-radius:4px; cursor:pointer; }
        .kr-button-primary { background:#b30000; color:#fff; }
        .kr-error { background:#ffdddd; padding:10px; margin-bottom:15px; border:1px solid #ff9090; border-radius:4px; }
        .kr-error ul { margin:0; padding-left:20px; }
        .kr-info { background:#ddffdd; padding:10px; margin-bottom:15px; border:1px solid #90ff90; border-radius:4px; }

        .kravo-login-wrapper { max-width: 400px; margin: 40px auto; }
        .kravo-login-form label { display:block; margin-top:15px; }
        .kravo-login-form input { width:100%; padding:8px; margin-top:5px; }
        .kravo-links { margin-top:15px; text-align:center; }

        .kravo-setpass-wrapper { max-width: 400px; margin: 40px auto; }
        .kravo-setpass-form label { display:block; margin-top:15px; }
        .kravo-setpass-form input { width:100%; padding:8px; margin-top:5px; }

        .kr-conversations-list {
            display:flex;
            flex-direction:column;
            gap:10px;
            margin-top:10px;
        }
        .kr-conversation-card {
            border:1px solid #ddd;
            border-radius:6px;
            padding:8px 10px;
            background:#fafafa;
        }
        .kr-conv-header {
            display:flex;
            justify-content:space-between;
            font-size:0.9em;
            margin-bottom:4px;
        }
        .kr-conv-name { font-weight:600; }
        .kr-conv-body {
            font-size:0.9em;
            margin-bottom:6px;
        }
        .kr-conv-label {
            font-weight:600;
            margin-right:4px;
        }
        .kr-conv-footer {
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .kr-conv-unread {
            font-size:0.85em;
            font-weight:600;
            color:#b30000;
        }
    </style>
    <?php
}
add_action('wp_head', 'kr_enqueue_form_styles');

/**
 * Тестови шорткодове
 */
add_shortcode( 'kr_test_login', 'kravo_login_form_shortcode' );
add_shortcode( 'kr_test_register_donor', 'kr_register_donor_shortcode' );
add_shortcode( 'kr_test_register_seeker', 'kr_register_seeker_shortcode' );
add_shortcode( 'kr_test_profile', 'kr_profile_shortcode' );
