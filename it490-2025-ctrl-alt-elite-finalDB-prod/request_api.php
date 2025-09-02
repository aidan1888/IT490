<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


require_once('get_db_1.php');
require_once('add_test.php');

function handleFetchRequest($request) {
    error_log("handleFetchRequest called");
    error_log("Request: " . print_r($request, true));

    if ($request["type"] === "fetch_flight_by_reg") {
        $flightReg = $request["flight_reg"];
        error_log("Processing fetch_flight_by_reg for: $flightReg");


        $iniFiles = ['db-api1.ini', 'db-api2.ini'];
        $apiClient = new rabbitMQClient($iniFiles, "testServer");
        $apiPayload = [
            "type" => "fetch_flight_data",
            "flight_reg" => $flightReg
        ];
        error_log("Sending API request: " . json_encode($apiPayload));
        $apiResponse = $apiClient->send_request($apiPayload);

        error_log("API response: " . print_r($apiResponse, true));

        if (!$apiResponse || !isset($apiResponse["status"]) || $apiResponse["status"] !== "success") {
            error_log("API fetch failed");
            return ["status" => "error", "message" => "API fetch failed"];
        }

        error_log("Inserting departure airport: " . print_r($apiResponse["departure_airport"], true));
        addOrUpdateAirport($apiResponse["departure_airport"]);

        error_log("Inserting arrival airport: " . print_r($apiResponse["arrival_airport"], true));
        addOrUpdateAirport($apiResponse["arrival_airport"]);

        error_log("Inserting flight: " . print_r($apiResponse["flight"], true));
        $result = addFlight($apiResponse["flight"]);

        error_log("addFlight result: " . print_r($result, true));

        // Return the "queued" status so the app side shows success flash message
        if (!empty($result) && (!isset($result["status"]) || $result["status"] !== "error")) {
            return ["status" => "queued", "message" => "Flight fetch request queued successfully"];
        } else {
            return ["status" => "error", "message" => "Failed to insert flight data"];
        }
    }

    error_log("Unhandled request type: " . $request["type"]);
    return ["status" => "error", "message" => "Unhandled request"];
}
