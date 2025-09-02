#!/usr/bin/php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once("add_test.php");
$result = saveUserFlight(["user_id" => -1, "flight_id" => 6]);
var_dump($result);
