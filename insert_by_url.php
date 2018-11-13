<?php
function ws_insert_by_url($urls) {
	global $wpdb;
	//�������ͼƬ��ַ�����ع���
	$schedule       = isset($_REQUEST['schedule']) && intval($_REQUEST['schedule']) == 1;
	$sprindboard    = isset($_REQUEST['springboard']) ?
						$_REQUEST['springboard'] :
						'http://read.html5.qq.com/image?src=forum&q=4&r=0&imgflag=7&imageUrl=';
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
    //	$debug          = isset($_REQUEST['debug']) ? $_REQUEST['debug'] : false;
	$force          = isset($_REQUEST['force']) ? $_REQUEST['force'] : true;

	$postId         = null;
	$urls           = str_replace('https', 'http', $urls);

	foreach ($urls as $url) {
		if (strpos($url, 'http://mp.weixin.qq.com/s') !== false || strpos($url, 'https://mp.weixin.qq.com/s') !== false) {
			$url =  trim($url);
		}
		if (!$url) {
			continue;
		}
		if (function_exists('file_get_contents')) {
			$html = @file_get_contents($url);
		} else {
			$GLOBALS['errMsg'][] = '��֧��file_get_contents';
			break;
		}
		if ($html == '') {
            $url = str_replace('http://', 'https://', $url);
			$ch = curl_init();
			$timeout = 30;
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$html = curl_exec($ch);
			curl_close($ch);
		}
		if (!$html) {
			$GLOBALS['errMsg'][] = array(
				'url' => $url,
				'msg' => '�޷���ȡ����URL����'
			);
			continue;
		}
		// �Ƿ��Ƴ�ԭ����ʽ
		if (!$keepStyle) {
			$html = preg_replace('/style\=\"[^\"]*\"/', '', $html);
		}
		$dom  = str_get_html($html);
		// ���±���
        $file = plugin_dir_path(__FILE__) . 'log.txt';
        file_put_contents($file, $html . "\n", FILE_APPEND);
		preg_match('/(msg_title = ")([^\"]+)"/', $html, $matches);
		$_REQUEST['post_title'] = trim($matches[2]);
		$title = $_REQUEST['post_title'];
		// ȷ���б���
		if (!$title) {
			$GLOBALS['errMsg'][] = array(
				'url' => $url,
				'msg' => '����URLû�����±���'
			);
			continue;
		}
		// ͬ������������Ƿ��ظ������ظ�������
		if ($id = post_exists($title)) {
			$GLOBALS['errMsg'][] = array(
				'url' => $url,
				'msg' => '�����ظ�'
			);
			continue;
		}
		// ����ͼƬ����Ƶ��Դ
		$imageDoms = $dom->find('img');
		$videoDoms = $dom->find('.video_iframe');
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

		if ($schedule) {
			$userId = 1;
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
		$postId = @wp_insert_post($post);
		// ���ں�����featured image
		$setFeaturedImage  = get_option('bp_featured_image', 'yes') == 'yes';
		if ($setFeaturedImage) {
			preg_match('/(msg_cdn_url = ")([^\"]+)"/', $html, $matches);
			$redirectUrl = 'http://read.html5.qq.com/image?src=forum&q=4&r=0&imgflag=7&imageUrl=';
			$coverImageSrc = $redirectUrl . $matches[2];
			$tmpFile = download_url($coverImageSrc);
			if (is_string($tmpFile)) {
				$prefixName = get_option('bp_image_name_prefix', 'beepress-weixin-zhihu-jianshu-plugin');
				$fileName = $prefixName . '-' . time() . '.jpeg';
				$fileArr  = array(
					'name'     => $fileName,
					'tmp_name' => $tmpFile
				);
				$id = @media_handle_sideload($fileArr, $postId);
				if (!is_wp_error($id)) {
					@set_post_thumbnail($postId, $id);
				}
			}
		}
		unset($html);
		// ����ͼƬ������
		ws_downloadImage($postId, $dom);
	}
	$GLOBALS['done'] = true;
	return $postId;
}
function ws_downloadImage($postId, $dom) {
	// ��ȡͼƬ
	$images            = $dom->find('img');
	$schedule          = isset($_REQUEST['schedule']) && intval($_REQUEST['schedule']) == 1;
	$version           = '2-4-2';
	// ���±���
	$title             = $_REQUEST['post_title'];
	$centeredImage     = get_option('bp_image_centered', 'no') == 'yes';
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
		$tmpFile = download_url($src);
		if ($schedule) {
			$fileName = 'ws-schedule-' . $version . '-' . $postId . '-' . time() .'.' . $type;
		} else {
			$fileName = 'ws-plugin-' . $version . '-' . $postId . '-' . time() .'.' . $type;
		}
		$fileArr = array(
			'name' => $fileName,
			'tmp_name' => $tmpFile
		);

		$id = @media_handle_sideload($fileArr, $postId);
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
         $userName = $dom->find('#original_account_nickname', 0)->plaintext;
     }
	$userName = esc_html($userName);
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
	$content = '<div class="bpp-post-content">'.$content.'</div>';
	// ����������ʽ
	$content = trim($content);
	@wp_update_post(array(
		'ID' => $postId,
		'post_content' =>  $content
	));
}
?>