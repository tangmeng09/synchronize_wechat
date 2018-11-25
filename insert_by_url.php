<?php
if(!class_exists('simple_html_dom_node')){
	require_once("php-simple-html-dom/simple_html_dom.php");
}
//! \brief: check the domain name
//! input: $url
//! output: trimed $url if $url contains the domain name of wx; otherwise, empty string is returned
function check_wx_url($url){
    if (strpos($url, 'http://mp.weixin.qq.com/s') !== false || strpos($url, 'https://mp.weixin.qq.com/s') !== false) {
        $url = str_replace('http://', 'https://', $url);
	    return trim($url);
    }
    else
        return '';
}
//! \brief: get the html from url
//! input: $url
//! output: $html raw text, if any error occurs, return empty string
function get_html($url){
    if (function_exists('file_get_contents')) {
	    $html = @file_get_contents($url);
    } 
    if ($html == '') { //fallback to use curl module for https request
	    $ch = curl_init();
	    $timeout = 30;
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    $html = curl_exec($ch);
	    curl_close($ch);
    }
    return $html;
}
/**
* intro: this function insert wechat article to the wp-database
* this function relies on global variable $wpdb and the php module $curl
* input:
* $config = {
* 		'changeAuthor'[bool,default:false]:whether to keep the original author
*		'changePostTime'[bool,default:false]: whether to keep the original post time
*		'postStatus'[choice,default:draft]: article status
*       'postType'[choice,default:post]: article type
*		'keepStyle'[bool,default:false]: whether the css of the article is kept
*		'postCate'[choice,default:not_classcified]: the classification to put the article
*        'downloadImage'[bool,default:false]: whether download image and save a local copy
*    }
* returns: 
* status = {
*     'post_id'[int]: if post_id = 0, error occurs
*     'err_msg'[str]: if no error, empty str      
*   }
*/

function ws_insert_by_url($url, $config){
	    $url =  check_wx_url($url);
		if (!$url) {
			return array('post_id' => 0, 'err_msg' => 'url does not contain mp.weixin.qq.com');
		}
        !$html = get_html($url);
		if (!$html) {
            return array('post_id' => 0, 'err_msg' => 'cannot get any message from '. $url);
		}
		// �Ƿ��Ƴ�ԭ����ʽ
        $keepStyle = isset($config['keep_style']) && $config['keep_style'] == true;
		if (!$keepStyle) {
			$html = preg_replace('/style\=\"[^\"]*\"/', '', $html);
		}
		// ���±���
		preg_match('/(msg_title = ")([^\"]+)"/', $html, $matches);
		$title = trim($matches[2]);
        // ȷ���б���
		if (!$title) {
			return array('post_id' => 0, 'err_msg' => 'cannot get title from '. $url);
		}
		// ͬ������������Ƿ��ظ������ظ�������
        $post_id = post_exists($title);
		if ($post_id != 0) {
			return array('post_id' => $post_id, 'err_msg' => 'the article is already in the database');
		}

		// ��������
		if ($changePostTime) {
			$postDate = date('Y-m-d H:i:s', current_time('timestamp'));
		} else {
			preg_match('/(publish_time = ")([^\"]+)"/', $html, $matches);
			$postDate = isset($matches[2]) ? $matches[2] : current_time('timestamp');
			$postDate = date('Y-m-d H:i:s', strtotime($postDate));
		}
		// ��ȡ�û���Ϣ
		$url      = parse_url($url);
		$query    = isset($url['query']) ? $url['query'] : '';
		$queryArr = explode('&', $query);
		$bizVal   = '';
		$cates = array();
		foreach ($queryArr as $item) {
			if (!$item) {
				continue;
			}
			list($key, $val) = explode('=', $item, 3);
			if ($key == '__biz') {
				//  �û�Ψһ��ʶ
				$bizVal = $val;
			}
			if ($key == 'cates') {
				$cates = explode(',', $val);
			}
		}
		// ��������в�����biz��������ѡ��ǰ��ʱ�����Ϊ�û���������
		if ($bizVal == '') {
			$bizVal = time();
		}

		// �Ƿ�ı����ߣ�Ĭ���ǵ�ǰ��¼����
		// $userName = $dom->find('#post-user', 0)->plaintext;
		// $userName = esc_html($userName);
		if ($changeAuthor) {
			// �����û�
			$userId   = wp_create_user($bizVal, $bizVal);
			// �û��Ѵ���
			if ($userId) {
				if ($userId->get_error_code() == 'existing_user_login') {
					$userData = get_user_by('login', $bizVal);
				} else if(is_integer($userId) > 0) {
					$userData = get_userdata($userId);
				} else {
					// �������
					continue;
				}
				// Ĭ����Ͷ����
				$userData->add_role('contributor');
				$userData->remove_role('subscriber');
				$userData->display_name = $userName;
				$userData->nickname     = $userName;
				$userData->first_name   = $userName;
				wp_update_user($userData);
				$userId = $userData->ID;
			} else {
				// Ĭ�ϲ�������
				$userId = get_current_user_id();
			}
		} else {
			// Ĭ�ϲ�������
			$userId = get_current_user_id();
		}
			
		if ($cates) {
			$cateIds = array();
			foreach ($cates as $cate) {
				$term = get_term_by('name', $cate, 'category');
				if ($term) {
					$cateIds[] = $term->term_id;
				} else {
				}
			}
			$postCate = $cateIds;
		}



		$post = array(
			'post_title'    => $title,
			'post_content'  => "",
			'post_status'   => $postStatus,
			'post_date'     => $postDate,
			'post_modified' => $postDate,
			'post_author'   => $userId,
			'post_category' => $postCate,
			'post_type'	    => $postType
		);
        $postId         = null;
		$postId = @wp_insert_post($post);
        file_put_contents($file, "add new post id to db:" . $postId . "\n", FILE_APPEND);
		// ���ں�����featured image
		$setFeaturedImage  = get_option('bp_featured_image', 'yes') == 'yes';
		if ($setFeaturedImage) {
			preg_match('/(msg_cdn_url = ")([^\"]+)"/', $html, $matches);
			$redirectUrl = 'http://read.html5.qq.com/image?src=forum&q=4&r=0&imgflag=7&imageUrl=';
			$coverImageSrc = $redirectUrl . $matches[2];
			$tmpFile = download_url($coverImageSrc);
			if (is_string($tmpFile)) {
				$prefixName = get_option('ws_image_name_prefix', 'ws-plugin');
				$fileName = $prefixName . '-' . time() . '.jpeg';
				$fileArr  = array(
					'name'     => $fileName,
					'tmp_name' => $tmpFile
				);
				$id = @media_handle_sideload($fileArr, $postId);
                file_put_contents($file, "add new feature image id to db:" . $id . "\n", FILE_APPEND);
				if (!is_wp_error($id)) {
					@set_post_thumbnail($postId, $id);
				}
			}
		}
		unset($html);
		// ����ͼƬ����Ƶ��Դ
        $dom  = str_get_html($html);
		$imageDoms = $dom->find('img');
		$videoDoms = $dom->find('.video_iframe');
        $sprindboard = 'http://read.html5.qq.com/image?src=forum&q=4&r=0&imgflag=7&imageUrl=';
		foreach ($imageDoms as $imageDom) {
			$dataSrc = $imageDom->getAttribute('data-src');
			if (!$dataSrc) {
				continue;
			}
			$src  = $sprindboard . $dataSrc;
			$imageDom->setAttribute('src', $src);
		}
		foreach ($videoDoms as $videoDom) {
			$dataSrc = $videoDom->getAttribute('data-src');
			// ��Ƶ��������
			$videoDom->setAttribute('src', $dataSrc);
		}
		// ����ͼƬ������
		ws_downloadImage($postId, $dom);
}
function ws_insert_by_urls($urls) {
    if ( is_admin() ) {
        require_once(ABSPATH . 'wp-admin/includes/admin.php');
    }
	global $wpdb;

	// ΢��ԭ����
	$changeAuthor   = false;
	// �ı䷢��ʱ��
	$changePostTime = isset($_REQUEST['change_post_time']) && $_REQUEST['change_post_time'] == 'true';
	// Ĭ����ֱ�ӷ���
	$postStatus     = isset($_REQUEST['post_status']) && in_array($_REQUEST['post_status'], array('publish', 'pending', 'draft')) ?
						$_REQUEST['post_status'] : 'publish';
	// ����������ʽ
	$keepStyle      = isset($_REQUEST['keep_style']) && $_REQUEST['keep_style'] == 'keep';
	// ���·��࣬Ĭ����δ���ࣨ1��
	$postCate       = isset($_REQUEST['post_cate']) ? intval($_REQUEST['post_cate']) : 1;
	$postCate       = array($postCate);
	// �������ͣ�Ĭ����post
	$postType       = isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : 'post';

	
	$urls           = str_replace('https', 'http', $urls);
    global $file;
    $config = array(
		'changeAuthor'    => $changeAuthor,
		'changePostTime'  => $changePostTime,
		'postStatus'   => $postStatus,
        'postType' => $postType,
		'keepStyle'     => $keepStyle,
		'postCate' => $postCate,
        'downloadImage' => true
	);
	foreach ($urls as $url) {
        ws_insert_by_url($url, $config);
	}
	$GLOBALS['done'] = true;
	return $postId;
}
function ws_downloadImage($postId, $dom) {
	// ��ȡͼƬ
	$images            = $dom->find('img');
	$version           = '2-4-2';
	// ���±���
	$title             = $_REQUEST['post_title'];
	$centeredImage     = get_option('bp_image_centered', 'no') == 'yes';
    global $file;
	foreach ($images as $image) {
		$src  = $image->getAttribute('src');
		$type = $image->getAttribute('data-type');
		if (!$src) {
			continue;
		}
		if (strstr($src, 'res.wx.qq.com')) {
			continue;
		}
		$class = $image->getAttribute('class');
		if ($centeredImage) {
			$class .= ' aligncenter';
			$image->setAttribute('class', $class);
		}
		$src = preg_replace('/^\/\//', 'http://', $src, 1);
		if (!$type || $type == 'other') {
			$type = 'jpeg';
		}
        file_put_contents($file, 'pic src: ' . $src . "\n", FILE_APPEND);
        $pic_array = explode("/",$src);
        $pic_name =  $pic_array[count($pic_array)-2];		
		$fileName = 'ws-plugin-' . $pic_name . '.' . $type;		
        $id = post_exists($fileName) ;
        if($id==0){
    		$tmpFile = download_url($src);

		    $fileArr = array(
			    'name' => $fileName,
			    'tmp_name' => $tmpFile
		    );
    		$id = @media_handle_sideload($fileArr, $postId);
            file_put_contents($file, "add new media to db, with id=" . $id . "\n", FILE_APPEND);
        }
		if (is_wp_error($id)) {
			$GLOBALS['errMsg'][] = array(
				'src'  => $src,
				'file' => $fileArr,
				'msg'  => $id
			);
			@unlink($tmpFile);
			continue;
		} else {
			$imageInfo = wp_get_attachment_image_src($id, 'full');
			$src       = $imageInfo[0];
			$image->setAttribute('src', $src);
			$image->setAttribute('alt', $title);
			$image->setAttribute('title', $title);
		}
	}
	$userName = $dom->find('#profileBt a', 0);
     if($userName){
      $userName = $userName->plaintext;
     }
     else{ // handle ת��
         $userName = $dom->find('.original_account_nickname', 0)->plaintext;
     }
	$userName = esc_html($userName);
    file_put_contents($file, "article user name:" . $userName . "\n", FILE_APPEND);
	// ������Դ
	$keepSource     = isset($_REQUEST['keep_source']) && $_REQUEST['keep_source'] == 'keep';
	$content = $dom->find('#js_content', 0)->innertext;
	$content = preg_replace('/data\-([a-zA-Z0-9\-])+\=\"[^\"]*\"/', '', $content);
	$content = preg_replace('/src=\"(http:\/\/read\.html5\.qq\.com)([^\"])*\"/', '', $content);
	$content = preg_replace('/class=\"([^\"])*\"/', '', $content);
	$content = preg_replace('/id=\"([^\"])*\"/', '', $content);
	if ($keepSource) {
		$source =
				"<blockquote class='keep-source'>" .
				"<p>ʼ����΢�Ź��ںţ�{$userName}</p>" .
				"</blockquote>";
		$content .= $source;
	}
	// ����������ʽ
	$content = trim($content);
    file_put_contents($file, $content . "\n", FILE_APPEND);
	$return_postID = wp_update_post(array(
		'ID' => $postId,
		'post_content' =>  $content
	));
    if(is_wp_error($return_postID)){
        file_put_contents($file, 'Error message' . $return_postID->get_error_message() . "\n", FILE_APPEND);
    }
    file_put_contents($file, 'update POST ID' .$postId . "\n", FILE_APPEND);
    
}
?>