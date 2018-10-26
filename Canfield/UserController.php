<?php

require_once("Mysql.php");

class UserController {

    static private $db;

    function __construct() {
        self::$db = new Mysql('localhost','root','','canfield_db', 'user');
    }

    public function getUser($id) {
        return self::$db->find($id)->get();
    }

    public function getAllUsers() {
        return self::$db->all()->get();
    }

    public function incrementCount($id) {
        return self::$db->where('user_id', $id)->update('access_count', 'access_count + 1');
    }

}