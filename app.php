<?php
/*** 
TeamToy extenstion info block
##name iOS推送支持
##folder_name ios_push
##author 李博
##email lb13810398408@gmail.com
##reversion 1.0.1
##desp 使iOS客户端可以从本站获得推送功能。
##update_url http://tt2net.sinaapp.com/?c=plugin&a=update_package&name=stoken 
##reverison_url http://tt2net.sinaapp.com/?c=plugin&a=latest_reversion&name=stoken 
***/

// 检查并创建数据库
define('IOSPUSH_PLUGIN_VERSION', '1.0.1');
define('IOSPUSH_PLUGIN_BUILD', '20130219');
define('IOSPUSH_DEVICE_TABLE', 'iospush_userdevice');
define('IOSPUSH_MESSAGE_TABLE', 'iospush_message');

define('IOSPUSH_API', 'https://iospushservice.sinaapp.com/index.php');

if( !mysql_query("SHOW COLUMNS FROM `".IOSPUSH_DEVICE_TABLE."`",db()) )
{
	// table not exists
	// create it
	run_sql("CREATE TABLE `".IOSPUSH_DEVICE_TABLE."` 
	(
		`uid` INT NOT NULL ,
        `device_id` VARCHAR( 32 ) NOT NULL ,
        `push_token` VARCHAR( 71 ) NOT NULL ,
        `badge` INT( 10 ) NOT NULL DEFAULT 0,
		PRIMARY KEY (  `uid` )
	) 	ENGINE = MYISAM ");
}


if( !mysql_query("SHOW COLUMNS FROM `".IOSPUSH_MESSAGE_TABLE."`",db()) )
{
	// table not exists
	// create it
	run_sql("CREATE TABLE `".IOSPUSH_MESSAGE_TABLE."` 
	(
        `id` INT( 10 ) unsigned NOT NULL AUTO_INCREMENT ,
        `body` TEXT NOT NULL ,
        `dateline` INT(10) unsigned NOT NULL DEFAULT 0 ,
        `sent` TINYINT(1) NOT NULL DEFAULT 0 ,
		PRIMARY KEY (  `id` )
	) 	ENGINE = MYISAM ");
}

// 添加API hook，完成业务逻辑
add_action('API_IOS_DEVICE_ADD', 'ios_device_add');
function ios_device_add()
{
    $device_id = z(t(v('device_id')));
    $push_token = z(t(v('push_token')));
    $uid = $_SESSION['uid'];

    if( (strlen($device_id) > 0) && (strlen($push_token) > 0) )
    {
        $sql = "DELETE FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `device_id` = '" . $device_id . "'";
        run_sql( $sql );

        $sql = "INSERT INTO `".IOSPUSH_DEVICE_TABLE."` ( `uid` , `device_id` , `push_token` ) VALUES ( '" . $uid . "' , '" . $device_id . "' , '" . $push_token . "' ) ";
        run_sql( $sql );

        if( db_errno() != 0 ) {
            apiController::send_error( LR_API_DB_ERROR , "DATABASE ERROR" . db_error() );
        } else {
            $data['device_id'] = $device_id;
            $data['push_token'] = $push_token;
            $data['uid'] = $uid;

            $post = array();
            $post['m'] = 'api';
            $post['a'] = 'user_add';
            $post['uid'] = $uid;
            $post['teamtoy'] = $_SERVER['HTTP_HOST'];
            $post['push_token'] = $push_token;

            if (!empty($post['teamtoy'])) {

                @post_data(IOSPUSH_API, $post);
                return apiController::send_result( $data );

            } else {
                apiController::send_error( LR_API_ARGS_ERROR , "FILED REQUIRED" );
            }
        }
    } else {
        apiController::send_error( LR_API_ARGS_ERROR , "FILED REQUIRED" );
    }
}

// 添加API hook，完成业务逻辑
add_action('API_IOS_DEVICE_REMOVE', 'ios_device_remove');
function ios_device_remove()
{
    $device_id = z(t(v('device_id')));
    $push_token = z(t(v('push_token')));
    $uid = $_SESSION['uid'];

    if( (strlen($device_id) > 0) && (strlen($push_token) > 0) )
    {
        $sql = "DELETE FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `uid` = '" . intval( $uid ) . "' AND `device_id` = '" . $device_id . "' LIMIT 1";

        run_sql( $sql );

        if( db_errno() != 0 ) {
            apiController::send_error( LR_API_DB_ERROR , "DATABASE ERROR" . db_error() );
        } else {
            $data['device_id'] = $device_id;
            $data['push_token'] = $push_token;
            $data['uid'] = $uid;

            $post = array();
            $post['m'] = 'api';
            $post['a'] = 'user_remove';
            $post['push_token'] = $push_token;
            $post['uid'] = $uid;
            $post['teamtoy'] = $_SERVER['HTTP_HOST'];

            @post_data(IOSPUSH_API, $post);

            return apiController::send_result( $data );
        }
    } else {
        apiController::send_error( LR_API_ARGS_ERROR , "FILED REQUIRED" );
    }
}

add_filter( 'API_'.g('a').'_OUTPUT_FILTER' , 'push' );
function push( $data )
{
    $a = g('a');
    $a = strtoupper($a);
    switch ($a) {
        case 'IM_SEND':
            $uid = intval(v('uid'));
            $message = z(t(v('text')));
            if ($uid > 0 && !empty($message)) {
                $lid = last_id();
                $message = $_SESSION['uname'] . '：' . $message;
                push_im($uid, $message, $lid);
            }
            break;
        case 'TODO_ASSIGN':
            $uid = intval(v('uid'));
            if ($uid > 0 && $uid != $_SESSION['uid']) {
                $message = $_SESSION['uname'] . '转让了一条Todo给您！';
                push_todo_assign($uid, $message, $data['tid']);
            }
            break;
        case 'TODO_ADD_COMMENT':
            $tid = $data['tid'];
            $tinfo = get_line("SELECT * FROM `todo` WHERE `id` = '" . intval( $tid ) . "' LIMIT 1");

            $content = z(t(v('text')));

            // 向订阅todo的同学发送通知
			$sql = "SELECT `uid` FROM `todo_user` WHERE `tid`= '" . intval($tid) . "' AND `is_follow` = 1 ";
			
			$follow_uids = array();
			if( $uitems = get_data( $sql ) )
			foreach( $uitems as $uitem )
			{
				if( $uitem['uid'] != $_SESSION['uid'] )
				{
					if( !in_array( $uitem['uid'] , $follow_uids ) )
                    {
                        $message = $_SESSION['uname'] . '评论了你关注的TODO！';
                        push_todo_add_comment($uitem['uid'], $message, $data['tid']);
					}
				}
			}
			
			// 向todo作者发通知
			if( $tinfo['owner_uid'] != $_SESSION['uid'] )
			{
                if( !in_array( $tinfo['owner_uid'] , $follow_uids ) ) {
                    $message = $_SESSION['uname'] . '评论了你的TODO！';
                    push_todo_add_comment($tinfo['owner_uid'], $message, $data['tid']);
                }
			}
			
			// 向被@的同学，发送通知
			if( $ats = find_at($content) )
			{
				$sql = "SELECT `id` FROM `user` WHERE ";
				foreach( $ats as $at )
				{
					$at =z(t($at));
					if( mb_strlen($at, 'UTF-8') < 2 ) continue;

					$wsql[] = " `name` = '" . s(t($at)) . "' ";
					if( c('at_short_name') )
						if( mb_strlen($at, 'UTF-8') == 2 )
							$wsql[] = " `name` LIKE '_" . s($at) . "' ";
				}
				
				if( isset( $wsql ) && is_array( $wsql ) )
				{
					$sql = $sql . join( ' OR ' , $wsql );
					if( $udata = get_data( $sql ) )
						foreach( $udata as $uitem )
							if( !in_array( $uitem['id'] , $follow_uids ) )
								$myuids[] = $uitem['id'];

					if( isset( $myuids ) && is_array($myuids) )
					{
						$myuids = array_unique($myuids);
						foreach( $myuids as $muid )
						{
                            if( $muid != uid() && $muid != $tinfo['owner_uid'] ) {
                                $message = $_SESSION['uname'] . '在TODO的评论中@了你！';
                                push_todo_add_comment($muid, $message, $data['tid']);
                            }
						}
					}
				}
            }
            break;
        case 'FEED_ADD_COMMENT':
            $fid = intval(v('fid'));
            $lid = $data['id'];
            $content = $text = z(t(v('text')));

            $finfo = get_line("SELECT * FROM `feed` WHERE `id` = '" . intval( $fid ) . "' LIMIT 1");

			// 向参与了该Feed讨论的同学发送通知
			$sql = "SELECT `uid` FROM `comment` WHERE `fid`= '" . intval($fid) . "' ";
			
			if( $uitems = get_data( $sql ) )
			foreach( $uitems as $uitem )
			{
				if( $uitem['uid'] != uid() )
					$myuids[] = $uitem['uid'];	
			}

			if( isset($myuids) )
			{
				$myuids = array_unique($myuids);
				foreach( $myuids as $muid )
                {
                    $message = $_SESSION['uname'] . '评论了你参与讨论的动态！';
                    push_feed_add_comment($muid, $message);
				}
			}

            if( $finfo['uid'] != $_SESSION['uid'] )
            {
                $message = $_SESSION['uname'] . '评论了你的动态！';
                push_feed_add_comment($finfo['uid'], $message);
			}
			
			// 向被@的同学，发送通知
			if( $ats = find_at($content) )
			{
				$sql = "SELECT `id` FROM `user` WHERE ";
				foreach( $ats as $at )
				{
					$at =z(t($at));
					if( mb_strlen($at, 'UTF-8') < 2 ) continue;

					$wsql[] = " `name` = '" . s(t($at)) . "' ";
					if( c('at_short_name') )
						if( mb_strlen($at, 'UTF-8') == 2 )
							$wsql[] = " `name` LIKE '_" . s($at) . "' ";
				}
				
				if( isset( $wsql ) && is_array( $wsql ) )
				{
					$sql = $sql . join( ' OR ' , $wsql );
					if( $udata = get_data( $sql ) )
					{
						foreach( $udata as $uitem )
							$myuids[] = $uitem['id'];

						if( isset( $myuids ) && is_array( $myuids ) )
						{
							$myuids = array_unique( $myuids );
                            foreach( $myuids as $muid ) {
                                if( $muid != uid() && $muid != $finfo['uid'] ) {
                                    $message = $_SESSION['uname'] . '在动态的评论中@了你！';
                                    push_feed_add_comment($muid, $message);
                                }
                            }
						}
					}
				}
			}
            break;
    }
	return $data;
}

function push_im($to_uid = 0, $message = '', $message_id = 0) {
    $from_uid = $_SESSION['uid'];
    $from_uid = intval($from_uid);

    $to_uid = intval($to_uid);

    $message = t($message);

    if ($from_uid > 0 && $to_uid > 0 && strlen($message) > 0) {
        $sql = "SELECT * FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `uid` = '" . $to_uid . "'";
        $to_user = get_data($sql);

        if ($to_user) {
            foreach ($to_user as $user) {
                $push = array();
                $push['message'] = $message;
                $push['to_uid'] = $user['uid'];
                $push['push_token'] = $user['push_token'];
                $push['action'] = 'dm';
                $push['from_uid'] = $from_uid;
                $push['message_id'] = $message_id;

                set_post_data($push);
            }
        }
    }
}

function push_todo_assign($to_uid = 0, $message = '', $tid = 0) {
    $from_uid = $_SESSION['uid'];
    $from_uid = intval($from_uid);

    $to_uid = intval($to_uid);

    $message = t($message);

    if ($from_uid > 0 && $to_uid > 0 && strlen($message) > 0) {
        $sql = "SELECT * FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `uid` = '" . $to_uid . "'";
        $to_user = get_data($sql);

        if ($to_user) {
            foreach ($to_user as $user) {
                $push = array();
                $push['message'] = $message;
                $push['to_uid'] = $user['uid'];
                $push['push_token'] = $user['push_token'];
                $push['action'] = 'todo_assign';
                $push['from_uid'] = $from_uid;
                $push['todo_id'] = $tid;

                set_post_data($push);
            }
        }
    }
}

function push_todo_add_comment($to_uid = 0, $message = '', $tid = 0) {
    $from_uid = $_SESSION['uid'];
    $from_uid = intval($from_uid);

    $to_uid = intval($to_uid);

    $message = t($message);

    if ($from_uid > 0 && $to_uid > 0 && strlen($message) > 0) {
        $sql = "SELECT * FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `uid` = '" . $to_uid . "'";
        $to_user = get_data($sql);

        if ($to_user) {
            foreach ($to_user as $user) {
                $push = array();
                $push['message'] = $message;
                $push['to_uid'] = $user['uid'];
                $push['push_token'] = $user['push_token'];
                $push['action'] = 'todo_add_comment';
                $push['from_uid'] = $from_uid;
                $push['todo_id'] = $tid;

                set_post_data($push);
            }
        }
    }
}

function push_feed_add_comment($to_uid = 0, $message = '') {
    $from_uid = $_SESSION['uid'];
    $from_uid = intval($from_uid);

    $to_uid = intval($to_uid);

    $message = t($message);

    if ($from_uid > 0 && $to_uid > 0 && strlen($message) > 0) {
        $sql = "SELECT * FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `uid` = '" . $to_uid . "'";
        $to_user = get_data($sql);

        if ($to_user) {
            foreach ($to_user as $user) {
                $push = array();
                $push['message'] = $message;
                $push['to_uid'] = $user['uid'];
                $push['push_token'] = $user['push_token'];
                $push['action'] = 'feed_add_comment';
                $push['from_uid'] = $from_uid;

                set_post_data($push);
            }
        }
    }
}

add_action( 'UI_COMMON_SCRIPT' , 'check_iospush_script' );
function check_iospush_script()
{
	?>
	var sending_iospush = false;
	var iospush_noty = null ;
	function check_iospush()
	{
		var url = '?c=plugin&a=check_iospush' ;
	
		var params = {};
		$.post( url , params , function( data )
		{
			var data_obj = $.parseJSON( data );
			if( data_obj.err_code == 0 )
			{
				if( data_obj.data.to_send && parseInt( data_obj.data.to_send ) > 0 )
				{
					if( iospush_noty != null )
					{
						iospush_noty.setText('正在发送队列中的iOS推送信息-剩余'+parseInt( data_obj.data.to_send )+'封');
					}
					else
					iospush_noty = noty(
					{
						text:'正在发送队列中的iOS推送信息-剩余'+parseInt( data_obj.data.to_send )+'封',
						layout:'topRight',
					});

					sending_iospush = true;
					check_iospush();
				}
				else
				{
					if( sending_iospush )
					{
						sending_iospush = false;
						iospush_noty.close();
					}
				}
			}
		});	
	}

	setInterval( check_iospush , 12000 );

	<?php
}

// 添加API hook，完成业务逻辑
add_action('API_IOS_CHECKPUSH', 'ios_checkpush');
function ios_checkpush()
{
    plugin_check_iospush();
}


add_action( 'PLUGIN_CHECK_IOSPUSH' , 'plugin_check_iospush' );
function plugin_check_iospush()
{
    include_once( AROOT .'controller' . DS . 'api.class.php');

	$sql = "SELECT * FROM `".IOSPUSH_MESSAGE_TABLE."` WHERE `sent` = 0 ORDER BY `dateline` DESC LIMIT 1";
    if( $line = get_line( $sql ) )
    {

        $id = $line['id'];

        $sql = "UPDATE `".IOSPUSH_MESSAGE_TABLE."` SET `sent` = 2 WHERE `id` = '" . intval($id) . "' LIMIT 1";
        run_sql( $sql );

        if( db_errno() == 0  ) {

            $push = unserialize($line['body']);

            $push['m'] = 'api';
            $push['a'] = 'send_push';
            $push['domain'] = $_SERVER['HTTP_HOST'];

            if (@post_data(IOSPUSH_API, $push)) {
                $sql = "UPDATE `".IOSPUSH_MESSAGE_TABLE."` SET `sent` = 1 WHERE `id` = '" . intval($id) . "' LIMIT 1";
            } else {
                $sql = "UPDATE `".IOSPUSH_MESSAGE_TABLE."` SET `sent` = -1 WHERE `id` = '" . intval($id) . "' LIMIT 1";
            }

            run_sql( $sql );

            if( db_errno() != 0  ) apiController::send_error( LR_API_DB_ERROR , 'DATABASE ERROR ' . db_error() );
            return apiController::send_result( array('to_send'=>get_var("SELECT COUNT(*) FROM `".IOSPUSH_MESSAGE_TABLE."` WHERE `sent` = 0 ")) );
        } else {
            apiController::send_error( LR_API_DB_ERROR , 'DATABASE ERROR ' . db_error() );
        }
    } else {
        return apiController::send_result( array('to_send'=>0) );
    }
}

//direct message list
add_action('API_IOS_DM_LIST', 'ios_dm_list');
function ios_dm_list()
{
    $uid = $_SESSION['uid'];
    $uid = intval($uid);

    $sql = "SELECT * FROM `message` WHERE `from_uid` = '" . intval($uid) . "' OR `to_uid` = '" . intval($uid) . "'  ORDER BY `id` DESC";
    //sae_debug( 'sql=' . $sql );

    if( !$data = get_data( $sql ) ) return apiController::send_error( LR_API_DB_EMPTY_RESULT , 'EMPTY RESULT' );

    $chat_data = array();

    if( db_errno() != 0 )
        return apiController::send_error(  LR_API_DB_ERROR , 'DATABASE ERROR '   );
    else
    {
        if( is_array( $data ) )
        {
            $tmp = array();

            foreach ($data as $key => $value)
            {
                $k_1 = $value['from_uid'] . '-' . $value['to_uid'];
                $k_2 = $value['to_uid'] . '-' . $value['from_uid'];

                if (isset($tmp[$k_1])) {
                    $tmp[$k_1][] = $value;
                } else {
                    if (isset($tmp[$k_2])) {
                        $tmp[$k_2][] = $value;
                    } else {
                        $tmp[$k_1] = array();
                        $tmp[$k_1][] = $value;
                    }
                }
            }


            foreach ($tmp as $key => $value) {
                $keys = explode('-', $key);
                $keys[0] = intval($keys[0]);
                $keys[1] = intval($keys[1]);

                $buddy_id = ($uid == $keys[0]) ? $keys[1] : $keys[0];
                $buddy = get_user_info_by_id($buddy_id);

                $last_message = reset($value);

                $chat_data[] = array('buddy' => $buddy, 'last_message' => $last_message);
            }
        }
        return apiController::send_result(  array( 'items' => $chat_data)  );
    }
}

add_action('API_IOS_PLUGIN_VERSION', 'ios_plugin_version');
function ios_plugin_version()
{
    return apiController::send_result(  array( 'version' => IOSPUSH_PLUGIN_VERSION, 'build' => IOSPUSH_PLUGIN_BUILD)  );
}

function set_post_data($data) {
    $body = serialize($data);
    $data = array();
    $data['body'] = $body;
    $data['dateline'] = time();
    $data['sent'] = 0;

    $insertkeysql = $insertvaluesql = $comma = '';
    foreach ($data as $insert_key => $insert_value) {
        $insertkeysql .= $comma.'`'.$insert_key.'`';
        $insertvaluesql .= $comma.'\''.$insert_value.'\'';
        $comma = ', ';
    }
    $method = 'INSERT';

    $sql = $method.' INTO `'.IOSPUSH_MESSAGE_TABLE.'` ('.$insertkeysql.') VALUES ('.$insertvaluesql.')';

    run_sql( $sql );
}

function post_data($url, $data) {
    $res = false;

    $sets = array();
    foreach ($data as $key => $val) {
        $sets[] = $key . '=' . urlencode($val);
    }
    $fields = implode('&', $sets);

    $ch = curl_init(); //初始化curl
    curl_setopt($ch, CURLOPT_URL, $url);//设置链接
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//设置是否返回信息
    curl_setopt($ch, CURLOPT_POST, 1);//设置为POST方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);//POST数据
    $response = curl_exec($ch);//接收返回信息
    if(curl_errno($ch)){//出错则显示错误信息
        $res = false;
    } else {
        $res = true;
    }
    curl_close($ch); //关闭curl链接

    return $res;
}
