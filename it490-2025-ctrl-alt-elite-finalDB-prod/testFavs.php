#!/usr/bin/php



require_once("add_test.php");
$req["user_id"] = -1;
$req["flight_id"] = 4;
$result = saveUserFlight($req); // Replace with real user_id and flight_id
var_dump($result);
