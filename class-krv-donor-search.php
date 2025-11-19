<?php
/**
 * Търсачка за кръводарители – шорткод [kr_find_donor]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KRV_Donor_Search' ) ) {

    class KRV_Donor_Search {

        public static function init() {
            add_shortcode( 'kr_find_donor', array( __CLASS__, 'render_search_shortcode' ) );
        }

        /**
         * Профилът е блокиран?
         */
        protected static function is_profile_blocked( $user_id ) {
            return (bool) get_user_meta( $user_id, 'kr_chat_profile_blocked', true );
        }

        /**
         * Дарителят е „наличен“ (минаха 2 месеца или няма дата)?
         */
        protected static function donor_is_available( $user_id ) {
            $last = get_user_meta( $user_id, 'last_donation', true );
            if ( ! $last ) {
                return true;
            }
            $ts_last = strtotime( $last );
            if ( ! $ts_last ) {
                return true;
            }
            $ts_lim = strtotime( '-2 months' );
            return ( $ts_last <= $ts_lim );
        }

        public static function render_search_shortcode( $atts = array(), $content = '' ) {
            if ( ! is_user_logged_in() ) {
                $login_url    = site_url( '/login' );
                $register_url = site_url( '/register' );

                ob_start();
                ?>
                <p>За да използвате търсачката за кръводарители, е необходимо да влезете в профила си.</p>
                <p>
                    <a href="<?php echo esc_url( $login_url ); ?>">Вход</a> |
                    <a href="<?php echo esc_url( $register_url ); ?>">Регистрация</a>
                </p>
                <?php
                return ob_get_clean();
            }

            $current    = wp_get_current_user();
            $current_id = (int) $current->ID;

            if ( self::is_profile_blocked( $current_id ) ) {
                return '<div class="kr-error">Профилът ви е блокиран. Нямате достъп до търсачката.</div>';
            }

            $region = isset( $_GET['kr_region'] ) ? sanitize_text_field( $_GET['kr_region'] ) : '';
            $blood  = isset( $_GET['kr_blood'] )  ? sanitize_text_field( $_GET['kr_blood'] )  : '';

            $regions = array(
                ''               => 'Всички области',
                'Благоевград'    => 'Благоевград',
                'Бургас'         => 'Бургас',
                'Варна'          => 'Варна',
                'Велико Търново' => 'Велико Търново',
                'Видин'          => 'Видин',
                'Враца'          => 'Враца',
                'Габрово'        => 'Габрово',
                'Добрич'         => 'Добрич',
                'Кърджали'       => 'Кърджали',
                'Кюстендил'      => 'Кюстендил',
                'Ловеч'          => 'Ловеч',
                'Монтана'        => 'Монтана',
                'Пазарджик'      => 'Пазарджик',
                'Перник'         => 'Перник',
                'Плевен'         => 'Плевен',
                'Пловдив'        => 'Пловдив',
                'Разград'        => 'Разград',
                'Русе'           => 'Русе',
                'Силистра'       => 'Силистра',
                'Сливен'         => 'Сливен',
                'Смолян'         => 'Смолян',
                'София'          => 'София',
                'София област'   => 'София област',
                'Стара Загора'   => 'Стара Загора',
                'Търговище'      => 'Търговище',
                'Хасково'        => 'Хасково',
                'Шумен'          => 'Шумен',
                'Ямбол'          => 'Ямбол',
            );

            $blood_groups = array(
                ''    => 'Всички групи',
                '0-'  => '0-',
                '0+'  => '0+',
                'A-'  => 'A-',
                'A+'  => 'A+',
                'B-'  => 'B-',
                'B+'  => 'B+',
                'AB-' => 'AB-',
                'AB+' => 'AB+',
            );

            $users = array();

            if ( $region || $blood ) {
                $meta_query = array( 'relation' => 'AND' );

                if ( $region ) {
                    $meta_query[] = array(
                        'key'   => 'region',
                        'value' => $region,
                    );
                }

                if ( $blood ) {
                    $meta_query[] = array(
                        'key'   => 'blood_group',
                        'value' => $blood,
                    );
                }

                $query = new WP_User_Query( array(
                    'role'       => 'kr_donor',
                    'number'     => 300,
                    'orderby'    => 'registered',
                    'order'      => 'DESC',
                    'meta_query' => $meta_query,
                ) );

                $all = $query->get_results();

                if ( $all ) {
                    foreach ( $all as $u ) {

                        // не показваме самия потребител
                        if ( (int) $u->ID === $current_id ) {
                            continue;
                        }

                        // не показваме блокирани профили
                        if ( self::is_profile_blocked( $u->ID ) ) {
                            continue;
                        }

                        // не показваме дарители в „паузата“
                        if ( ! self::donor_is_available( $u->ID ) ) {
                            continue;
                        }

                        $users[] = $u;
                    }
                }
            }

            $messages_url = site_url( '/messages' );

            ob_start();
            ?>
            <div class="kr-form-wrapper">
                <h2 class="kr-form-title">Търсачка за кръводарители</h2>

                <form method="get" class="kr-form-row" style="margin-bottom:20px;">
                    <label>Област</label>
                    <select name="kr_region">
                        <?php foreach ( $regions as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $region, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label style="margin-top:10px;">Кръвна група</label>
                    <select name="kr_blood">
                        <?php foreach ( $blood_groups as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $blood, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="kr-form-actions" style="margin-top:15px;">
                        <button type="submit" class="kr-button kr-button-primary">Търси</button>
                    </div>
                </form>

                <?php if ( ! $region && ! $blood ) : ?>
                    <p>Изберете област и/или кръвна група и натиснете „Търси“.</p>
                <?php else : ?>

                    <?php if ( empty( $users ) ) : ?>
                        <p>Няма намерени подходящи кръводарители според избраните критерии.</p>
                    <?php else : ?>
                        <h3>Намерени кръводарители</h3>
                        <ul class="kr-conversations-list">
                            <?php foreach ( $users as $u ) : ?>
                                <?php
                                $region_u = get_user_meta( $u->ID, 'region', true );
                                $bg       = get_user_meta( $u->ID, 'blood_group', true );
                                $last     = get_user_meta( $u->ID, 'last_donation', true );
                                $name     = trim( $u->first_name . ' ' . $u->last_name );
                                $label    = $name ? $name : 'Кръводарител';
                                $chat_link = add_query_arg( 'partner', $u->ID, $messages_url );
                                ?>
                                <li class="kr-conversation-card">
                                    <div class="kr-conv-header">
                                        <span class="kr-conv-name"><?php echo esc_html( $label ); ?></span>
                                    </div>
                                    <div class="kr-conv-body">
                                        <p><strong>Област:</strong> <?php echo esc_html( $region_u ?: '—' ); ?></p>
                                        <p><strong>Кръвна група:</strong> <?php echo esc_html( $bg ?: '—' ); ?></p>
                                        <p><strong>Последно даряване:</strong> <?php echo esc_html( $last ?: 'няма данни' ); ?></p>
                                    </div>
                                    <div class="kr-conv-footer">
                                        <a href="<?php echo esc_url( $chat_link ); ?>" class="kr-button kr-button-primary">
                                            Пиши на този дарител
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }
    }
}

// регистрираме шорткода веднага щом файлът се включи
KRV_Donor_Search::init();
