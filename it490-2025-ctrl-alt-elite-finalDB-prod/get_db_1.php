<?php

function getDB() {

    $ini = parse_ini_file(".env");
    //var_dump($ini);

    $servername = $ini["HOST"];
    $username = $ini["USER"];
    $password = $ini["PASSWORD"];
    $database = $ini["DATABASE"];

    global $db;

    if(!isset($db))
    {

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
            // Set PDO error mode to exception
    	    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("Connected successfully");
            $db = $conn;
            return $db;
        } catch(PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
        }
    }
    return $db;
}
