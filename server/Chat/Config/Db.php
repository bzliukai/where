<?php

namespace Config;

/**
 * Class Db  数据库配置类
 * @package Config
 * @
 */
class Db
{
    /**
     * redis配置
     */
    public static $redis = array(
        
    );

    /**
     * mysql配置
     */
    public static $mysql = array(
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'user'    => 'root',
        'password' => '123456',
        'dbname'  => 'outsideLeader',
        'charset'    => 'utf8',
    );
}