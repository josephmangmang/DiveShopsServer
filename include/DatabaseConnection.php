<?php

/* 
 * Copyright 2017 Joseph Mangmang.
 * Created 15-Feb-2017
 *
 */

class DatabaseConnection{
    private $conn;
    
    public function __construct() {
        
    }
    public function connect(){
        include_once 'Config.php';
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if(mysqli_connect_errno()){
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }
        return $this->conn;
    }
} 