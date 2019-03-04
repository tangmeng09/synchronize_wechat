<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div>
<h1> synchronize wechat </h1>
<form method="post" action="options.php">
<?php settings_fields('sync_wechat-settings-group'); ?>
 <!--tab implementation in the future -->
 <table class="form-table">
        <tr valign="top">
        <th scope="row">AppId</th>
        <td><input type="text" name="appid" value="<?php echo esc_attr( get_option('appid') ); ?>" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">AppSecret</th>
        <td><input type="text" name="appsecret" value="<?php echo esc_attr( get_option('appsecret') ); ?>" /></td>
        </tr>
 </table>
<?php submit_button(); ?>
</form>
<div id="url" style="display:flex;flex-direction:column;">
<textarea type="text" name="given_urls" class="large-text code" rows="3" placeholder="Paste urls of articles here, one url per line..."></textarea>         
<div>
<button class="button button-primary" id="expandControl" style="margin-bottom:10px; margin-top:10px;">Advanced</button>
</div>
<table class="form-table" style="display:none;" id="synchronize-table">
    <tr>
     <th scope="row"><label for="sync_wechat_history">Get preivous articles</label></th>
     <td>
        <select name="sync_wechat_history">
                <option value="sync_wechat_Yes" selected>Yes</option>
                <option value="sync_wechat_No">No</option>
        </select>
     </td>
     <th scope="row"><label for="change_post_time">Use current time to publish</label></th>
     <td>
        <select name="change_post_time">
                <option value="sync_wechat_Yes">Yes</option>
                <option value="sync_wechat_No" selected>No</option>
        </select>
     </td>     
     </tr>    
    <tr>
     <th scope="row"><label for="keep_style">Keep original style</label></th>
     <td>
        <select name="keep_style">
                <option value="keep" selected>Yes</option>
                <option value="remove">No</option>
        </select>
     </td>
     <th scope="row"><label for="post_status">Default post status</label></th>
     <td>
        <select name="post_status">
                <option value="publish" selected>publish</option>
                <option value="pending">pending</option>
                <option value="pending">draft</option>                
        </select>
     </td>       
     </tr>
    <tr>
     <th scope="row"><label for="keep_source">Keep original source info</label></th>
     <td>
        <select name="keep_source">
                <option value="keep" selected>Yes</option>
                <option value="remove">No</option>
        </select>
     </td>
     <th scope="row"><label for="debug">Debug mode</label></th>
     <td>
        <select name="debug">
                <option value="off" selected>off</option>
                <option value="on">basic</option>
                <option value="detail">detail</option>                
        </select>
     </td>      
     </tr>
    <tr>
     <th scope="row"><label for="check_date">Check date</label></th>
     <td>
        <select name="check_date">
                <option value="Yes" selected>Yes</option>
                <option value="No">No</option>
        </select>
     </td>
     <th scope="row"><label for="offset">Offset</label></th>
     <td>
         <input name="offset" type="number" step="1" value="0"/>
     </td>      
     </tr>     
</table>
<div>
<button class="button button-primary" id="Synchronize" style="margin-bottom:10px;">Synchronize</button>
</div>
</div>
<textarea id="console" class="large-text code" rows="1" style="display:none"></textarea>
<div id="dialog" title="Prompt">
  <p id="dialogText"></p>
</div>
<script>
<?php 
$wp_scripts = wp_scripts();
$jquery_ui_ver = $wp_scripts->registered['jquery-ui-core']->ver;

wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui-css', plugins_url('css/jquery-ui-'. $jquery_ui_ver .'.min.css', __FILE__)); 
?>
    jQuery(document).ready(function(){
        jQuery("#dialog").dialog({autoOpen: false, modal: true});
    });
    jQuery("#expandControl").click(function () {
        var header = jQuery(this);
        var content = jQuery("#synchronize-table");
        content.slideToggle(500, function () {
            header.text(function () {
                return content.is(":visible") ? "Collapse" : "Advanced";
            });
        });
    });
    var sync_wechat_url_list = [];
    var sync_wechat_url_global_id = 0;
    var sync_wechat_global_offset = 0;
    var sync_wechat_is_debug = 0;
    var sync_wechat_get_url_list_termination = false;
    var sync_wechat_console = jQuery("#console");
    var sync_wechat_submit_total_count = 0;
    function sync_wechat_submit_single(url){
        var data_ = {
            'action': 'sync_wechat_process_request',
            'url_id': sync_wechat_url_global_id,
            'given_urls': url,
            'keep_source': jQuery('select[name="keep_source"]').val(),
            'keep_style': jQuery('select[name="keep_style"]').val(),
            'debug': jQuery('select[name="debug"]').val()
        };
        sync_wechat_url_global_id += 1;
        jQuery.ajax({
             url: ajaxurl,
             type: "POST",
             timeout: 50000,
             data: data_,
             success: function(data, textStatus, jqXHR){
                 var data_json = JSON.parse(data);
                 var extra_info = '';
                 if(data_json['status_code'] < 0 && sync_wechat_is_debug == 2)
                    extra_info = '*' + url;
                 if(data_json['status_code'] >= 0)
                    sync_wechat_submit_total_count += 1; 
                 sync_wechat_console_writeline(data + extra_info)
                 var new_url = sync_wechat_url_list.pop();
                 if(new_url != undefined){
                     sync_wechat_submit_single(new_url);
                 }
                 else{
                    if(jQuery('select[name="sync_wechat_history"]').val() == 'sync_wechat_Yes' && sync_wechat_get_url_list_termination == false && jQuery('textarea[name="given_urls"]').val() == "")
                        sync_wechat_get_news();
                 }
                 if(sync_wechat_url_list.length == 0){
                     // notify the user the job finished.
                    var articles = "articles";
                    if(sync_wechat_submit_total_count == 1)
                        articles = 'article;'
                    if(sync_wechat_submit_total_count == 0)
                        jQuery("#dialogText").text("Synchronize Error!");
                    else
                        jQuery("#dialogText").text("Successfully synchronize " + sync_wechat_submit_total_count + articles);                    
                    jQuery("#dialog").dialog("open");                     
                 }
                     
             },
            error: function(jqXHR, textStatus, errorThrown){
                sync_wechat_console_writeline(textStatus + '*'+ errorThrown);
                if(sync_wechat_is_debug == 2){
                    sync_wechat_console_writeline(url);
                    sync_wechat_console_writeline(jqXHR.responseText);
                }
            }           
        })        
    }
    function sync_wechat_submit_multiple(){
        var submitted_length = sync_wechat_url_list.length;
        for(var i = 0; i < Math.min(5, submitted_length); i++){ 
            var url = sync_wechat_url_list.pop();
            sync_wechat_submit_single(url);
        }          
    }

    //! content added to the console
    function sync_wechat_console_writeline(content, row_add = 1){
        if(sync_wechat_is_debug == 0)
            return
        var console_value = sync_wechat_console.val();
        var row = parseInt(sync_wechat_console.attr("rows"));
        sync_wechat_console.attr("rows", row + row_add);
        sync_wechat_console.val(console_value + content + '\n');
    }
    function sync_wechat_get_news(){

        var data_to_sent = 
                   {'action':'sync_wechat_process_request',
                    'offset': sync_wechat_global_offset,
                    'sync_wechat_history':jQuery('select[name="sync_wechat_history"]').val(),
                    'sync_wechat_date_check': jQuery('select[name="check_date"]').val()
                   };
        sync_wechat_global_offset += 20;           
        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            timeout: 35000,
            data: data_to_sent,
            success: function(data, textStatus, jqXHR){
                var return_array = JSON.parse(data);
                if(return_array.status_code < 0){
                    sync_wechat_console_writeline(data);
                    jQuery("#dialogText").text("Synchronize Error!");
                    jQuery("#dialog").dialog("open");
                    return;
                }
                var url_list = return_array.data.url_list;
                sync_wechat_console_writeline("get urls : " + url_list.length);
                // issue new requests for each url in result_array
                if(url_list.length == 0)
                    sync_wechat_get_url_list_termination = true;
                else if(sync_wechat_is_debug == 1){ 
                    sync_wechat_url_list = sync_wechat_url_list.concat(url_list);
                    sync_wechat_submit_multiple();  
                    if(!return_array.data.need_update){
                        sync_wechat_get_url_list_termination = true;
                    }                                    
                }                
                else{ // debug mode is on, do not issue submit multiple request
                    sync_wechat_console_writeline(url_list.join("\n"), url_list.length);
                }                
            },
            error: function(jqXHR, textStatus, errorThrown){
                sync_wechat_console_writeline(textStatus + '*' + errorThrown);
                sync_wechat_console_writeline("at global offset : " + (sync_wechat_global_offset - 20));
                if(sync_wechat_is_debug == 2){
                    sync_wechat_console_writeline(jqXHR.responseText);
                }
                else if(sync_wechat_is_debug == 0){
                    jQuery("#dialogText").text("Synchronize Error!");
                    jQuery("#dialog").dialog("open");
                }
            }   
        });        
    }
   jQuery("#Synchronize").on('click', function(e){
       sync_wechat_submit_total_count = 0;
       if(jQuery('select[name="debug"]').val() == 'on')
          sync_wechat_is_debug = 1;
       else if(jQuery('select[name="debug"]').val() == 'detail')
          sync_wechat_is_debug = 2;           
       var sync_wechat_url_list_string = jQuery('textarea[name="given_urls"]').val();
       if(sync_wechat_is_debug > 0)
          sync_wechat_console.attr("style", "display:block");
       if(sync_wechat_url_list_string.length>0){
           sync_wechat_url_list = sync_wechat_url_list_string.split("\n");
           sync_wechat_submit_multiple();
       }
       else{
            // reset global offset
            var offset_to_sent = parseInt(jQuery('input[name="offset"]').val())
            // todo: alert the user that offset cannot be negative.
            sync_wechat_global_offset = offset_to_sent >=0 ? offset_to_sent : 0;           
            sync_wechat_get_news();
       }
    }); 
</script>
</div>
