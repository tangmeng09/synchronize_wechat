<?php
/**
 * @package wechat_synchronize_api
 * @version 1.0
 * @file synchronize_api.php
 */

require "wechat-php-sdk/autoload.php";
use Gaoming13\WechatPhpSdk;
use Gaoming13\WechatPhpSdk\Api;
use Gaoming13\WechatPhpSdk\Utils\HttpCurl;
require_once 'insert_by_url.php';
/**
 * \brief Custom functions to retrieve the access_token from the database
 * @param: void
 * @return valid access token string
 */
function ws_get_access_token(){
  // notice that the access_token is the serialized json string containing the expired time (UTC)
    return get_option('access_token');
}

/**
 * \brief Custom functions to save the access_token to the database
 * @param: access token string
 * @return void
 */
function ws_save_access_token($token){
    update_option('access_token', $token);
}

/**
 * @param: valid access token
 * @return url list of current wechat account prior to current date
 */
function ws_get_history_url(){
    $api = new Api(
	    array(
            'appId' => get_option('appid'),
            'appSecret'	=> get_option('appsecret'),
            'get_access_token' => 'ws_get_access_token',
            'save_access_token' => 'ws_save_access_token'
        )
    );
    list($err, $data) = $api->get_material_count();
    // each time maximal 20 articles fetch is allowed
    $offset = 0;
    
    $url_list = array();
    while($offset < $data->news_count){ //
        list($err, $material) = $api->get_materials('news', $offset, 20);
        // extract urls of each article from $material list and append it to an array
        for($i=0; $i<count($material->item); $i++){ //
            $news_item = $material->item[$i]->content->news_item;
            for($j=0; $j<count($news_item); $j++){
                $url = $news_item[$j]->url;
                array_push($url_list, $url);
            }            
        }
        $offset += 20;
    }
    ws_insert_by_urls($url_list);
}

function ws_process_request(){
    // if no post data, return 
    $sync_history = isset($_REQUEST['ws_history']) ? true : false;
    if(!$sync_history){
        $urls_str = $_REQUEST['given_urls'];
        if($urls_str != ''){
            $url_list = explode("\n", $urls_str);
            // file_put_contents($file, '');                    
            ws_insert_by_urls($url_list);
        }
        else{
            ws_get_history_url();
        }
    }   
}
echo "good";
?>