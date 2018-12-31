<div>
<h1> Wechat synchronization </h1>
<form method="post" action="options.php">
<?php settings_fields('ws-settings-group'); ?>
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
<form id="url">
<table class="form-table">
    <tr>
     <th scope="row"><label for="ws_history">Get preivous articles Url</label></th>
     <td>
        <select name="ws_history">
                <option value="ws_Yes" selected>Yes</option>
                <option value="ws_No">No</option>
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
     </tr>
    <tr>
     <th scope="row"><label for="keep_source">Keep original author info</label></th>
     <td>
        <select name="keep_source">
                <option value="keep" selected>Yes</option>
                <option value="remove">No</option>
        </select>
     </td>
     </tr>
     <tr>
     <textarea type="text" name="given_urls" class="large-text code" rows="3" id="console"></textarea>         
     </tr>
</table>
<?php submit_button("Synchronize"); ?>
</form>
<script>
<?php wp_enqueue_script( 'jquery-form');?>
   jQuery("#url").on('submit', function(e){
       e.preventDefault();
       jQuery(this).ajaxSubmit({
       type: "POST",
       url: "<?php echo plugins_url("/synchronize_api.php", __FILE__);?>",
       success: function(data, textSatus, jqXHR){
           console.log("done");
       }
       })
    }); 
</script>
</div>