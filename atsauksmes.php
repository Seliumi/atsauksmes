<?php
/**
 * Plugin Name: Atsauksmes
 * Description: Atsauksmju sistēma WordPress videi.
 * Version: 2.2
 * Author: Anna A
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//Custom post type reģ.
add_action( 'init', function() {
    $labels = array(
        'name'               => 'Atsauksmes',
        'singular_name'      => 'Atsauksme',
        'add_new'            => 'Pievienot jaunu',
        'add_new_item'       => 'Pievienot jaunu atsauksmi',
        'edit_item'          => 'Rediģēt atsauksmi',
        'all_items'          => 'Visas atsauksmes',
        'search_items'       => 'Meklēt atsauksmes',
        'not_found'          => 'Atsauksmes nav atrastas',
        'not_found_in_trash' => 'Miskastē atsauksmes nav atrastas',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => false,
        'exclude_from_search'=> true,
        'rewrite'            => false,
        'has_archive'        => false,
        'show_in_menu'       => true,
        'menu_icon'          => 'dashicons-star-filled',
        'supports'           => array( 'title', 'editor', 'author' ),
        'capability_type'    => 'post',
        'capabilities'       => array(
            'create_posts' => 'do_not_allow',
        ),
        'map_meta_cap'        => true,
    );

    register_post_type( 'product_review', $args );
});

//Noņem "Pievienot jaunu atsauksmi" submenu — tomēr nevajadzīgs
add_action( 'admin_menu', function() {
    remove_submenu_page( 'edit.php?post_type=product_review', 'post-new.php?post_type=product_review' );
}, 99 );

//Formatē timestamp kā ("2026. gada 4. septembrī"), gadījumā, ja WP vidē lokāli izmanto citu valodu
function atsauksmes_format_date_lv( $timestamp ) {
    $months = array(
        1  => 'janvārī',
        2  => 'februārī',
        3  => 'martā',
        4  => 'aprīlī',
        5  => 'maijā',
        6  => 'jūnijā',
        7  => 'jūlijā',
        8  => 'augustā',
        9  => 'septembrī',
        10 => 'oktobrī',
        11 => 'novembrī',
        12 => 'decembrī',
    );

    $day   = (int) gmdate( 'j', $timestamp );
    $month = $months[ (int) gmdate( 'n', $timestamp ) ];
    $year  = gmdate( 'Y', $timestamp );

    return sprintf( '%s. gada %d. %s', $year, $day, $month );
}


//2. Admina meta box (vērtējums, produkta ID)
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'review_details',
        'Atsauksmes detaļas',
        'render_review_meta_box',
        'product_review',
        'side',
        'high'
    );
});

function render_review_meta_box( $post ) {
    $rating     = get_post_meta( $post->ID, '_review_rating', true );
    $product_id = get_post_meta( $post->ID, '_review_product_id', true );
    wp_nonce_field( 'review_details_nonce', 'review_nonce' );
    ?>
    <p>
        <label for="review_rating"><strong>Vērtējums (1-5):</strong></label><br>
        <select name="review_rating" id="review_rating">
            <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                <option value="<?php echo $i; ?>" <?php selected( $rating, $i ); ?>>
                    <?php echo $i; ?> zvaigzne(s)
                </option>
            <?php endfor; ?>
        </select>
    </p>
    <p>
        <label for="review_product_id"><strong>Produkta ID:</strong></label><br>
        <input type="number" name="review_product_id" id="review_product_id" value="<?php echo esc_attr( $product_id ); ?>" />
    </p>
    <?php
}

add_action( 'save_post', function( $post_id ) {
    if ( ! isset( $_POST['review_nonce'] ) || ! wp_verify_nonce( $_POST['review_nonce'], 'review_details_nonce' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( isset( $_POST['review_rating'] ) ) {
        $rating = intval( $_POST['review_rating'] );
        $rating = max( 1, min( 5, $rating ) );
        update_post_meta( $post_id, '_review_rating', $rating );
    }
    if ( isset( $_POST['review_product_id'] ) ) {
        update_post_meta( $post_id, '_review_product_id', intval( $_POST['review_product_id'] ) );
    }
});

// 3. Moderācijas paneļa kolonnas
//Virsraksts, Vērtējums, Atsauksmes teksts, Produkts, Autors, Datums.
add_filter( 'manage_product_review_posts_columns', function( $columns ) {
    return array(
        'cb'             => isset( $columns['cb'] ) ? $columns['cb'] : '',
        'title'          => 'Virsraksts',
        'review_rating'  => 'Vērtējums',
        'review_content' => 'Atsauksmes teksts',
        'review_product' => 'Produkts',
        'author'         => 'Autors',
        'date'           => 'Datums',
    );
});

add_action( 'manage_product_review_posts_custom_column', function( $column, $post_id ) {
    switch ( $column ) {

        case 'review_rating':
            $rating = get_post_meta( $post_id, '_review_rating', true );
            echo $rating ? str_repeat( '⭐', intval( $rating ) ) : '—';
            break;

        case 'review_content':
            $post = get_post( $post_id );
            echo esc_html( wp_trim_words( $post->post_content, 12, '…' ) );
            break;

        case 'review_product':
            $product_id = get_post_meta( $post_id, '_review_product_id', true );
            if ( ! $product_id ) {
                echo '—';
                break;
            }
            //Produkta nosaukums un arī ID, ja nepieciešams
            if ( function_exists( 'wc_get_product' ) && ( $wc_product = wc_get_product( $product_id ) ) ) {
                printf(
                    '<a href="%s">%s</a> (ID: %s)',
                    esc_url( get_edit_post_link( $product_id ) ),
                    esc_html( $wc_product->get_name() ),
                    esc_html( $product_id )
                );
            } else {
                echo esc_html( $product_id );
            }
            break;
    }
}, 10, 2 );

add_filter( 'manage_edit-product_review_sortable_columns', function( $columns ) {
    $columns['review_rating']  = 'review_rating';
    $columns['review_product'] = 'review_product';
    return $columns;
});

// Sorting for kolonnas
add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( 'product_review' !== $query->get( 'post_type' ) ) {
        return;
    }

    $orderby = $query->get( 'orderby' );

    if ( 'review_rating' === $orderby ) {
        $query->set( 'meta_key', '_review_rating' );
        $query->set( 'orderby', 'meta_value_num' );
    }

    if ( 'review_product' === $orderby ) {
        $query->set( 'meta_key', '_review_product_id' );
        $query->set( 'orderby', 'meta_value_num' );
    }
});

// 4. JS un CSS
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'atsauksmes-css',
        plugins_url( 'assets/atsauksmes.css', __FILE__ ),
        array(),
        '1.0'
    );

    wp_enqueue_script(
        'atsauksmes-js',
        plugins_url( 'assets/atsauksmes.js', __FILE__ ),
        array( 'jquery' ),
        '1.1',
        true
    );
    wp_localize_script( 'atsauksmes-js', 'custom_reviews_obj', array(
        'ajax_url'     => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'submit_review_nonce' ),
        'delete_nonce' => wp_create_nonce( 'delete_review_nonce' ),
    ) );
});

// 5. AJAX atsauksmes iesniegšanai
add_action( 'wp_ajax_submit_product_review', 'handle_submit_product_review' );

function handle_submit_product_review() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'submit_review_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Drošības kļūda. Lūdzu, pārlādējiet lapu un mēģiniet vēlreiz.' ) );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Jums ir jābūt ielogotam, lai atstātu atsauksmi.' ) );
    }

    $user_id    = get_current_user_id();
    $product_id = intval( $_POST['review_product_id'] ?? 0 );
    $title      = sanitize_text_field( $_POST['review_title'] ?? '' );
    $content    = sanitize_textarea_field( $_POST['review_content'] ?? '' );
    $rating     = intval( $_POST['review_rating'] ?? 0 );

    if ( $rating < 1 || $rating > 5 ) {
        wp_send_json_error( array( 'message' => 'Lūdzu, izvēlieties vērtējumu no 1 līdz 5 zvaigznēm.' ) );
    }

    if ( empty( $title ) && empty( $content ) ) {
        wp_send_json_error( array( 'message' => 'Lūdzu, aizpildiet vismaz virsrakstu vai atsauksmes tekstu.' ) );
    }

    $existing = get_posts( array(
        'post_type'      => 'product_review',
        'post_status'    => array( 'publish', 'pending' ),
        'author'         => $user_id,
        'meta_key'       => '_review_product_id',
        'meta_value'     => $product_id,
        'posts_per_page' => 1,
    ) );

    if ( $existing ) {
        wp_send_json_error( array( 'message' => 'Jūs jau esat atstājis atsauksmi šim produktam.' ) );
    }

    //PLACEHOLDER - nezinu vai tiešām vajadzēs, ja būs moderācijas panelis
    $blacklist = array( 'sliktsvards1', 'sliktsvards2' );
    $text      = $title . ' ' . $content;
    $status    = 'publish';

    foreach ( $blacklist as $word ) {
        if ( stripos( $text, $word ) !== false ) {
            $status = 'pending';
            break;
        }
    }

    if ( preg_match( '/https?:\/\//i', $text ) ) {
        $status = 'pending';
    }

    $review_data = array(
        'post_type'    => 'product_review',
        'post_title'   => $title ?: ( $content ? mb_substr( $content, 0, 50 ) . '...' : 'Bez virsraksta' ),
        'post_content' => $content,
        'post_status'  => $status,
        'post_author'  => $user_id,
    );

    $review_id = wp_insert_post( $review_data );

    if ( $review_id && ! is_wp_error( $review_id ) ) {
        update_post_meta( $review_id, '_review_rating', $rating );
        update_post_meta( $review_id, '_review_product_id', $product_id );

        if ( $status === 'pending' ) {
            wp_send_json_success( array(
                'message' => 'Paldies! Jūsu atsauksme ir iesniegta un tiks publicēta pēc moderācijas.',
                'status'  => 'pending',
            ) );
        } else {
            $current_user = wp_get_current_user();

            wp_send_json_success( array(
                'message' => 'Paldies! Jūsu atsauksme ir publicēta.',
                'status'  => 'publish',
                'review'  => array(
                    'id'      => $review_id,
                    'title'   => $review_data['post_title'],
                    'content' => $content,
                    'stars'   => str_repeat( '⭐', $rating ),
                    'author'  => $current_user->display_name,
                    'date'    => atsauksmes_format_date_lv( current_time( 'timestamp' ) ),
                ),
            ) );
        }
    } else {
        wp_send_json_error( array( 'message' => 'Notika kļūda. Lūdzu, mēģiniet vēlreiz.' ) );
    }
}


// 6. ar AJAX atsauksmes dzēšanai
add_action( 'wp_ajax_delete_product_review', 'handle_delete_product_review' );

function handle_delete_product_review() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'delete_review_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Drošības kļūda. Lūdzu, pārlādējiet lapu un mēģiniet vēlreiz.' ) );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Jums ir jābūt ielogotam.' ) );
    }

    $review_id = intval( $_POST['review_id'] ?? 0 );
    $review    = get_post( $review_id );

    if ( ! $review || 'product_review' !== $review->post_type ) {
        wp_send_json_error( array( 'message' => 'Atsauksme nav atrasta.' ) );
    }

    // Tiesību check
    if ( (int) $review->post_author !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Jums nav tiesību dzēst šo atsauksmi.' ) );
    }

    // No sākuma uz miskasti
    $result = wp_trash_post( $review_id );

    if ( $result ) {
        wp_send_json_success( array( 'message' => 'Atsauksme dzēsta.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Neizdevās dzēst atsauksmi.' ) );
    }
}


// 7. WooCommerce noklusēto atsauksmju aizstāšana
add_filter( 'woocommerce_product_tabs', function( $tabs ) {
    if ( isset( $tabs['reviews'] ) ) {
        global $product;
        $product_id = $product ? $product->get_id() : 0;

        //Uzskaitīšana
        $tabs['reviews']['title']    = sprintf( 'Atsauksmes (%d)', atsauksmes_count_published_reviews( $product_id ) );
        $tabs['reviews']['callback'] = 'custom_display_product_reviews';
    }
    return $tabs;
}, 98 );

function atsauksmes_count_published_reviews( $product_id ) {
    $ids = get_posts( array(
        'post_type'      => 'product_review',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => '_review_product_id',
        'meta_value'     => $product_id,
    ) );

    return count( $ids );
}

function custom_display_product_reviews() {
    global $product;
    if ( ! $product ) {
        return;
    }

    $product_id = $product->get_id();
    ?>
    <div class="custom-reviews">
        <h2>Atsauksmes</h2>

        <?php
        $reviews = get_posts( array(
            'post_type'      => 'product_review',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'meta_key'       => '_review_product_id',
            'meta_value'     => $product_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        ?>

        <div class="reviews-list" id="atsauksmes-list">
        <?php if ( $reviews ) : ?>
            <?php foreach ( $reviews as $review ) :
                $rating = get_post_meta( $review->ID, '_review_rating', true );
                $stars  = str_repeat( '⭐', intval( $rating ) );
                ?>
                <div class="review-card" data-review-id="<?php echo esc_attr( $review->ID ); ?>" style="border:1px solid #ddd; padding:15px; margin-bottom:15px;">
                    <div class="review-rating"><?php echo $stars; ?></div>
                    <h4><?php echo esc_html( $review->post_title ); ?></h4>
                    <p><?php echo esc_html( $review->post_content ); ?></p>
                    <small>
                        <?php echo esc_html( atsauksmes_format_date_lv( get_the_date( 'U', $review->ID ) ) ); ?>
                        —
                        <?php echo esc_html( get_the_author_meta( 'display_name', $review->post_author ) ); ?>
                    </small>
                    <?php if ( get_current_user_id() === (int) $review->post_author ) : ?>
                        <p>
                            <a href="#" class="delete-review-link" data-review-id="<?php echo esc_attr( $review->ID ); ?>">
                                Dzēst atsauksmi
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="no-reviews-msg">Pašlaik atsauksmju nav.</p>
        <?php endif; ?>
        </div>

        <?php if ( is_user_logged_in() ) : ?>
            <h3>Pievienot atsauksmi</h3>
            <form method="post" action="" class="custom-review-form">
                <input type="hidden" name="review_product_id" value="<?php echo esc_attr( $product_id ); ?>" />
                <div class="review-form-response"></div>
                <p>
                    <label for="review_title">Virsraksts:</label><br>
                    <input type="text" name="review_title" id="review_title" maxlength="100" style="width:100%;" />
                </p>
                <p>
                    <label for="review_content">Atsauksme:</label><br>
                    <textarea name="review_content" id="review_content" rows="4" style="width:100%;"></textarea>
                </p>
                <p>
                    <label>Vērtējums:</label><br>
                    <?php
                    //Vērtēšana hover izvēles veidā - css fails priekš šī
                    ?>
                    <div class="star-rating-input">
                        <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                            <input type="radio" id="star<?php echo $i; ?>-<?php echo esc_attr( $product_id ); ?>" name="review_rating" value="<?php echo $i; ?>">
                            <label for="star<?php echo $i; ?>-<?php echo esc_attr( $product_id ); ?>" title="<?php echo $i; ?> zvaigzne(s)">★</label>
                        <?php endfor; ?>
                    </div>
                </p>
                <p>
                    <input type="submit" value="Iesniegt atsauksmi" class="button" />
                </p>
            </form>
        <?php else : ?>
            <p>Lai pievienotu atsauksmi, lūdzu, <a href="<?php echo wp_login_url( get_permalink() ); ?>">ielogojieties</a>.</p>
        <?php endif; ?>
    </div>
    <?php
}

// 8. Priekš mazās "Reviews" sekcijas produkta rediģēšanas lapā (moderācijas panelī)
add_action( 'add_meta_boxes_product', function() {
    remove_meta_box( 'commentsdiv', 'product', 'normal' );

    add_meta_box(
        'product_reviews_overview',
        'Atsauksmes',
        'render_product_reviews_overview_meta_box',
        'product',
        'normal',
        'default'
    );
});

function render_product_reviews_overview_meta_box( $post ) {
    $product_id = $post->ID;

    $reviews = get_posts( array(
        'post_type'      => 'product_review',
        'post_status'    => array( 'publish', 'pending' ),
        'posts_per_page' => -1,
        'meta_key'       => '_review_product_id',
        'meta_value'     => $product_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    if ( ! $reviews ) {
        echo '<p>Šim produktam vēl nav atsauksmju.</p>';
        return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Vērtējums</th><th>Virsraksts</th><th>Autors</th><th>Statuss</th><th>Datums</th><th></th></tr></thead>';
    echo '<tbody>';

    foreach ( $reviews as $review ) {
        $rating = get_post_meta( $review->ID, '_review_rating', true );
        $stars  = $rating ? str_repeat( '⭐', intval( $rating ) ) : '—';
        $status = ( 'publish' === $review->post_status ) ? 'Publicēta' : 'Gaida moderāciju';

        printf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">Rediģēt</a></td></tr>',
            $stars,
            esc_html( $review->post_title ),
            esc_html( get_the_author_meta( 'display_name', $review->post_author ) ),
            esc_html( $status ),
            esc_html( get_the_date( '', $review->ID ) ),
            esc_url( get_edit_post_link( $review->ID ) )
        );
    }

    echo '</tbody></table>';
}
