<?php
/*
Plugin Name: wechat synchronize
Plugin URI: https://github.com/zhaofeng-shu33/wechat_synchronize_to_wordpress
Description: synchronize wechat articles to wordpress website
Author: zhaofeng-shu33
Version: 0.1
Author URI: https://github.com/zhaofeng-shu33
*/


if (is_admin()) {
	add_action('admin_menu', 'ws_admin_menu');
}
function ws_admin_menu(){
    //add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
    add_options_page('ws options', 'ws', 'manage_options', 'ws-unique-identifier', 'ws_plugin_options');
    add_action('admin_init', 'register_ws_settings');
}
function register_ws_settings(){
    register_setting('ws-settings-group', 'appid');
    register_setting('ws-settings-group', 'appsecret');
    add_option('access_token');
}
function ws_plugin_options(){
    require_once 'setting-page.php';
}

require_once "synchronize_api.php";
require_once 'insert_by_url.php';

function ws_process_request(){
    // if no post data, return 
    $sync_history = isset($_REQUEST['ws_history']) ? $_REQUEST['ws_history'] == 'ws_Yes' : false;
    if($sync_history){
            $return_array = ws_get_history_url();
    }
    else{
        $urls_str = isset($_REQUEST['given_urls']) ? $_REQUEST['given_urls'] : '';
        if($urls_str != ''){
            $url_list = explode("\n", $urls_str);
            // file_put_contents($file, '');                    
            $return_array = ws_insert_by_urls($url_list);
        }
        else{
            $return_array = array('post_id' => -9, 'err_msg' => 'no urls are given');
        }
    }
    echo json_encode($return_array);
    wp_die();
}
add_action( 'wp_ajax_ws_process_request', 'ws_process_request' );
?>
