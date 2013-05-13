<?php
/*** 
TeamToy extenstion info block
##name iOS客户端TToy支持
##folder_name ttoy
##author 李博
##email lb13810398408@gmail.com
##reversion 1.0.8
##desp 使TToy（iOS客户端）可以从本站获得额外功能（例如Push）支持。
##update_url http://tt2net.sinaapp.com/?c=plugin&a=update_package&name=stoken 
##reverison_url http://tt2net.sinaapp.com/?c=plugin&a=latest_reversion&name=stoken 
***/

// 检查并创建数据库
define('IOSPUSH_PLUGIN_VERSION', '1.0.8');
define('IOSPUSH_PLUGIN_BUILD', '20130301');
define('IOSPUSH_DEVICE_TABLE', 'iospush_userdevice');
define('IOSPUSH_MESSAGE_TABLE', 'iospush_message');

define('IOSPUSH_API', 'https://iospushservice.sinaapp.com/index.php');

function create_device_table() {
    run_sql("CREATE TABLE `".IOSPUSH_DEVICE_TABLE."` 
	(
		`uid` INT NOT NULL ,
        `device_id` VARCHAR( 32 ) NOT NULL ,
        `push_token` VARCHAR( 71 ) NOT NULL ,
		PRIMARY KEY (  `uid` )
	) 	ENGINE = MYISAM ");
}

function create_message_table() {
    run_sql("CREATE TABLE `".IOSPUSH_MESSAGE_TABLE."` 
	(
        `id` INT( 10 ) unsigned NOT NULL AUTO_INCREMENT ,
        `body` TEXT NOT NULL ,
        `dateline` INT(10) unsigned NOT NULL DEFAULT 0 ,
        `sent` TINYINT(1) NOT NULL DEFAULT 0 ,
		PRIMARY KEY (  `id` )
	) 	ENGINE = MYISAM ");
}

function check_device_table_column() {

    $cols = array();
    $cols[] = 'uid';
    $cols[] = 'device_id';
    $cols[] = 'push_token';

    $data = get_data("SHOW COLUMNS FROM `".IOSPUSH_DEVICE_TABLE."`",db());

    if (count($data) == 3) {
        $err = 0;
        foreach ($data as $key => $line) {
            if ($line['Field'] != $cols[$key]) {
                $err = 1;
            }
        }
        if ($err == 0) {
            return true;
        }
    }
    return false;
}

function check_message_table_column() {

    $cols = array();
    $cols[] = 'id';
    $cols[] = 'body';
    $cols[] = 'dateline';
    $cols[] = 'sent';

    $data = get_data("SHOW COLUMNS FROM `".IOSPUSH_MESSAGE_TABLE."`",db());

    if (count($data) == 4) {
        $err = 0;
        foreach ($data as $key => $line) {
            if ($line['Field'] != $cols[$key]) {
                $err = 1;
            }
        }
        if ($err == 0) {
            return true;
        }
    }
    return false;
}

if( !mysql_query("SHOW COLUMNS FROM `".IOSPUSH_DEVICE_TABLE."`",db()) )
{
	// table not exists
	// create it
    create_device_table();
} else {
    if (!check_device_table_column()) {
        $sql = "DROP TABLE `".IOSPUSH_DEVICE_TABLE."`";
        run_sql($sql);
        create_device_table();
    }
}

if( !mysql_query("SHOW COLUMNS FROM `".IOSPUSH_MESSAGE_TABLE."`",db()) )
{
	// table not exists
	// create it
    create_message_table();
} else {
    if (!check_message_table_column()) {
        $sql = "DROP TABLE `".IOSPUSH_MESSAGE_TABLE."`";
        run_sql($sql);
        create_message_table();
    }
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
        $sql = "SELECT * FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `device_id` = '" . $device_id . "' OR `push_token` = '" . $push_token . "'";
        $exists = get_data($sql);

        if (!empty($exists)) {
            foreach ($exists as $e) {
                $data['device_id'] = $device_id;
                $data['push_token'] = $push_token;
                $data['uid'] = $exists['uid'];

                $post = array();
                $post['m'] = 'api';
                $post['a'] = 'user_remove';
                $post['push_token'] = $push_token;
                $post['uid'] = $uid;
                $post['teamtoy'] = $_SERVER['HTTP_HOST'];

                @post_data(IOSPUSH_API, $post);
            }
        }

        $sql = "DELETE FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `device_id` = '" . $device_id . "' OR `push_token` = '" . $push_token . "'";
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
        $sql = "DELETE FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `uid` = '" . intval( $uid ) . "' AND (`device_id` = '" . $device_id . "' OR `push_token` = '" . $push_token . "') LIMIT 1";

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

add_action( 'SEND_NOTICE_AFTER' , 'push' );
function push( $data )
{
    $from_uid = $_SESSION['uid'];
    $from_uid = intval($from_uid);

    $to_uid = $data['uid'];

    $sql = "SELECT * FROM `".IOSPUSH_DEVICE_TABLE."` WHERE `uid` = '" . $to_uid . "'";
    $to_user = get_data($sql);

    if ($to_user) {
        foreach ($to_user as $user) {
            $push = array();
            $push['message'] = $data['content'];
            $push['to_uid'] = $user['uid'];
            $push['push_token'] = $user['push_token'];
            $push['type'] = $data['type'];
            $push['action'] = 'notice';
            $push['from_uid'] = $from_uid;

            set_post_data($push);
        }
    }
}

add_filter( 'API_IM_SEND_OUTPUT_FILTER' , 'dm_push' );
function dm_push()
{
    $to_uid = intval(v('uid'));
    $message = z(t(v('text')));
    if ($to_uid > 0 && !empty($message)) {
        $message_id = last_id();
        $message_id = intval($message_id);
        $message = '【新私信】' . $_SESSION['uname'] . '：' . $message;

        $from_uid = $_SESSION['uid'];
        $from_uid = intval($from_uid);

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

            $data = array();
            $buddy_ids = array();
            $chat_data = array();

            foreach ($tmp as $key => $value) {
                $keys = explode('-', $key);
                $keys[0] = intval($keys[0]);
                $keys[1] = intval($keys[1]);

                $buddy_id = ($uid == $keys[0]) ? $keys[1] : $keys[0];

                $buddy_ids[] = $buddy_id;

                $last_message = reset($value);

                $data[$buddy_id] = $last_message;

            }

            if (!empty($buddy_ids) && !empty($data)) {
                $ids = "'".implode("','", (array)$buddy_ids)."'";

                $sql = "SELECT " . USER_INFO . " FROM `user` WHERE `id` IN($ids)";
                $tmp = get_data($sql);

                foreach ((array)$tmp as $buddy) {

                    if (isset($data[$buddy['id']])) {
                        $chat_data[] = array('buddy' => $buddy, 'last_message' => $data[$buddy['id']]);
                    }
                }

            }
        }
        $sql = "UPDATE `message` SET `is_read` = 1 WHERE `to_uid` = '" . intval($uid) . "' LIMIT 100";
        run_sql( $sql );

        return apiController::send_result(  array( 'items' => $chat_data)  );
    }
}

add_action('API_IOS_PLUGIN_VERSION', 'ios_plugin_version');
function ios_plugin_version()
{
    return apiController::send_result(  array( 'version' => IOSPUSH_PLUGIN_VERSION, 'build' => IOSPUSH_PLUGIN_BUILD)  );
}

function set_post_data($data)
{
    $to_uid = $data['to_uid'];

    $sql = "SELECT COUNT(*) FROM `notice` WHERE `to_uid` = '" . intval($to_uid) . "' AND `is_read` = 0 ";
    $notice_count = intval(get_var( $sql ));

    $sql = "SELECT COUNT(*) FROM `message` WHERE `to_uid` = '" . intval($to_uid) . "' AND `is_read` = 0 ";
    $message_count = intval(get_var( $sql ));

    $data['badge'] = $notice_count + $message_count;

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

    $data['ver'] = IOSPUSH_PLUGIN_VERSION;

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
