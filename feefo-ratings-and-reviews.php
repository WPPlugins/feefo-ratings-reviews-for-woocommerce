<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Feefo Ratings & Reviews
 * Plugin URI:        http://www.feefo.com
 * Description:       Feefo is the most trusted ratings and reviews platform in the world. Increase traffic, sales and business insights â€“ plus much more. Install the Feefo plugin and start collecting trusted reviews from your genuine customers, today!
 * Version:           1.0.2
 * Author:            Feefo
 * Author URI:        http://www.feefo.com
 * Requires at least: 4.1
 * Tested up to: 4.7
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       feefo-ratings-and-reviews
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$api_prefix = 'https://ww2.feefo.com/api';

/**
 * used in the activation hook
 */
function FEEFO_wc_wp_setup_on_activation() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    update_option( 'feefo_wc_wp_just_installed', 'yes' );
    $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
    check_admin_referer( "activate-plugin_{$plugin}" );
}

/**
 * used in the deactivation hook
 */
function FEEFO_wc_wp_setup_on_deactivation() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
    check_admin_referer( "deactivate-plugin_{$plugin}" );
}

/**
 * checks if woocommerce is activated
 */
function FEEFO_wc_wp_is_woocommerce_activated() {
    $plugin = 'woocommerce/woocommerce.php';
    $network_active = false;

    if ( is_multisite() ) {
        $plugins = get_site_option( 'active_sitewide_plugins' );
        if ( isset( $plugins[$plugin] ) ) {
            $network_active = true;
        }
    }

    return in_array( $plugin, get_option( 'active_plugins' ) ) || $network_active;
}

/**
 * displays an admin notice when woocommerce is not activated
 */
function FEEFO_wc_wp_woocommerce_notice() {
    $errors = array( 'WooCommerce is not activated.' );

    printf(
        '<div class="error"><p><b>%1$s</b></p>
        	<p><i>%2$s</i> needs WooCommerce to be installed and activated.</p></div>',
        join( '</p><p>', $errors ),
        FEEFO_wc_wp_appname()
    );
}

/**
 * returns the plugin text domain string
 * @return string  text-domain
 */
function FEEFO_wc_wp_text_domain() {
    return 'feefo-ratings-and-reviews';
}

/**
 * enqueue styles
 * now uses wp default constants as plugins_url was returning 403 for a few merchants.
 */
function FEEFO_wc_wp_admin_styles() {
	$PLUGIN_URL = WP_PLUGIN_URL;
    $BASE = !empty( $PLUGIN_URL ) ? WP_PLUGIN_URL : WPMU_PLUGIN_DIR;
    $BASE_SUFFIX = "{$BASE}/feefo-ratings-reviews-for-woocommerce/";
    $PROTOCOL = strtolower( parse_url($BASE_SUFFIX, PHP_URL_SCHEME) )  === false ? '' : strtolower( parse_url($BASE_SUFFIX, PHP_URL_SCHEME) );

    str_replace ( $PROTOCOL.':', '', $BASE_SUFFIX );//did this to avoid broweser blocking css loading due to mixed content

    wp_enqueue_style( 'feefoSideLogoStylesheet', "{$BASE_SUFFIX}assets/css/side-menu-logo.css" );
    wp_enqueue_style( 'feefoIframeStylesheet', "{$BASE_SUFFIX}assets/css/iframe-display.css" );
    wp_enqueue_style( 'feefoStartPluginSetup', "{$BASE_SUFFIX}assets/css/start-plugin-setup-display.css" );
}

/**
 * sets up the Feefo Menu on WordPress
 */
function FEEFO_wc_wp_menu_settings() {

    if ( get_option( 'feefo_wc_wp_just_installed', 'yes' ) ) {

        delete_option( 'feefo_wc_wp_just_installed' );
        add_action( 'admin_enqueue_scripts', 'FEEFO_wc_wp_admin_styles' );
        add_menu_page( 'Feefo', 'Feefo', 'manage_options', FEEFO_wc_wp_text_domain(), 'FEEFO_wc_wp_load', 'none', null );
    }
}

/**
 * creates TempMerchantInfo object
 */
function FEEFO_wc_wp_create_temp_info( $route ) {

    //define and load current user
    $current_user = wp_get_current_user();
    $header_image_url = get_header_image();
    $show_owner = htmlspecialchars_decode( $current_user->display_name );

    $parameters = array(
        'merchantDomain' => FEEFO_wc_wp_home_url_no_protocol(),
        'merchantName' => htmlspecialchars_decode( get_bloginfo( 'name' ) ),
        'merchantDescription' => htmlspecialchars_decode( get_bloginfo( 'description' ) ),
        'merchantUrl' => get_bloginfo( 'url' ),
        'merchantLanguage' => get_bloginfo( 'language' ),
        'merchantAdminUserEmail' => $current_user->user_email,
        'redirectUrl'			=>  admin_url( 'admin.php?page=' . FEEFO_wc_wp_text_domain() ),
        'merchantShopOwner' => $show_owner,
        'merchantImageUrl' => $header_image_url,
        'eCommerceVersion' => get_option( 'woocommerce_version', '' )
    );

    $requestHeaders = array(
        'Content-Type' => 'application/json'
    );

    $response = wp_remote_post( $route, array(
            'method' => 'POST',
            'body' => json_encode( $parameters ),
            'headers' => $requestHeaders,
            'cookies' => array()
        )
    );

    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();

    } else {
        return $response;
    }
}

/**
 * returns the home_url value without the protocol
 * @return home_url without the protocol
 */
function FEEFO_wc_wp_home_url_no_protocol() {
    $home_url = get_bloginfo( 'url' );
    $home_url_protocol = parse_url( $home_url, PHP_URL_SCHEME );

    $home_url__no_protocol = str_ireplace( $home_url_protocol . '://' , '' , $home_url );
    return $home_url__no_protocol;
}

/**
 * loads the needed Feefo page
 */
function FEEFO_wc_wp_load() {

    //when Feefo menu is clicked
    if ( isset( $_REQUEST['page'] )  && $_REQUEST['page'] == FEEFO_wc_wp_text_domain() ) {

        //confirm WooCommerce has been installed and activated
        if ( FEEFO_wc_wp_is_woocommerce_activated() || class_exists( 'WooCommerce' ) ) {

            //Has this Merchant been linked to Feefo?
            $merchant_linked_response = FEEFO_wp_wc_merchant_linked();

            if ( $merchant_linked_response == 200 ) {
                //proceed with flow
                FEEFO_wc_wp_init();
                FEEFO_wc_wp_set_up_widget_options(); //create widget options

            } else if ( $merchant_linked_response == 404 ) {
                delete_option( 'feefo_wc_wp_stored_options' );
                delete_option( 'feefo_wc_wp_widget_options' );

                //redirect for woocommerce authorization
                ?>

                <div id="plugin-setup-div" >
                    <h1 id="plugin-setup-h1" >Feefo Plugin Setup</h1>
                    <a id="plugin-setup-a" href="<?php echo FEEFO_wp_wc_woocommerce_authentication_url(); ?>"> Start Plugin Setup </a>
                </div>

                <?php
            }

        } else {

            FEEFO_wc_wp_woocommerce_notice();
        }

    }

    //returning from woocommerce authentication endpoint
    if ( isset( $_REQUEST['success'] ) && $_REQUEST['success'] == 1 && isset( $_REQUEST['page'] )  && $_REQUEST['page'] == FEEFO_wc_wp_text_domain() ) {
        //proceed with flow
        FEEFO_wc_wp_init();
    }
}

/**
 * returns the woocommerce authentication url used to enable authorization to Merchants
 * woocommerce shop data
 */
function FEEFO_wp_wc_woocommerce_authentication_url() {
    $store_url = get_bloginfo( $show = 'url');
    $endpoint  = '/wc-auth/v1/authorize';
    $params    = array(
        'app_name'     => FEEFO_wc_wp_appname(),
        'scope'        => 'read',
        'user_id'      => FEEFO_wc_wp_home_url_no_protocol(),
        'return_url'   => admin_url( 'admin.php?page=' . FEEFO_wc_wp_text_domain() ) ,
        'callback_url' => $GLOBALS['api_prefix'] . '/ecommerce/plugin/woocommerce/register/callback'
    );

    $redirectEndPoint = $store_url . $endpoint . '?' . http_build_query( $params );;

    return $redirectEndPoint;
}

/**
 * returns this apps name
 */
function FEEFO_wc_wp_appname() {
    $name = get_file_data( __FILE__, array ( 'Plugin Name' ), 'plugin' );
    return $name[0];
}

/**
 *
 */
function FEEFO_wc_wp_init() {

    global $wpdb;

    $table_name = $wpdb->prefix . 'woocommerce_api_keys';

    $feefo_wc_wp_stored_options_array = get_option( 'feefo_wc_wp_stored_options', array() );

    if ( ! empty( $feefo_wc_wp_stored_options_array ) ) {

        $feefo_wc_wp_stored_options_array = get_option( 'feefo_wc_wp_stored_options' );
    }

    $plugin_id = empty( $feefo_wc_wp_stored_options_array['plugin_id'] ) ? '' : $feefo_wc_wp_stored_options_array['plugin_id'];

    $verification_response =  FEEFO_wc_wp_app_entry( $plugin_id );

    //only display config page based on configurationUri and plugin_id being available
    if ( isset( $verification_response->configurationUri ) && !empty( $verification_response->configurationUri ) ) {
        FEEFO_wc_wp_load_in_frame( $verification_response->configurationUri );

    } else if ( isset( $verification_response->registrationUri ) && !empty( $verification_response->registrationUri ) ) {

        FEEFO_wc_wp_load_in_frame( $verification_response->registrationUri );

    } else {

        if ( empty( $verification_response->key_id ) ) {

            FEEFO_wc_wp_load_error_page();

        } else {

            $verification_access_key_id = $verification_response->key_id;

            $wpdb->flush();
            $woocommerce_details = $wpdb->get_row("SELECT * FROM $table_name WHERE key_id = $verification_access_key_id");

            //confirm no errors on query execution
            if (empty($wpdb->last_error)) {

                $access_key = $woocommerce_details->truncated_key;

                //Merchant has just granted feefo authorisation to their woocommerce orders etc
                //Now verify credentials
                if (!empty( $verification_response->consumer_key ) && (strpos( $verification_response->consumer_key, $access_key ) !== false)) {

                    $create_temp_info_route = $GLOBALS['api_prefix'] . '/ecommerce/plugin/woocommerce/register/temp/' . $access_key;

                    $create_temp_info_response = FEEFO_wc_wp_create_temp_info($create_temp_info_route);

                    //confirm a 200 was received
                    if (wp_remote_retrieve_response_code($create_temp_info_response) == 200) {

                        $create_temp_info_response_json = json_decode(wp_remote_retrieve_body($create_temp_info_response), true);

                        $url_to_render = $create_temp_info_response_json['registrationUri'];

                        FEEFO_wc_wp_create_feefo_options($create_temp_info_response_json, $url_to_render);//create Feefo options
                        FEEFO_wc_wp_load_in_frame($url_to_render); //render page
                    }

                } else {
                    FEEFO_wc_wp_load_error_page();
                }
            }

        }

    }
}

function FEEFO_wc_wp_app_entry( $plugin_id ) {
    $verify_merchant_route = $GLOBALS['api_prefix'] . '/ecommerce/plugin/woocommerce/register/entry';

    $parameters = array(
        'merchant_domain' => FEEFO_wc_wp_home_url_no_protocol(),
        'plugin_id' => ( isset( $plugin_id ) && ! empty( $plugin_id ) ) ? $plugin_id : ''
    );

    $requestHeaders = array(
        'Content-Type' => 'application/json'
    );

    $response = wp_remote_post( $verify_merchant_route, array(
            'method' => 'POST',
            'body' => json_encode( $parameters ),
            'headers' => $requestHeaders,
            'cookies' => array()
        )
    );


    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        //echo $response;
        exit( var_dump( $error_message) );

    } else {
        return json_decode( wp_remote_retrieve_body( $response ) );
    }
}

/**
 * loads Feefo default error page
 */
function FEEFO_wc_wp_load_error_page() {
    $error_url = 'https://register.feefo.com/#/error/plugin-installation-general';
    FEEFO_wc_wp_load_in_frame( $error_url );
}

/**
 * checks and returns a 200 if this is a fresh installation else 404
 */
function FEEFO_wp_wc_merchant_linked() {

    $merchant_linked_route = '/ecommerce/plugin/woocommerce/register/verify';
    $merchant_linked_endpoint = $GLOBALS['api_prefix'] . $merchant_linked_route;

    $args = array(
        'body' => array(
            "merchant_domain" => FEEFO_wc_wp_home_url_no_protocol()
        )
    );

    $response = wp_remote_post( $merchant_linked_endpoint, $args );

    if ( is_wp_error( $response ) ) {

        //display error page

    } else {
        return wp_remote_retrieve_response_code( $response );
    }
}

/**
 * creates Feefo stored options
 */
function FEEFO_wc_wp_create_feefo_options( $jsonString, $url ) {
    if ( empty( $jsonString ) ) {
        return;

    } else {

        $feefo_wc_wp_stored_options = array(
            'plugin_id' 	  => $jsonString[ 'options' ][ 'plugin_id' ],
            'merchant_domain' => $jsonString[ 'options' ][ 'merchant_domain' ],
            'key_id'		  => $jsonString[ 'options' ][ 'key_id' ],
            'merchant_id'	  => ( empty( $jsonString[ 'options' ][ 'merchant_id' ] ) ? '' : $jsonString[ 'options' ][ 'merchant_id' ] ),
            'registrationUri' => $url
        );

        update_option( 'feefo_wc_wp_stored_options', $feefo_wc_wp_stored_options );
    }
}

/**
 * sets up Feefo widgets
 */
function FEEFO_wc_wp_set_up_widget_options() {
    wp_cache_flush();
    $stored_options = get_option( 'feefo_wc_wp_stored_options' );
    if ( empty( $stored_options ) ) {
        return;

    } else {
        if ( !empty( $stored_options[ 'plugin_id' ] ) ) {
            $plugin_id = $stored_options[ 'plugin_id' ];
            $widget_endpoint  = '/ecommerce/plugin/register/' . $plugin_id . '/snippetwidgetpreview';
            $widget_route = $GLOBALS[ 'api_prefix' ] . $widget_endpoint;

            $widget_response = wp_remote_get( $widget_route );

            if ( !is_wp_error( $widget_response ) && wp_remote_retrieve_response_code( $widget_response ) == 200 ) {

                $widget_response_body = wp_remote_retrieve_body( $widget_response );
                if ( !empty( $widget_response_body ) ) {

                    $widget_response_json = json_decode( wp_remote_retrieve_body( $widget_response ), true );
                    if ( empty( $widget_response_json->Error ) ) {
                        $feefo_wc_wp_widget_options = array(
                            'service_snippet'  			=> $widget_response_json[ 'snippetsPreview' ][ 'serviceSnippet' ],
                            'products_stars_snippet'	=> $widget_response_json[ 'snippetsPreview' ][ 'productStarsSnippet' ],
                            'product_base_snippet'      => $widget_response_json[ 'snippetsPreview' ][ 'productBaseSnippet' ]
                        );

                        update_option( 'feefo_wc_wp_widget_options', $feefo_wc_wp_widget_options );

                        //config widget display based on options
                        wp_cache_flush();
                        FEEFO_wc_wp_widget_config();
                    }
                }
            }
        }
    }
}

function FEEFO_wc_wp_service_snippet() {
    wp_cache_flush();
    $stored_widget_option = get_option( 'feefo_wc_wp_widget_options' );
    if ( empty( $stored_widget_option ) ) {
        return;
    } else {
        $feefo_service_widget = get_option( 'feefo_service_reviews_widget' );
        if( !empty( $feefo_service_widget ) ) {
            echo $stored_widget_option[ 'service_snippet' ];
        }
    }
}

function FEEFO_wc_wp_product_stars_snippet() {
    wp_cache_flush();
    $stored_widget_option = get_option( 'feefo_wc_wp_widget_options' );
    $feefo_product_widget = get_option( 'feefo_product_reviews_widget' );
    $feefo_tab_option = get_option( 'feefo_product_widget_placement' );

    if ( empty( $stored_widget_option ) ) {
        return;
    } else if ( !empty( $feefo_product_widget ) && $feefo_product_widget == 'yes' && ( !empty( $feefo_tab_option ) && $feefo_tab_option !== 'CUSTOM') ){
        echo $stored_widget_option[ 'products_stars_snippet' ];
    }
}

function FEEFO_wc_wp_product_base_snippet() {
    global $post;
    $feefo_reviews_tab = get_option( 'feefo_product_reviews_widget' );
    $feefo_tab_option = get_option( 'feefo_product_widget_placement' );

    if ( !empty( $feefo_reviews_tab ) && $feefo_reviews_tab == 'yes' && ( !empty( $feefo_tab_option ) && $feefo_tab_option !== 'CUSTOM') ) {

        if( $post->post_parent != 0 ) {
            FEEFO_wc_wp_product_base_snippet_div($post->post_parent);

        } else if( $post->ID != 0 ){
            FEEFO_wc_wp_product_base_snippet_div( $post->ID );
        }

    } else {
        echo '';
    }
}

function FEEFO_wc_wp_product_base_snippet_div( $shop_product_id ) {
    echo '<div id="feefo-product-review-widgetId" class="feefo-review-widget-product" data-feefo-product-id="'. $shop_product_id .'"></div>';
}

/**
 * renders the returned url page
 */
function FEEFO_wc_wp_load_in_frame( $url_to_render ) {

    ?>
    <div id="root">
        <iframe src="<?php echo $url_to_render; ?>" >
            Your browser does not support inline frames.
        </iframe>
    </div>
    <?php
}

/**
 * decides what to do with widgets
 * @param $tabs
 * @return mixed
 */
function FEEFO_wc_wp_feefo_tab( $tabs ) {
    $feefo_tab_option = get_option( 'feefo_product_widget_placement' );
    $feefo_service_widget_option = get_option( 'feefo_service_reviews_widget' );
    $feefo_product_reviews = get_option( 'feefo_product_reviews_widget' );

    //unset feefo tab if service is 'no
    if ( !empty( $feefo_service_widget_option ) && $feefo_service_widget_option == 'no' ) {
        unset( $tabs[ 'feefo_tab' ]  );      	// Remove the Feefo Reviews tab
    }

    if ( !empty( $feefo_tab_option) && $feefo_tab_option !== 'TAB' ) {
        unset( $tabs[ 'feefo_tab' ]  );      	// Remove the Feefo Reviews tab
    }

    if ( !empty( $feefo_tab_option) && $feefo_product_reviews == 'no' ) {
        unset( $tabs[ 'feefo_tab' ]  );      	// Remove the Feefo Reviews tab
    }

    //	 Adds the new tab
    if ( !empty( $feefo_tab_option ) && $feefo_tab_option == "TAB" && ( !empty( $feefo_product_reviews ) && $feefo_product_reviews == 'yes' ) ) {
        $tabs['feefo_tab'] = array(
            'title' 	=> __( 'Feefo Reviews', 'woocommerce' ),
            'priority' 	=> 60,
            'callback' 	=> 'FEEFO_wc_wp_product_base_snippet'
        );
    }

    $feefo_stored_option = get_option( 'feefo_disable_woocommerce_review_tab' );
    if ( !empty( $feefo_stored_option ) && $feefo_stored_option == 'yes' ) {
        unset( $tabs[ 'reviews' ]  );      	// Remove the Feefo Reviews tab
        update_option( 'woocommerce_enable_review_rating', 'no' );
        update_option( 'woocommerce_review_rating_required', 'no' );
    }

    return $tabs;
}

/**
 * get and store widget display configurations
 */
function FEEFO_wc_wp_widget_config() {

    wp_cache_flush();
    $stored_options = get_option('feefo_wc_wp_stored_options');
    if (empty($stored_options)) {
        return;

    } else {
        if (!empty($stored_options['plugin_id'])) {
            $plugin_id = $stored_options['plugin_id'];

            $plugin_info_endpoint = '/ecommerce/plugin/' . $plugin_id;
            $plugin_info_route = $GLOBALS['api_prefix'] . $plugin_info_endpoint;

            $plugin_info_response = wp_remote_get($plugin_info_route);

            $plugin_info_response_json = json_decode(wp_remote_retrieve_body($plugin_info_response), true);


            if ( !is_wp_error( $plugin_info_response )  && wp_remote_retrieve_response_code( $plugin_info_response ) == 200 ) {

                $plugin_info_body = wp_remote_retrieve_body( $plugin_info_response );
                if ( !empty( $plugin_info_body ) ) {
                    if (empty($plugin_info_response_json->Error)) {

                        $platform_settings = $plugin_info_response_json['pluginConfig']['platformSettings'];


                        //disable woocommerce review tab option
                        if ( $platform_settings['nativePlatformReviewSystem'] == 1 ) {

                            update_option( 'feefo_disable_woocommerce_review_tab', 'yes' );

                        } else {
                            update_option( 'feefo_disable_woocommerce_review_tab', 'no' );
                        }

                        //product widget
                        if ( $platform_settings['productReviewsWidget'] == 1) {
                            update_option( 'feefo_product_reviews_widget', 'yes' );

                            update_option( 'feefo_product_widget_placement', $platform_settings['productWidgetPlacement'] );

                        } else if( empty( $platform_settings['productReviewsWidget'] ) ){
                            update_option( 'feefo_product_reviews_widget', 'no' );
                        }

                        //service widget display option. Note without service widget the product won't work
                        if ( $platform_settings[ 'serviceReviewsWidget' ] == 1 ) {
                            update_option( 'feefo_service_reviews_widget', 'yes' );
                        } else {
                            update_option( 'feefo_service_reviews_widget', 'no' );
                        }

                    }
                }
            }
        }
    }
}

/**
 * Custom code for including Feefo product widget
 */
function FEEFO_wc_wp_product_widget_snippet_custom() {
    echo '<div id="feefo-product-review-widgetId" class="feefo-review-widget-product" data-feefo-product-id="'. get_the_ID() .'"></div>';
}

/**
 * Custom code for including Feefo product stars
 */
function FEEFO_wc_wp_product_stars_snippet_custom() {
    $stored_widget_option = get_option('feefo_wc_wp_widget_options');
    echo $stored_widget_option['products_stars_snippet']; //Product Stars
}

//register hooks with associated methods
register_activation_hook(   __FILE__, 'FEEFO_wc_wp_setup_on_activation' );
register_deactivation_hook( __FILE__, 'FEEFO_wc_wp_setup_on_deactivation' );

add_action( 'admin_menu', 'FEEFO_wc_wp_menu_settings' );

add_action( 'init', function () {
    add_filter('woocommerce_product_tabs', 'FEEFO_wc_wp_feefo_tab');
    add_action( 'wp_footer', 'FEEFO_wc_wp_service_snippet' );
    add_action( 'woocommerce_after_add_to_cart_button', 'FEEFO_wc_wp_product_stars_snippet' );
    add_action( 'woocommerce_after_single_product_summary', 'FEEFO_wc_wp_product_base_snippet');
} );