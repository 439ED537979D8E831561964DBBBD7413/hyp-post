<?php

class SocialNetworkPost
{

	public static function post($feedId)
	{
		$feedInf    = wpFetch("feeds" , $feedId);

		$postId                 = $feedInf['post_id'];
		$custom_post_message    = $feedInf['custom_post_message'];

		$nInf = getAccessToken($feedInf['node_type'] , $feedInf['node_id']);

		$nodeProfileId       = $nInf['node_id'];
		$appId               = $nInf['app_id'];
		$driver              = $nInf['driver'];
		$accessToken         = $nInf['access_token'];
		$accessTokenSecret   = $nInf['access_token_secret'];
		$proxy               = $nInf['info']['proxy'];


		$link           = '';
		$message        = '';
		$sendType       = 'status';
		$images         = null;
		$imagesLocale   = null;
		$videoURL       = null;
		$postTitle      = '';
		$videoURLLocale = null;

		$postInf    = get_post($postId , ARRAY_A);
		$postType   = $postInf['post_type'];

		if( $driver == 'reddit' )
		{
			$postTitle = $postInf['post_title'];
		}

		if( $postType == 'attachment' && strpos($postInf['post_mime_type'] , 'image') !== false )
		{
			$sendType = 'image';
			$images[] = $postInf['guid'];
			$imagesLocale[] = get_attached_file( $postId );

		}
		else if( $postType == 'attachment' && strpos($postInf['post_mime_type'] , 'video') !== false )
		{
			$sendType = 'video';
			$videoURL = $postInf['guid'];
			$videoURLLocale = get_attached_file( $postId );
		}
		else
		{
			$sendType = 'link';
		}

		$shortLink = '';
		$longLink = '';
		if( $postType == 'fs_post' )
		{
			$message = $postInf['post_content'];

			$link1 = get_post_meta( $postId, '_fs_link', true );
			$longLink = $link1;

			$mediaId = get_post_thumbnail_id( $postId );
			if( $mediaId > 0 )
			{
				$sendType = 'image';
				$url1 = wp_get_attachment_url($mediaId);
				$url2 = get_attached_file($mediaId);

				$images = [$url1];
				$imagesLocale = [$url2];
			}

			if( !empty($link1) )
			{
				$link = $link1;
				$shortLink = $link1;
			}
		}
		else
		{
			$link = get_permalink($postInf['ID']);

			$link = customizePostLink($link , $feedId);

			if( get_option('unique_link', '1') == 1 && !empty($link) )
			{
				$link .= ( strpos($link , '?') === false ? '?' : '&' ) . '_unique_id=' . uniqid();
			}

			if( empty( $custom_post_message ) )
			{
				$custom_post_message = get_option( 'post_text_message_' . $driver , "{title}" );
			}

			$longLink = $link;
			$shortLink = shortenerURL( $link );

			$message = replaceTags( $custom_post_message , $postInf , $link , $shortLink);

			$link = $shortLink;
		}

		if( $driver == 'fb' )
		{
			require_once LIB_DIR . "fb/FacebookLib.php";
			$res = FacebookLib::sendPost($nodeProfileId , $sendType , $message , 0 , $link , $images , $videoURL , $accessToken , $proxy);
		}
		else if( $driver == 'instagram' )
		{
			require_once LIB_DIR . "instagram/Instagram.php";

			if( $sendType != 'image' && $sendType != 'video' )
			{
				$mediaId = get_post_thumbnail_id($postId);

				if( empty($mediaId) )
				{
					$media = get_attached_media( 'image' , $postId);
					$first = reset($media);
					$mediaId = isset($first->ID) ? $first->ID : 0;
				}

				$url = $mediaId > 0 ? get_attached_file($mediaId) : '';

				if( !empty($url) )
				{
					$sendType = 'image';
					$imagesLocale = [$url];
				}
			}

			if( $feedInf['feed_type'] == 'story' )
			{
				$res = Instagram::sendStory($nInf['info'] , $sendType , $message , $link , $imagesLocale , $videoURLLocale);
			}
			else
			{
				$res = Instagram::sendPost($nInf['info'] , $sendType , $message , $link , $imagesLocale , $videoURLLocale);
			}
		}
		else if( $driver == 'linkedin' )
		{
			require_once LIB_DIR . "linkedin/Linkedin.php";
			$res = Linkedin::sendPost($nInf['info'] , $sendType , $message , $postInf['post_title'] , $link , $images , $videoURL , $accessToken , $proxy);
		}
		else if( $driver == 'vk' )
		{
			if( get_option('fs_vk_upload_image', '1') == 1 && $sendType != 'image' && $sendType != 'video' )
			{
				$mediaId = get_post_thumbnail_id($postId);

				if( empty($mediaId) )
				{
					$media = get_attached_media( 'image' , $postId);
					$first = reset($media);
					$mediaId = isset($first->ID) ? $first->ID : 0;
				}

				$url = $mediaId > 0 ? get_attached_file($mediaId) : '';

				if( !empty($url) )
				{
					$sendType = 'image_link';
					$imagesLocale = [$url];
				}
			}

			require_once LIB_DIR . "vk/Vk.php";
			$res = Vk::sendPost($nodeProfileId , $sendType , $message , $link , $imagesLocale , $videoURLLocale , $accessToken , $proxy);
		}
		else if( $driver == 'pinterest' )
		{
			if( $sendType != 'image' && $sendType != 'video' )
			{
				$mediaId = get_post_thumbnail_id($postId);

				if( empty($mediaId) )
				{
					$media = get_attached_media( 'image' , $postId);
					$first = reset($media);
					$mediaId = isset($first->ID) ? $first->ID : 0;
				}

				$url = $mediaId > 0 ? wp_get_attachment_url($mediaId) : '';

				if( !empty($url) )
				{
					$sendType = 'image';
					$images = [$url];
				}
			}

			require_once LIB_DIR . "pinterest/Pinterest.php";
			$res = Pinterest::sendPost($nInf['info'] , $sendType , $message , $longLink , $images , $videoURL , $accessToken , $proxy);
		}
		else if( $driver == 'reddit' )
		{
			require_once LIB_DIR . "reddit/Reddit.php";
			$res = Reddit::sendPost($nInf['info'] , $sendType , $postTitle , $message , $longLink , $images , $videoURL , $accessToken , $proxy);
		}
		else if( $driver == 'tumblr' )
		{
			if( $sendType != 'image' && $sendType != 'video' )
			{
				$mediaId = get_post_thumbnail_id($postId);

				if( empty($mediaId) )
				{
					$media = get_attached_media( 'image' , $postId);
					$first = reset($media);
					$mediaId = isset($first->ID) ? $first->ID : 0;
				}

				$url = $mediaId > 0 ? wp_get_attachment_url($mediaId) : '';

				if( !empty($url) )
				{
					$sendType = 'image';
					$images = [$url];
				}

				// for <img> tag... link post type
				$imagesLocale = $images;
			}

			require_once LIB_DIR . "tumblr/Tumblr.php";
			$res = Tumblr::sendPost($nInf['info'] , $sendType , $postTitle , $message , $link , $imagesLocale , $videoURLLocale , $accessToken , $accessTokenSecret , $appId , $proxy);
		}
		else if( $driver == 'twitter' )
		{
			if( $sendType != 'image' && $sendType != 'video' )
			{
				$pMethod = get_option('fs_twitter_posting_type', '1');
				if( $pMethod == '2' )
				{
					$mediaId = get_post_thumbnail_id($postId);

					if( empty($mediaId) )
					{
						$media = get_attached_media( 'image' , $postId);
						$first = reset($media);
						$mediaId = isset($first->ID) ? $first->ID : 0;
					}

					$url = $mediaId > 0 ? get_attached_file($mediaId) : '';

					if( !empty($url) )
					{
						$imagesLocale = [$url];
						$sendType = 'image';
					}
				}
				else if( $pMethod == '3' )
				{
					$imagesLocale = [];

					$mediaId = get_post_thumbnail_id($postId);
					if( $mediaId > 0 )
					{
						$imagesLocale[] = get_attached_file( $mediaId );
					}

					$allImgaes = get_attached_media( 'image' , $postId);
					foreach( $allImgaes AS $mediaInf )
					{
						$mediaId2 = isset($mediaInf->ID) ? $mediaInf->ID : 0;
						if( $mediaId2 > 0 )
						{
							$imagesLocale[] = get_attached_file($mediaId2);
						}
					}

					if( !empty($imagesLocale) )
					{
						$sendType = 'image';
					}
				}
			}

			if( get_option('fs_twitter_auto_cut_tweets', '1') == 1 )
			{
				$limit = 280 - mb_strlen("\n" . $link , 'UTF-8');
				if( $limit < mb_strlen($message , 'UTF-8') )
				{
					$limit -= 3;
					$message = mb_substr($message , 0, $limit, 'UTF-8') . '...';
				}
			}

			require_once LIB_DIR . "twitter/TwitterLib.php";
			$res = TwitterLib::sendPost($appId , $sendType , $message , $link , $imagesLocale , $videoURLLocale , $accessToken , $accessTokenSecret , $proxy);
		}
		else if( $driver == 'google' )
		{
			require_once LIB_DIR . "google/GooglePlus.php";

			$google = new GooglePlus($nInf['username'] , $nInf['password'] , $nInf['proxy']);

			$shareTo = $feedInf['node_type'] == 'account' ? 'profile' : [$nInf['info']['node_id'] , $nInf['info']['access_token']];

			$res = $google->sendPost( $shareTo , $message , $link);
		}
		else
		{
			$res = ['status' => 'error' , 'error_msg' => 'Driver error! Driver type: ' . htmlspecialchars($driver)];
		}

		$udpateDate = [
			'is_sended'         => 1,
			'send_time'         => current_time('Y-m-d H:i:s'),
			'status'            => $res['status'],
			'error_msg'         => isset($res['error_msg']) ? $res['error_msg'] : '',
			'driver_post_id'    => isset($res['id']) ? $res['id'] : null,
			'driver_post_id2'   => isset($res['id2']) ? $res['id2'] : null
		];

		wpDB()->update(wpTable('feeds') , $udpateDate , ['id' => $feedId]);

		if( isset($res['id']) )
		{
			$username = isset($nInf['info']['screen_name']) ? $nInf['info']['screen_name'] : $nInf['username'];
			$res['post_link'] = postLink($res['id'] , $driver , $username);
		}

		return $res;
	}

}