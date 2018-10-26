<?php


class Mysql {

    static private $connection;
    static private $table;
    private $sql;

    function __construct($HOST, $USER, $PASS, $DB, $TABLE) {
        if (!self::$connection = mysqli_connect($HOST, $USER, $PASS, $DB)) {
            throw new Exception("There was a problem connecting to the database.");
        }
        self::$table = $TABLE;
    }

    function __destruct() {
        mysqli_close(self::$connection);
    }


    public function all() {
        $this->sql = "SELECT * FROM " . self::$table;
        return $this;
    }

    public function find($id) {
        $this->sql = "SELECT * FROM " . self::$table . " WHERE `user_id` = " . $id;
        return $this;
    }

    public function where($col, $val) {
        $this->sql .= " WHERE " . $col . " = " . $val;
        return $this;
    }

    public function update($col, $val) {
        $this->sql = "UPDATE " . self::$table . " SET " . $col . " = " . $val . $this->sql;
        return mysqli_query(self::$connection, $this->sql);
    }

    public function get() {
        $allResults = array();
        $result = mysqli_query(self::$connection, $this->sql);
        while($row = $result->fetch_assoc()) {
            array_push($allResults, $row);
        }
        return $allResults;
    }

}