<?php
/**
 * Plugin Name: WPRTSP ActiveCampaign Integration
 * Description: ActiveCampaign Integration for WP Real-Time Social-Proof
 * Version:     0.1
 * Plugin URI:  https://wp-social-proof.com
 * Author:      Shivanand Sharma
 * Author URI:  https://wp-social-proof.com
 * Text Domain: wprtspac
 * License:     Copyright 2019 WP-Social-Proof.Com
 * Tags: social proof, conversion, ctr, ecommerce, marketing, popup, woocommerce, easy digital downloads, newsletter, optin, signup, sales triggers
 */

/*
Copyright 2019 Shivanand Sharma
*/

class WPRTSP_ActiveCampaign{

    static function get_instance() {

		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self;
			$instance->setup_includes();
			$instance->setup_actions();
		}
		return $instance;
    }

    function setup_includes(){
        require_once('includes/ActiveCampaign.class.php');
    }

    function llog($str){
        echo '<pre>';
        print_r($str);
        echo '</pre>';
    }

    function setup_actions(){
        add_action( 'wprtsp_add_meta_boxes', array($this, 'add_meta_boxes') );

        add_filter( 'wprtsp_sanitize', array($this, 'sanitize') );
        add_filter('wprtsp_shop_type',array($this, 'add_active_campaign'));

        add_action( 'wp_ajax_wprtsp_activecampaign_connect', array( $this, 'activecampaign_connect' ));
        add_action( 'wp_ajax_nopriv_wprtsp_activecampaign_connect', '__return_false');
        
    }
    
    function activecampaign_connect(){
        //wp_send_json($_REQUEST);
        check_ajax_referer('wprtsp_activecampaign_connect', 'activecampaign_connect_nonce');
        $apiurl = !empty($_REQUEST['apiurl']) ? trim(sanitize_text_field($_REQUEST['apiurl'])): false; //https://kardaruchikagwl.api-us1.com
        $apikey = !empty($_REQUEST['apikey']) ? trim(sanitize_text_field($_REQUEST['apikey'])): false; //https://kardaruchikagwl.api-us1.com
        if( ! $apikey  || ! $apiurl ) {
            wp_send_json_error('Missing Credentials');
        }
        //$apiurl = trailingslashit( $apiurl ) . 'api/3/';
        $ac = new ActiveCampaign($apiurl, $apikey);
        if (!(int)$ac->credentials_test()) {
            wp_send_json_error('Invalid Credentials');
        }
        else{
            $account = $ac->api('account/view');
            $lists = $ac->api('list/list?ids=all');
            $contacts = $ac->api('contact/list?listid=1&limit=100');
            wp_send_json_success($contacts);
            wp_send_json_success($account);
        }
    }

    function add_active_campaign($shops){
        $shops[] = 'ActiveCampaign';
        return $shops;
    }

    function add_meta_boxes(){
        add_meta_box( 'social-proof-activecampaign', __( 'Active Camapign', 'wprtsp' ), array($this, 'activecampaign_meta_box'), 'socialproof', 'normal');
    }

    function defaults($defaults = array()){

        /* Additional routines */
        $defaults['activecampaign_apiurl'] = 'https://kardaruchikagwl.api-us1.com';
        $defaults['activecampaign_apikey'] = '8b148c8e3e018445a8253410279b4c0528313e4f4283b9ef27081b1d317044b1dd788640';
        $defaults['activecampaign_connected'] = false;

        return $defaults;
    }
    
    function activecampaign_meta_box(){
        global $post;
        $wprtsp = WPRTSP::get_instance();
        $defaults = $this->defaults();
        $settings = get_post_meta($post->ID, '_socialproof', true);
        if(! $settings) {
            $settings = $defaults;
        }

        $settings = $this->sanitize($settings);

        $activecampaign_apiurl = $settings['activecampaign_apiurl'];
        $activecampaign_apikey = $settings['activecampaign_apikey'];
        ?>
        <table id="tbl_activecampaign" class="wprtsp_tbl wprtsp_tbl_activecampaign">
            <tr>
                <th width="30%"></th>
                <th width="70%"></th>
            </tr>
            <tr>
                <td><div class="wprtsp-help-tip"><div class="wprtsp-help-content"><p>Please provide your ActiveCampaign API URL here. It's of the format: <strong>https://&lt;your-account&gt;.api-us1.com</strong>. Your API URL can be found in your account on the My Settings page under the "Developer" tab.</p></div></div><label for="wprtsp[activecampaign_apiurl]">Enter API URL</label></td>
                <td>
                    <input id="wprtsp_activecampaign_apiurl" name="wprtsp[activecampaign_apiurl]" type="url" value="<?php echo $activecampaign_apiurl ?>" placeholder="https://<your-account>.api-us1.com" class="widefat"  />
                </td>
            </tr>
            <tr>
                <td><div class="wprtsp-help-tip"><div class="wprtsp-help-content"><p>Please provide your ActiveCampaign API Key here. Your API key can be found in your account on the Settings page under the "Developer" tab. Each user in your ActiveCampaign account has their own unique API key.</p></div></div><label for="wprtsp[activecampaign_apikey]">Enter API Key</label></td>
                <td>
                    <input id="wprtsp_activecampaign_apikey" name="wprtsp[activecampaign_apikey]" type="text" value="<?php echo $activecampaign_apikey ?>" class="widefat" />
                </td>
            </tr>
        </table>
        <script type="text/javascript">
            jQuery( document ).ready(function($) {
                $('#activecampaign_connect').click(function(e){
                    e.preventDefault();
                    console.log('clicked');
                    activecampaign_connect = {
                        activecampaign_connect_nonce: '<?php echo wp_create_nonce( 'wprtsp_activecampaign_connect' ); ?>',
                        action                      : 'wprtsp_activecampaign_connect',
                        apiurl                      : $('#wprtsp_activecampaign_apiurl').val(),
                        apikey                      : $('#wprtsp_activecampaign_apikey').val(),
                    };
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data:  activecampaign_connect,
                        complete: function(jqXHR, textStatus){
                            console.dir(jqXHR);
                            console.dir(textStatus);
                        }
                    });
                });
            });
        </script>

        <?php
        echo '<p>';
        submit_button('Connect', 'secondary', 'activecampaign_connect', false );
        echo '</p>';
    }

    function sanitize($request = array()){
        $request['activecampaign_apiurl'] = array_key_exists('activecampaign_apiurl', $request)? sanitize_text_field( $request['activecampaign_apiurl'] ) : '';
        $request['activecampaign_apikey'] = array_key_exists('activecampaign_apikey', $request)? sanitize_text_field( $request['activecampaign_apikey'] ) : '';

        return $request;
    }


}

function wprtsp_activecampaign() {
	return WPRTSP_ActiveCampaign::get_instance();
}

wprtsp_activecampaign();