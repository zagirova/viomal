<?php
namespace Webtomizer\WCDP;

if( ! defined( 'ABSPATH' ) ){
    exit;
}
use stdClass;

/**
 * Automatic updater client
 */
class Envato_items_Update_Client{

    /**
     * @var $item_id
     * @description Envato Item ID
     */
    private $item_id;
    /**
     * @var $purchase_code
     * @description Purchase code inserted by customer
     */
    private $purchase_code;
    /**
     * @var
     * @description Plugin file
     */
    private $plugin_file;
    /**
     * @var $update_endpoint
     * @description update endpoint
     */
    private $update_endpoint;
    /**
     * @var $verify_purchase_endpoint
     * @description verify purchase endpoint
     */
    private $verify_purchase_endpoint;

    /**
     * Constructor
     * @param $item_id
     * @param $plugin_file
     * @param $update_endpoint
     * @param $verify_purchase_endppint
     * @param $purchase_code
     */
    function __construct($item_id , $plugin_file , $update_endpoint , $verify_purchase_endppint , $purchase_code ){

        $this->item_id = $item_id;
        $this->plugin_file = $plugin_file;
        $this->update_endpoint = $update_endpoint;
        $this->verify_purchase_endpoint = $verify_purchase_endppint;
        $this->purchase_code = $purchase_code;
        $this->enable();
    }

    /**
     *  enable the client functionality
     * @return void
     */
    function enable(){
        add_filter( 'pre_set_site_transient_update_plugins' , array( $this , 'check_for_update' ) );
        add_filter( 'plugins_api' , array( $this , 'plugin_information' ) ,20,3);
        add_action("in_plugin_update_message-{$this->plugin_file}", array( $this , 'update_message' ) );
    }

    /**
     *  update message
     * @return void
     */
    function update_message(){

        $purchase_code_status = $this->verify_purchase_code( $this->purchase_code );
        if( $purchase_code_status === 'invalid' ){
            $notice = sprintf('<b>'. wp_kses(__('Please <a href="%s"> enter your purchase code </a> to receive automatic updates', 'woocommerce-deposits'), array('a' => array('href' => array(), 'target' => array()))) . '</b>', admin_url('/admin.php?page=wc-settings&tab=wc-deposits&section=auto_updates'));
            echo '<br/><span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$notice.'</span>';
        }
    }

    /**
     *  retrieve plugin information from update endpoint
     * @param $data
     * @param $action
     * @param $args
     * @return mixed|stdClass
     */
    function plugin_information($data, $action, $args){

        if($action !== 'plugin_information') return $data;

        if ( ! isset( $args->slug ) || ( $args->slug !== 'woocommerce-deposits' ) ) return $data;
        $cache_key = 'woocommerce-deposits_api_request_' . substr( md5( serialize( 'woocommerce-deposits' ) ), 0, 15 );

        $api_request_transient = get_site_transient( $cache_key );

        if ( empty( $api_request_transient ) ) {
            $api_response = $this->get_remote_response();
            $api_request_transient = new \stdClass();

            $api_request_transient->name = 'WooCommerce Deposits';
            $api_request_transient->slug = 'woocommerce-deposits';
            $api_request_transient->author = '<a href="https://webtomizer.com/">Webtomizer</a>';
            $api_request_transient->homepage = 'https://woocommerce-deposits.com/';
            $api_request_transient->requires = $api_response['requires'];

            $api_request_transient->version = $api_response['new_version'];
//            $api_request_transient->last_updated = $api_response['last_updated'];
            $api_request_transient->download_link = $api_response['package'];
            $api_request_transient->banners = [
                'high' => 'https://res.cloudinary.com/https-codecraze-io/image/upload/v1611902751/preview_vtm9tw.png',
                'low' => 'https://res.cloudinary.com/https-codecraze-io/image/upload/v1611902751/preview_vtm9tw.png',
            ];

            $api_request_transient->sections =  $api_response['sections'];

            // Expires in 1 day
            set_site_transient( $cache_key, $api_request_transient, DAY_IN_SECONDS );
        }

        $data = $api_request_transient;

        return $data;

    }


    /**
     *  verify purchase code request
     * @param $purchase_code
     * @return bool|string|\WP_Error
     */
    function verify_purchase_code($purchase_code ) {


        $args = array( 'timeout' => 10,  'body' => array( 'purchase_code' => $purchase_code , 'item_id' => $this->item_id ) );

        $request = wp_remote_post( $this->verify_purchase_endpoint , $args );
        if( is_array( $request ) && $request[ 'response' ][ 'code' ] == 200 ){
            $response = json_decode( wp_remote_retrieve_body( $request ) , true );
            if( is_array($response) && isset( $response[ 'status' ]) && $response[ 'status' ] === 'success'  ){
                return 'valid';
            } else {
                return 'invalid';
            }
        }else {
            //an error occurred
            if(is_wp_error($request)){
                return $request;
            }
            return false;
        }
    }

    /**
     *  get remote response for plugin information and update check
     * @return false|mixed|null
     */
    protected function get_remote_response(){

        $response = false;
        $args = array(  'timeout' => 10,  'body' => array( 'item_id' => $this->item_id , 'purchase_code' => $this->purchase_code , 'domain' => home_url() ) );
        $request = wp_remote_post( $this->update_endpoint , $args );
        if( is_array( $request ) && $request[ 'response' ][ 'code' ] == 200 ){
            $response = json_decode( wp_remote_retrieve_body( $request ) , true );
        }

        return $response;
    }

    /**
     *  check for plugin updates
     * @param $transient
     * @return mixed
     */
    function check_for_update($transient ){
        //		if ( empty( $transient->checked ) ) {
        //			return $transient;
        //		}

        //make api call
        $api_data = $this->get_remote_response();

        //if new update is available add transient
        if( is_array( $api_data ) && isset( $api_data[ 'status' ] ) && $api_data[ 'status' ] === 'success' ){


            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_file );
            if(isset($api_data['new_version']) && version_compare($api_data['new_version'] , $plugin_data[ 'Version' ]) === 1 ) {


                $wp_response = new stdClass();

                //insert data from plugin information
                $wp_response->plugin_name = 'Woocommerce Deposits';
                $wp_response->slug = 'woocommerce-deposits';
                $wp_response->version = $plugin_data[ 'Version' ];
                $wp_response->homepage = $plugin_data[ 'PluginURI' ];
                $wp_response->description = $plugin_data[ 'Description' ];
                //insert data from api response

                //			$wp_response->icons = $api_data[ 'icons' ];
                //			$wp_response->tested = $api_data[ 'tested' ];
                $wp_response->package = $api_data[ 'package' ];
                $wp_response->new_version = $api_data[ 'new_version' ];

                $transient->response[ $this->plugin_file ] = $wp_response;
            }


        }

        return $transient;
    }

}