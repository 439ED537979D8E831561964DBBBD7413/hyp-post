<?php

trait FSPReports
{
	public function report1_data()
	{
		if( !(isset($_POST['type']) && is_string($_POST['type']) && in_array($_POST['type'] , ['dayly' , 'monthly' , 'yearly'])) )
		{
			exit();
		}

		$type = (string)$_POST['type'];

		$query = [
			'dayly'     =>  "SELECT CAST(send_time AS DATE) AS date , COUNT(0) AS c FROM ".wpTable('feeds')." WHERE is_sended=1 GROUP BY CAST(send_time AS DATE)",
			'monthly'   =>  "SELECT CONCAT(YEAR(send_time), '-', MONTH(send_time) , '-01') AS date , COUNT(0) AS c FROM ".wpTable('feeds')." WHERE is_sended=1 AND send_time > ADDDATE(now(),INTERVAL -1 YEAR) GROUP BY YEAR(send_time), MONTH(send_time)",
			'yearly'    =>  "SELECT CONCAT(YEAR(send_time), '-01-01') AS date , COUNT(0) AS c FROM ".wpTable('feeds')." WHERE is_sended=1 GROUP BY YEAR(send_time)"
		];

		$dateFormat = [
			'dayly'   => 'Y-m-d',
			'monthly' => 'Y M',
			'yearly'  => 'Y',
		];

		$dataSQL = wpDB()->get_results($query[$type] , ARRAY_A);

		$labels = [];
		$datas = [];
		foreach( $dataSQL AS $dInf )
		{
			$datas[] = $dInf['c'];
			$labels[] = date( $dateFormat[$type] , strtotime($dInf['date']) );
		}

		response(true , [
			'data' => $datas,
			'labels' => $labels
		]);
	}

	public function report2_data()
	{
		if( !(isset($_POST['type']) && is_string($_POST['type']) && in_array($_POST['type'] , ['dayly' , 'monthly' , 'yearly'])) )
		{
			exit();
		}

		$type = (string)$_POST['type'];

		$query = [
			'dayly'     =>  "SELECT CAST(send_time AS DATE) AS date , SUM(visit_count) AS c FROM ".wpTable('feeds')." WHERE is_sended=1 GROUP BY CAST(send_time AS DATE)",
			'monthly'   =>  "SELECT CONCAT(YEAR(send_time), '-', MONTH(send_time) , '-01') AS date , SUM(visit_count) AS c FROM ".wpTable('feeds')." WHERE send_time > ADDDATE(now(),INTERVAL -1 YEAR) AND is_sended=1 GROUP BY YEAR(send_time), MONTH(send_time)",
			'yearly'    =>  "SELECT CONCAT(YEAR(send_time), '-01-01') AS date , SUM(visit_count) AS c FROM ".wpTable('feeds')." WHERE is_sended=1 GROUP BY YEAR(send_time)"
		];

		$dateFormat = [
			'dayly'   => 'Y-m-d',
			'monthly' => 'Y M',
			'yearly'  => 'Y',
		];

		$dataSQL = wpDB()->get_results($query[$type] , ARRAY_A);

		$labels = [];
		$datas = [];
		foreach( $dataSQL AS $dInf )
		{
			$datas[] = $dInf['c'];
			$labels[] = date( $dateFormat[$type] , strtotime($dInf['date']) );
		}

		response(true , [
			'data' => $datas,
			'labels' => $labels
		]);
	}

	public function report3_data()
	{
		$page = _post('page' , '0' , 'num');
		$schedule_id = _post('schedule_id' , '0' , 'num');

		if( !($page > 0) )
		{
			response(false);
		}

		$limit = 4;
		$offset = ($page - 1) * $limit;

		$queryAdd = '';
		if( $schedule_id > 0 )
		{
			$queryAdd = ' AND schedule_id="'.(int)$schedule_id.'"';
		}

		$allCount = wpDB()->get_row("SELECT COUNT(0) AS c FROM " . wpTable('feeds') . ' WHERE is_sended=1 ' . $queryAdd , ARRAY_A);

		$getData = wpDB()->get_results("SELECT * FROM " . wpTable('feeds') . ' WHERE is_sended=1 ' . $queryAdd . " ORDER BY id DESC LIMIT $offset , $limit" , ARRAY_A);
		$resultData = [];

		foreach($getData AS $feedInf)
		{
			$postInf = get_post($feedInf['post_id']);

			$nodeInfTable = $feedInf['node_type'] == 'account' ? 'accounts' : 'account_nodes';

			$nodeInf = wpFetch($nodeInfTable , $feedInf['node_id']);
			if( $feedInf['node_type'] == 'account' )
			{
				$nodeInf['node_type'] = 'account';
			}

			$insights = [
				'like'		=>	0,
				'details'	=>	'',
				'comments'	=>	0,
				'shares'	=>	0
			];

			if( !empty($feedInf['driver_post_id']) )
			{
				$nInf = getAccessToken($feedInf['node_type'] , $feedInf['node_id']);

				$proxy          = $nInf['info']['proxy'];
				$accessToken    = $nInf['access_token'];
				if( $feedInf['driver'] == 'fb' )
				{
					require_once LIB_DIR . "fb/FacebookLib.php";
					$insights = FacebookLib::getStats($feedInf['driver_post_id'] , $accessToken , $proxy);
				}
				else if( $feedInf['driver'] == 'vk' )
				{
					require_once LIB_DIR . "vk/Vk.php";
					$insights = Vk::getStats($feedInf['driver_post_id'] , $accessToken , $proxy);
				}
				else if( $feedInf['driver'] == 'twitter' )
				{
					require_once LIB_DIR . "twitter/TwitterLib.php";
					$insights = TwitterLib::getStats($feedInf['driver_post_id'] , $accessToken , $nInf['access_token_secret'] , $nInf['app_id'] , $proxy);
				}
				else if( $feedInf['driver'] == 'instagram' )
				{
					require_once LIB_DIR . "instagram/Instagram.php";
					$insights = Instagram::getStats($feedInf['driver_post_id2'] , $nInf['info'] , $proxy);
				}
				else if( $feedInf['driver'] == 'linkedin' )
				{
					require_once LIB_DIR . "linkedin/Linkedin.php";
					$insights = Linkedin::getStats(null , $proxy);
				}
				else if( $feedInf['driver'] == 'pinterest' )
				{
					require_once LIB_DIR . "pinterest/Pinterest.php";
					$insights = Pinterest::getStats($feedInf['driver_post_id'] , $accessToken , $proxy);
				}
				else if( $feedInf['driver'] == 'reddit' )
				{
					require_once LIB_DIR . "reddit/Reddit.php";
					$insights = Reddit::getStats($feedInf['driver_post_id'] , $accessToken , $proxy);
				}
			}

			$resultData[] = [
				'id'            =>  $feedInf['id'],
				'name'          =>  htmlspecialchars($nodeInf['name']),
				'post_id'       =>  htmlspecialchars($feedInf['driver_post_id']),
				'post_title'    =>  htmlspecialchars($postInf->post_title),
				'cover'         =>  profilePic($nodeInf),
				'profile_link'  =>  profileLink($nodeInf),
				'is_sended'     =>  $feedInf['is_sended'],
				'post_link'     =>  postLink($feedInf['driver_post_id'] , $feedInf['driver'] , isset($nodeInf['screen_name']) ? $nodeInf['screen_name'] : $nodeInf['username']),
				'status'        =>  $feedInf['status'],
				'error_msg'     =>  $feedInf['error_msg'],
				'hits'          =>  $feedInf['visit_count'],
				'driver'        =>  $feedInf['driver'],
				'insights'      =>  $insights,
				'node_type'     =>  ucfirst($feedInf['node_type']),
				'feed_type'     =>  ucfirst((string)$feedInf['feed_type']),
				'date'          =>  date('Y-m-d H:i' , strtotime($feedInf['send_time']))
			];
		}

		$nextBtnDisable = ($page * $limit >= $allCount['c']);

		response(true , ['data' => $resultData , 'disable_btn' => $nextBtnDisable]);

	}
}