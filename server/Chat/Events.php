<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // 向当前client_id发送数据 
        Gateway::sendToClient($client_id, "Hello $client_id");
        // 向所有人发送
        Gateway::sendToAll("$client_id login");
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        //在这开启一个redis连接
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);

        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:" . json_encode($_SESSION) . " onMessage:" . $message . "\n";

        // 客户端传递的是json数据,如果没有收到任何数据,直接返回
        $message_data = json_decode($message, true);
        if (!$message_data) {
            return;
        }

        //根据不同的类型,执行不同的操作
        switch ($message_data['type']) {
            //客户端回应服务器的心跳
            case 'pong':
                return;

            //用户加入行程
            case 'join':
                // 判断是否有行程号,这个要判断的是用户输入的行程号是否在redis库中
                if (!isset($message_data['travel_num'])) {
                    throw new \Exception("\$message_data['travel_num'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
                }
                /**
                 * 设计思路
                 * 每个用户单独用一个redis键,存该用户和和当前行程的相关信息hash类型
                 * 把用户的即时行程坐标存进list类型 list user_id_travel []
                 * 把该行程的所有成员存进一个list中, travel_num_member[]
                 */
                // 把该用户的相关信息存进redis中,哈希类型,键为user_id_idNum  {user_id_12:12,travel_num:12,travel_line:user_id_line}
                $redis->hMset('user_id_'.$message_data['user_id'],['travel_num'=>$message_data['travel_num'],'travel_line'=>'user_line_'.$message_data['user_id']]);
                // 获取该行程内的所有用户
                $clients_list = Gateway::getClientInfoByGroup($message_data['travel_num']);
                foreach ($clients_list as $tmp_client_id => $item) {
                    $clients_list[$tmp_client_id] = $item['client_name'];
                }
                $clients_list[$client_id] = $message_data['user_id'];

                // 转播给当前行程内的的所有用户，xx加入行程 message {type:login, client_id:xx, name:xx}
                $new_message = array('type' => $message_data['type'], 'client_id' => $client_id, 'client_name' => $message_data['user_id'], 'time' => date('Y-m-d H:i:s'));
                Gateway::sendToGroup($message_data['travel_num'], json_encode($new_message));
                Gateway::joinGroup($client_id, $message_data['travel_num']);
                //测试消息
                $new_message = array(
                    'type'=>$message_data['type'],
                    'user_id'=>$message_data['user_id'],
                    'travel_num'=>$message_data['travel_num']
                );
                //发送
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;
        }

        // 向所有人发送 
        //Gateway::sendToAll("$client_id said $message");
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        // 向所有人发送 
        GateWay::sendToAll("$client_id logout");
    }
}
