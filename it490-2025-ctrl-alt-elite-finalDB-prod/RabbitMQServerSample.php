<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

//Testing Files
require_once('get_db_1.php');
require_once('add_test.php');
require_once('request_api.php');
// Add User PHP Doc

// Remove favorite
function _removeUserFlight($message) {
    $user_id = $message["user_id"] ?? null;
    $flight_id = $message["flight_id"] ?? null;

    if (!$user_id || !$flight_id) {
        return ["status" => "error", "message" => "Missing user_id or flight_id"];
    }

    return removeUserFlight([
        "user_id" => $user_id,
        "flight_id" => $flight_id
    ]);
}

//-----------Saves Favorites
function _saveUserFlight($message) {
    $user_id = $message["user_id"] ?? null;
    $flight_id = $message["flight_id"] ?? null;

    if (!$user_id || !$flight_id) {
        return ["status" => "error", "message" => "Missing user_id or flight_id"];
    }

    
    return saveUserFlight([
        "user_id" => $user_id,
        "flight_id" => $flight_id
    ]);
}

function _getUserFlights($message) {
    $user_id = $message["user_id"] ?? null;
    if (!$user_id) {
        return ["status" => 400, "message" => "Missing user_id"];
    }

    return getUserFlights($user_id);
}

function login($username,$password){
	//TODO validate user credentials
	$status = _login($username, $password);
	return $status;
}

function _searchUsers($message){
    return searchUsers($message);
}

function _managersearchFlights($message){
    return managersearchFlights($message);
}

function _adminsearchFlights($message){
    return adminsearchFlights($message);
}

function _updatePassword($passwordMessage){
	return updatePassword($passwordMessage);
}

function _searchFlights($message){
    return searchFlights($message);
}

function _add_test($message){
	return add_test($message);
}


function validate($sessionid){
	return validate($sessionid);
}


function _addUser($username, $email, $password){
	return addUser($username, $email, $password);
}

function _loginUser($message){
	return loginUser($message);
}

function _createUser($message){
	return createUser($message);
}

function _profileUser($profileMessage){
	return profileUser($profileMessage);
}

function _getadminFlight($message){
	return getadminFlight($message);
}

function _getmanagerFlight($message){
	return getmanagerFlight($message);
}

function _admindeleteFlight($message){
	return admindeleteFlight($message);
}


function _admingetUser($message) {
    return getUserById($message);
}

// manual flight entry
function _addFlight($flight) {
    
    if (!empty($flight["departure_airport"])) {
        addOrUpdateAirport($flight["departure_airport"]);
    }
    if (!empty($flight["arrival_airport"])) {
        addOrUpdateAirport($flight["arrival_airport"]);
    }

    // Normalize aircraft registration if needed
    if (empty($flight["AircraftRegistration"]) && !empty($flight["Registration"])) {
        $flight["AircraftRegistration"] = $flight["Registration"];
    }

    // Default round trip to false if not set
    if (!isset($flight["IsRoundTrip"])) {
        $flight["IsRoundTrip"] = false;
    }

    // Insert flight to DB
    return addFlight($flight);
}

//fetch api flight
function _fetchFlight($request){
    error_log("This is what is going to get inserted: " . $request);
	return handleFetchRequest($request);
}

function _getFlightById($message) {
    return getFlightById($message['flight_id']);
}

function _getFlightById2($message) {
    return getFlightsByIds2($message['ids']); 
}

function _getUserById($message) {
    return getUserById($message);
}

function _updateFlight($message) {
    // Handle airport updates if needed
    if (!empty($message["departure_airport"])) {
        addOrUpdateAirport($message["departure_airport"]);
    }
    if (!empty($message["arrival_airport"])) {
        addOrUpdateAirport($message["arrival_airport"]);
    }
    
    // Normalize aircraft registration if needed
    if (empty($message["AircraftRegistration"]) && !empty($message["Registration"])) {
        $message["AircraftRegistration"] = $message["Registration"];
    }
    
    // Default round trip to false if not set
    if (!isset($message["IsRoundTrip"])) {
        $message["IsRoundTrip"] = false;
    }
    
    // Update flight in DB
    return updateFlight($message);
}

function _getAllFlights($message) {
    $limit = $message['limit'] ?? 50;
    return getAllFlights($limit);
}

function _deleteFlight($message) {
    return deleteFlight($message['flight_id']);
}

function _adminupdateUser($request){
    return adminupdateUser($request);
}

function _deleteUserById($request){
    return deleteUserById($request);
}

function _adminsearchUsers($request){
    return adminsearchUsers($request);
}

function request_processor($req){
	echo "Received Request".PHP_EOL;
	echo "<pre>" . var_dump($req) . "</pre>";
	if(!isset($req['type'])){
		return "Error: unsupported message type";
	}
	//Handle message type
	$type = $req['type'];
	switch($type){
		case "get_user_flights":
			return _getUserFlights($req);
		case "login":
			return _loginUser($req);
		case "register":
			return _createUser($req);
		case 'update_profile':
			return _profileUser($req);
		case 'update_password':
			return _updatePassword($req);
		case "save_user_flight":
    			return _saveUserFlight($req);
		case "remove_user_flight":
			return _removeUserFlight($req);
		case "validate_session":
			return validate($req['session_id']);
		case "search_users":
			return _searchUsers($req);
		case "flight_entry":	
            return _addFlight($req);	
		case "fetch_flight_by_reg":
			return _fetchFlight($req);
        case "test":
            return _add_test($req["message"]);
        case "search_flights":
            return _searchFlights($req);
        case "manager_search_flights":
            return _managersearchFlights($req);
        case "admin_search_flights":
            return _adminsearchFlights($req);
        case "admin_get_flight":
            return _getadminFlight($req);
        case "manager_get_flight":
            return _getmanagerFlight($req);    
		case "get_flight_by_id":
    		return _getFlightById($req);
		case "update_flight":
    		return _updateFlight($req);
		case "get_all_flights":
    		return _getAllFlights($req);
		case "delete_flight":
   			return _deleteFlight($req);
        case 'get_flight':
            return getFlight($req);
        case "admin_delete_flight":
             return _admindeleteFlight($req);
        case "admin_search_users":
             return _adminsearchUsers($req);
        case "admin_get_user":  
             return _admingetUser($req);
        case "delete_user":
             return _deleteUserById($req);
        case "admin_update_user":
             return _adminupdateUser($req);
        case "admin_delete_user":  
             return _deleteUserById($req);

case "get_flight_by_id2":
    $res = _getFlightById2($req);
    if (isset($res['success']) && $res['success']) {
        return [
            "return_code" => 0,
            "message" => "Flights retrieved successfully",
            "flights" => $res['flights']
        ];
    } else {
        return [
            "return_code" => 1,
            "message" => $res['message'] ?? "Failed to retrieve flights"
        ];
    }

		case "echo":
			return array("return_code"=>'0', "message"=>"Echo: " .$req["message"]);
	}
	return array("return_code" => '0',
		"message" => "Server received request and processed it");
}

$iniFiles = ['app-db1.ini', 'app-db2.ini'];

$server = new rabbitMQServer($iniFiles, "testServer");

echo "Rabbit MQ Server Start" . PHP_EOL;
$server->process_requests('request_processor');
echo "Rabbit MQ Server Stop" . PHP_EOL;
exit();
?>
