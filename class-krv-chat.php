<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ========================================================================
 *  REGISTER CPT FOR MESSAGES
 * ======================================================================== */
function kr_chat_register_cpt() {
    register_post_type('krv_message', [
        'label'              => 'Съобщения',
        'public'             => false,
        'show_ui'            => false,
        'show_in_menu'       => false,
        'exclude_from_search'=> true,
        'supports'           => ['editor', 'author'],
    ]);
}
add_action('init', 'kr_chat_register_cpt');


/* ========================================================================
 *  HELPERS
 * ======================================================================== */

function kr_chat_get_thread_key($u1, $u2) {
    $ids = [ (int)$u1, (int)$u2 ];
    sort($ids);
    return "thread_{$ids[0]}_{$ids[1]}";
}

function kr_chat_is_profile_blocked($uid) {
    return (bool)get_user_meta($uid, 'kr_chat_profile_blocked', true);
}

function kr_chat_block_thread($u1, $u2) {
    $key = kr_chat_get_thread_key($u1, $u2);

    $meta1 = get_user_meta($u1, 'kr_blocked_threads', true);
    if (!is_array($meta1)) $meta1 = [];
    $meta1[$key] = 1;
    update_user_meta($u1, 'kr_blocked_threads', $meta1);

    $meta2 = get_user_meta($u2, 'kr_blocked_threads', true);
    if (!is_array($meta2)) $meta2 = [];
    $meta2[$key] = 1;
    update_user_meta($u2, 'kr_blocked_threads', $meta2);
}

function kr_chat_unblock_thread($u1, $u2) {
    $key = kr_chat_get_thread_key($u1, $u2);

    $meta1 = get_user_meta($u1, 'kr_blocked_threads', true);
    if (is_array($meta1) && isset($meta1[$key])) {
        unset($meta1[$key]);
        update_user_meta($u1, 'kr_blocked_threads', $meta1);
    }

    $meta2 = get_user_meta($u2, 'kr_blocked_threads', true);
    if (is_array($meta2) && isset($meta2[$key])) {
        unset($meta2[$key]);
        update_user_meta($u2, 'kr_blocked_threads', $meta2);
    }
}

function kr_chat_is_thread_blocked($u1, $u2) {
    $key = kr_chat_get_thread_key($u1, $u2);

    $meta1 = get_user_meta($u1, 'kr_blocked_threads', true);
    if (is_array($meta1) && !empty($meta1[$key])) return true;

    $meta2 = get_user_meta($u2, 'kr_blocked_threads', true);
    if (is_array($meta2) && !empty($meta2[$key])) return true;

    return false;
}

function kr_chat_contains_forbidden_money($text) {
    $t = mb_strtolower($text, 'UTF-8');
    $words = ['пари','лв','заплащане','такса','евро','€','комисион','банкова сметка','iban','айбан','възнаграждение'];

    foreach ($words as $w) {
        if (strpos($t, $w) !== false) return true;
    }
    if (preg_match('/\b\d+\s*(лв|лева|евро)\b/u', $t)) return true;

    return false;
}

function kr_chat_contains_contact($text) {
    $t = mb_strtolower($text, 'UTF-8');

    // телефони
    if (preg_match('/(\+359|0)\s*\d[\d\s\-]{7,12}/u', $t)) return true;

    // имейли
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', $t)) return true;

    // социални мрежи и думи за контакт
    $danger = ['facebook','viber','whatsapp','instagram','телефон','имейл','mail','скайп'];
    foreach ($danger as $d) {
        if (strpos($t, $d) !== false) return true;
    }

    return false;
}


/* ========================================================================
 *  EMAIL NOTIFICATIONS
 * ======================================================================== */

function kr_chat_send_email($to_user_id, $from_user_id, $text) {
    $to_user   = get_user_by('ID', $to_user_id);
    $from_user = get_user_by('ID', $from_user_id);
    if (!$to_user || !$from_user) return;

    $address = $to_user->user_email;
    $subject = 'Ново съобщение в Kravodaritel.com';
    $body    = "Имате ново съобщение от: {$from_user->first_name} {$from_user->last_name}\n\n".
               "Съдържание:\n{$text}\n\nВлезте в сайта, за да отговорите.";

    wp_mail($address, $subject, $body);
}


/* ========================================================================
 *  SHORTCODE: [kr_messages]
 * ======================================================================== */

function kr_messages_shortcode() {

    if (!is_user_logged_in()) {
        return "<p>Трябва да сте логнати, за да виждате съобщенията.</p>";
    }

    $current_id = get_current_user_id();

    if (kr_chat_is_profile_blocked($current_id)) {
        return "<div class='kr-error'>Профилът ви е блокиран.</div>";
    }

    $partner_id = isset($_GET['partner']) ? (int)$_GET['partner'] : 0;
    if ($partner_id === $current_id) $partner_id = 0; // не чат със себе си

    $base_url = get_permalink();

    /* ====================================================================
     *  CASE 1: НЯМА избран партньор → ПОКАЗВАМЕ СПИСЪК С РАЗГОВОРИ
     * ==================================================================== */
    if (!$partner_id) {

        $msgs = new WP_Query([
            'post_type'      => 'krv_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => 'kr_sender_id',   'value' => $current_id ],
                [ 'key' => 'kr_receiver_id', 'value' => $current_id ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $threads = [];

        if ($msgs->have_posts()) {
            foreach ($msgs->posts as $msg) {

                $sender   = (int)get_post_meta($msg->ID, 'kr_sender_id', true);
                $receiver = (int)get_post_meta($msg->ID, 'kr_receiver_id', true);

                if (!$sender || !$receiver) continue;

                $other = $sender === $current_id ? $receiver : $sender;
                if ($other === $current_id) continue;

                $thread_key = kr_chat_get_thread_key($current_id, $other);

                if (!isset($threads[$thread_key])) {
                    $threads[$thread_key] = [
                        'other_id'  => $other,
                        'last_date' => $msg->post_date,
                        'last_text' => $msg->post_content,
                    ];
                }
            }
        }

        ob_start();
        ?>
        <div class="kr-form-wrapper">
            <h2 class="kr-form-title">Кореспонденция</h2>

            <?php if (empty($threads)): ?>
                <p>Все още нямате съобщения.</p>
            <?php else: ?>
                <ul class="kr-conversations-list">
                    <?php foreach ($threads as $th): ?>
                        <?php
                        $other = get_user_by('ID', $th['other_id']);
                        if (!$other) continue;

                        $name      = trim($other->first_name . ' ' . $other->last_name);
                        if (!$name) $name = $other->user_login;

                        $date      = mysql2date('d.m.Y H:i', $th['last_date']);
                        $excerpt   = wp_trim_words($th['last_text'], 15, '…');
                        $chat_link = add_query_arg('partner', $other->ID, $base_url);
                        ?>
                        <li class="kr-conversation-card">
                            <div class="kr-conv-header">
                                <span class="kr-conv-name"><?php echo esc_html($name); ?></span>
                                <span class="kr-conv-date"><?php echo esc_html($date); ?></span>
                            </div>
                            <div class="kr-conv-body">
                                <?php echo esc_html($excerpt); ?>
                            </div>
                            <div class="kr-conv-footer">
                                <a href="<?php echo esc_url($chat_link); ?>" class="kr-button kr-button-primary">
                                    Отвори чата
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ====================================================================
     *  CASE 2: ИЗБРАН ПАРТНЬОР → ПОКАЗВАМЕ ПЪЛНИЯ ЧАТ
     * ==================================================================== */

    $partner = get_user_by('ID', $partner_id);
    if (!$partner) return "<p>Невалиден потребител.</p>";

    $thread_key = kr_chat_get_thread_key($current_id, $partner_id);
    $blocked    = kr_chat_is_thread_blocked($current_id, $partner_id);

    /* ---- изпращане на съобщение ---- */
    if (!$blocked && isset($_POST['kr_chat_action']) && $_POST['kr_chat_action'] === 'send') {

        if (isset($_POST['kr_send_nonce']) &&
            wp_verify_nonce($_POST['kr_send_nonce'], 'kr_send_'.$partner_id)) {

            $text = trim(wp_unslash($_POST['kr_text']));

            if ($text === '') {
                echo "<div class='kr-error'>Съобщението е празно.</div>";
            } elseif (kr_chat_contains_forbidden_money($text)) {
                echo "<div class='kr-error'>Не е позволено да се уговарят пари.</div>";
            } elseif (kr_chat_contains_contact($text)) {
                echo "<div class='kr-error'>Не е позволено да се разменят телефони, имейли или контакти.</div>";
            } else {
                $mid = wp_insert_post([
                    'post_type'    => 'krv_message',
                    'post_status'  => 'publish',
                    'post_content' => $text,
                    'post_author'  => $current_id,
                ]);

                update_post_meta($mid, 'kr_sender_id',   $current_id);
                update_post_meta($mid, 'kr_receiver_id', $partner_id);
                update_post_meta($mid, 'kr_thread_key',  $thread_key);

                kr_chat_send_email($partner_id, $current_id, $text);
            }
        }
    }

    /* ---- извличаме историята ---- */
    $msgs = new WP_Query([
        'post_type'      => 'krv_message',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'   => 'kr_thread_key',
            'value' => $thread_key,
        ]],
        'orderby'        => 'date',
        'order'          => 'ASC',
    ]);

    ob_start();
    ?>
    <div class="kr-form-wrapper">
        <h2 class="kr-form-title">Разговор с <?php echo esc_html($partner->first_name . ' ' . $partner->last_name); ?></h2>

        <div class="kr-chat-history" style="border:1px solid #ccc;padding:10px;max-height:350px;overflow-y:auto;margin-bottom:15px;">
            <?php if (!$msgs->have_posts()): ?>
                <p>Все още няма съобщения.</p>
            <?php else: ?>
                <?php foreach ($msgs->posts as $msg): ?>
                    <?php
                    $sender = (int)get_post_meta($msg->ID, 'kr_sender_id', true);
                    $is_me  = $sender === $current_id;
                    $date   = mysql2date('d.m.Y H:i', $msg->post_date);
                    ?>
                    <div style="text-align:<?php echo $is_me ? 'right':'left'; ?>;margin-bottom:8px;">
                        <div style="display:inline-block;padding:6px 10px;border-radius:6px;max-width:70%;background:<?php echo $is_me ? '#e6f7ff':'#f2f2f2'; ?>;">
                            <div style="font-size:10px;color:#555;"><?php echo $is_me ? 'Аз' : esc_html($partner->first_name); ?> • <?php echo esc_html($date); ?></div>
                            <div><?php echo nl2br(esc_html($msg->post_content)); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!$blocked): ?>
            <form method="post">
                <textarea name="kr_text" rows="3" style="width:100%;"></textarea>
                <?php wp_nonce_field('kr_send_'.$partner_id,'kr_send_nonce'); ?>
                <input type="hidden" name="kr_chat_action" value="send">
                <button class="kr-button kr-button-primary" style="margin-top:10px;">Изпрати</button>
            </form>
        <?php else: ?>
            <div class="kr-error">Разговорът е блокиран.</div>
        <?php endif; ?>

    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('kr_messages', 'kr_messages_shortcode');
