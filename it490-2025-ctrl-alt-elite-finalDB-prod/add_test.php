<?php
require_once('get_db_1.php');
/*
function add_test($message)
{
    try {
        $db=getDB();
	$stmt=$db->prepare("INSERT INTO test (message) VALUES (:message)");
	$stmt->execute([":message" => $message]);
	return $stmt->rowCount();
    } catch (PDOException $e){
        return $e;
    }

}
*/


function deleteUserById($request) {
    $id = (int)($request['user_id'] ?? $request['id'] ?? 0);
    if (!$id) {
        return ['success' => false, 'message' => 'Missing user id'];
    }

  $db = getDB(); 

    try {
       
        $db->beginTransaction();
        
  
        $stmt = $db->prepare("DELETE FROM user_flights WHERE user_id = :id");
        $stmt->execute([':id' => $id]);
        
        $stmt = $db->prepare("DELETE FROM UserRoles WHERE user_id = :id");
        $stmt->execute([':id' => $id]);
        
        
        $stmt = $db->prepare("DELETE FROM Users WHERE id = :id");
        $success = $stmt->execute([':id' => $id]);
        
        if ($success && $stmt->rowCount() > 0) {
            $db->commit();
            return ['success' => true, 'message' => 'User deleted successfully'];
        } else {
            $db>rollBack();
            return ['success' => false, 'message' => 'User not found'];
        }
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Delete user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

function searchUsers($message) {
    $db = getDB(); 

    $filters = $message['filters'] ?? [];
    $sort = $message['sort'] ?? [];
    $limit = isset($message['limit']) ? (int)$message['limit'] : 50;

  
    $limit = max(1, min($limit, 100));

   
    $order = strtoupper($sort['order'] ?? 'ASC');
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

    $sortColumn = $sort['column'] ?? 'email';
    $columnMap = [
        'email' => 'u.email',
       
        'joined_date' => 'u.created',
        'created_date' => 'u.created'
    ];
    $orderBy = $columnMap[$sortColumn] ?? 'u.email';

    
    $emailSearch = trim($filters['email'] ?? '');

    
    $escapeLike = static function(string $s): string {
        return strtr($s, [
            '\\' => '\\\\',
            '%' => '\%',
            '_' => '\_',
        ]);
    };

    
    $sql = "SELECT 
                u.id,
                u.email,
                u.created as joined_date,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') as roles
            FROM Users u
            LEFT JOIN UserRoles ur ON u.id = ur.user_id AND ur.is_active = 1
            LEFT JOIN Roles r ON ur.role_id = r.id AND r.is_active = 1
            WHERE 1=1";

    $params = [];

   
    if ($emailSearch !== '') {
        $sql .= " AND u.email LIKE ? ESCAPE '\\\\'";
        $params[] = '%' . $escapeLike($emailSearch) . '%';
    }

    $sql .= " GROUP BY u.id, u.email, u.created";
    $sql .= " ORDER BY $orderBy $order";

   
    $sql .= " LIMIT $limit";

  
    error_log("SQL Query: $sql");
    error_log("Bound Params: " . print_r($params, true));

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [
            "success" => false,
            "status" => "error",
            "message" => "Prepare failed: " . implode(" ", $db->errorInfo())
        ];
    }

    if (!$stmt->execute($params)) {
        $errorInfo = $stmt->errorInfo();
        return [
            "success" => false,
            "status" => "error",
            "message" => "Execute failed: " . implode(" ", $errorInfo)
        ];
    }

    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['roles'])) {
            $roleNames = explode(',', $row['roles']);
            $row['roles'] = array_map('trim', $roleNames);
        } else {
            $row['roles'] = [];
        }
        $users[] = $row;
    }

    return [
        "success" => true,
        "status" => "success",
        "order" => $order,
        "count" => count($users),
        "users" => $users,
    ];
}





// Remove favorite flights
function removeUserFlight($req) {
    $user_id = $req["user_id"] ?? null;
    $flight_id = $req["flight_id"] ?? null;

    if (!$user_id || !$flight_id) {
        return ["status" => 400, "message" => "Missing user_id or flight_id"];
    }

    $db = getDB();
    $stmt = $db->prepare("DELETE FROM user_flights WHERE user_id = :uid AND flight_id = :fid");

    try {
        $stmt->execute([":uid" => $user_id, ":fid" => $flight_id]);
        return ["status" => 200, "message" => "Flight unfavorited"];
    } catch (PDOException $e) {
        error_log("Failed to remove flight: " . $e->getMessage());
        return ["status" => 500, "message" => "Failed to unfavorite flight"];
    }
}


function saveUserFlight($req) {
    $user_id = $req["user_id"] ?? null;
    $flight_id = $req["flight_id"] ?? null;

    if (!$user_id || !$flight_id) {
        return ["status" => 400, "message" => "Missing user_id or flight_id"];
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO user_flights (user_id, flight_id) VALUES (:uid, :fid);");

    try {
        $stmt->execute([":uid" => $user_id, ":fid" => $flight_id]);
        return ["status" => 200, "message" => "Flight favorited"];
    } catch (PDOException $e) {
        error_log("Failed to save flight: " . $e->getMessage());
        return ["status" => 500, "message" => "Failed to favorite flight"];
    }
}


function getUserFlights($user_id) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT f.*
        FROM user_flights uf
        JOIN Flights f ON uf.flight_id = f.id
        WHERE uf.user_id = :uid
    ");

    try {
        $stmt->execute([":uid" => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB error in getUserFlights: " . $e->getMessage());
        return [];
    }
}


function addOrUpdateAirport($airport) {
    $db = getDB();

    
    if (empty($airport['icao'])) {
        error_log("Missing ICAO code in airport data");
        return false;
    }

    $stmt = $db->prepare("
        INSERT INTO Airports (
            icao,
            iata,
            name,
            city,
            country,
            latitude,
            longitude,
            timezone
        ) VALUES (
            :icao,
            :iata,
            :name,
            :city,
            :country,
            :latitude,
            :longitude,
            :timezone
        )
        ON DUPLICATE KEY UPDATE
            iata = VALUES(iata),
            name = VALUES(name),
            city = VALUES(city),
            country = VALUES(country),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            timezone = VALUES(timezone)
    ");

    try {
        $stmt->execute([
            ':icao' => $airport['icao'],
            ':iata' => $airport['iata'] ?? null,
            ':name' => $airport['name'] ?? null,
            ':city' => $airport['city'] ?? null,        
            ':country' => $airport['country'] ?? null,  
            ':latitude' => $airport['latitude'] ?? null, 
            ':longitude' => $airport['longitude'] ?? null, 
            ':timezone' => $airport['timezone'] ?? null
        ]);
        return $airport['icao'];
    } catch (PDOException $e) {
        error_log("Airport insert/update error: " . $e->getMessage());
        return false;
    }
}

function addFlight($flight) {
    $db = getDB();
    try {
        $departureTime = (new DateTime($flight['DepartureTime']))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Invalid DepartureTime: " . $flight['DepartureTime']);
        $departureTime = null;
    }

    try {
        $arrivalTime = (new DateTime($flight['ArrivalTime']))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Invalid ArrivalTime: " . $flight['ArrivalTime']);
        $arrivalTime = null;
    }

    $stmt = $db->prepare("
        INSERT INTO Flights (
            flight_number,
            airline,
            origin_icao,
            destination_icao,
            departure_time,
            arrival_time,
            real_time_status,
            aircraft_model,
            aircraft_registration,
            aircraft_image_url,
            is_round_trip,
            seat_capacity,
            registration_date,
            aircraft_age
        ) VALUES (
            :flight_number,
            :airline,
            :origin_icao,
            :destination_icao,
            :departure_time,
            :arrival_time,
            :real_time_status,
            :aircraft_model,
            :aircraft_registration,
            :aircraft_image_url,
            :is_round_trip,
            :seat_capacity,
            :registration_date,
            :aircraft_age
        )
            ON DUPLICATE KEY UPDATE
        airline = VALUES(airline),
        origin_icao = VALUES(origin_icao),
        destination_icao = VALUES(destination_icao),
        arrival_time = VALUES(arrival_time),
        real_time_status = VALUES(real_time_status),
        aircraft_model = VALUES(aircraft_model),
        aircraft_registration = VALUES(aircraft_registration),
        aircraft_image_url = VALUES(aircraft_image_url),
        is_round_trip = VALUES(is_round_trip),
        seat_capacity = VALUES(seat_capacity),
        registration_date = VALUES(registration_date),
        aircraft_age = VALUES(aircraft_age)
    ");

    try {
        $stmt->execute([
            ':flight_number' => $flight['FlightNumber'],
            ':airline' => $flight['Airline'],
            ':origin_icao' => $flight['OriginICAO'],
            ':destination_icao' => $flight['DestinationICAO'],
            ':departure_time' => $departureTime,
            ':arrival_time' => $arrivalTime,
            ':real_time_status' => $flight['RealTimeStatus'],
            ':aircraft_model' => $flight['AircraftModel'] ?? null,
            ':aircraft_registration' => $flight['AircraftRegistration'] ?? null,
            ':aircraft_image_url' => $flight['AircraftImageURL'] ?? null,
            ':is_round_trip' => $flight['IsRoundTrip'] ? 1 : 0,
            ':seat_capacity' => $flight['SeatCapacity'] ?? null,
            ':registration_date' => $flight['RegistrationDate'] ?? null,
            ':aircraft_age' => $flight['AircraftAge'] ?? null
        ]);

    $affectedRows = $stmt->rowCount();
    error_log("Rows affected by insert: " . $affectedRows);

       return [
            "success" => true,
            "message" => "Flight inserted",
            "rows_affected" => $affectedRows
        ];
    } catch (PDOException $e) {
        error_log("Flight insert error: " . $e->getMessage());
        return [
            "success" => false,
            "message" => "DB insert error: " . $e->getMessage()
        ];
    }
}
function profileUser($profileMessage)
{
    $db = getDB();

    $email = $profileMessage['email'] ?? null;
    $username = $profileMessage['username'] ?? null;

    if (!$email || !$username) {
        return [
            "success" => false,
            "message" => "Missing required fields: email and username are required."
        ];
    }

    $params = [
        ":email" => $email,
        ":username" => $username
    ];

    $stmt = $db->prepare("UPDATE Users SET username = :username WHERE email = :email");

    try {
        $stmt->execute($params);
        return [
            "success" => true,
            "message" => "Profile updated successfully."
        ];
    } catch (PDOException $e) {
        if ($e->errorInfo[1] === 1062) {
            preg_match("/Users.(\w+)/", $e->errorInfo[2], $matches);
            $field = $matches[1] ?? "field";
            return [
                "success" => false,
                "message" => "The chosen {$field} is already in use."
            ];
        }
        error_log("Profile update error: " . var_export($e->errorInfo, true));
        return [
            "success" => false,
            "message" => "An unexpected error occurred while updating the profile."
        ];
    }
}

function updatePassword($passwordMessage)
{
    $db = getDB();

    $email = $passwordMessage['email'] ?? null;
    $currentPassword = $passwordMessage['current_password'] ?? null;
    $newPassword = $passwordMessage['new_password'] ?? null;

    if (!$email || !$currentPassword || !$newPassword) {
        return [
            "success" => false,
            "message" => "Missing required fields: email, current password, and new password."
        ];
    }

    try {
        $stmt = $db->prepare("SELECT password FROM Users WHERE email = :email");
        $stmt->execute([":email" => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($currentPassword, $row["password"])) {
            return [
                "success" => false,
                "message" => "Current password is incorrect."
            ];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE Users SET password = :password WHERE email = :email");
        $stmt->execute([
            ":password" => $newHash,
            ":email" => $email
        ]);

        return [
            "success" => true,
            "message" => "Password updated successfully."
        ];
    } catch (PDOException $e) {
        error_log("Password update error: " . var_export($e->errorInfo, true));
        return [
            "success" => false,
            "message" => "An unexpected error occurred while updating the password."
        ];
    }
}



function createUser($message)
{
    $username = $message['username'] ??'';
    $email = $message['email'] ?? '';
    $password = $message['password'] ?? '';


     $db = getDB();
        if (!$db) {
            $response['message'] = "Database connection failed.";
            return $response;
        }

    $stmt = $db->prepare("INSERT INTO Users (email, password, username) VALUES(:email, :password, :username)");
    try {
        $stmt->execute([
            ":email" => $email,
            ":password" => $password,
            ":username" => $username
        ]);
        return [
            "success" => true,
            "message" => "User registered"
        ];
    } catch (PDOException $e) {
        return [
            "success" => false,
            "message" => "Duplicate or DB error: " . $e->getMessage()
        ];
    }
}

function loginUser($message)
{
    $response = [
        "success" => false, //error here??
        "message" => "Login failed"
    ];

    $email = $message['user'] ?? '';
    $password = $message['pass'] ?? '';
    

    try {
        $db = getDB();

        if (!$db) {
            $response['message'] = "Database connection failed.";
            return $response;

            
        }

        $stmt = $db->prepare("SELECT id, email, username, password FROM Users WHERE email = :email OR username = :email");
        error_log("Executing SQL with email: " . $email);
	$stmt->execute([":email" => $email]);
	
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
	error_log("[DEBUG] USER DATA FETCHED" . var_export($user,true));
        if (!$user) {
            $response['success'] = false;
            $response['message'] = "User not found.";
            return $response;
        }

        error_log("[DEBUG] Supplied password: " . $password);
        error_log("[DEBUG] DB password: " . $user['password']);
        
        if (!password_verify($password, $user['password'])) {
            $response['success'] = false;
            $response['message'] = "Invalid password.";
            return $response;
}

/*if ($password !== $user['password']) {
    $response['success'] = false;
    $response['message'] = "Invalid password.";
    return $response;
}
        
*/
        $stmt = $db->prepare("
            SELECT Roles.name 
            FROM Roles 
            JOIN UserRoles ON Roles.id = UserRoles.role_id 
            WHERE UserRoles.user_id = :user_id 
              AND Roles.is_active = 1 
              AND UserRoles.is_active = 1
        ");
	
        $stmt->execute([":user_id" => $user["id"]]);

        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($roles)) {
            $roles = [];
        }

        unset($user['password']);

        $response["success"] = true;
        $response["user"] = $user;
        $response["roles"] = $roles;
        $response["message"] = "Login successful.";

    } catch (Exception $e) {
        error_log("Auth error: " . $e->getMessage());
        $response["message"] = "Internal error during login.";
    }

    return $response;
}

function adminsearchUsers(array $message): array
{
    try {
        $db = getDB();
        $filters = $message['filters'] ?? [];
        $sort = $message['sort'] ?? [];
        $limit = isset($message['limit']) ? (int)$message['limit'] : 10;

        
        $sql = "
            SELECT
              id,
              username,
              email,
              created
            FROM Users
            WHERE 1=1
        ";
        $params = [];

        
        if (!empty($filters['name'])) {
            $sql .= " AND username LIKE :uname";
            $params[':uname'] = '%' . $filters['name'] . '%';
        }

       
        if (!empty($filters['email'])) {
            $sql .= " AND email LIKE :email";
            $params[':email'] = '%' . $filters['email'] . '%';
        }

        
        $colMap = [
            'username' => 'username',
            'email'    => 'email',
            'created'  => 'created',
        ];
        $colKey = $sort['column'] ?? 'username';
        $col = $colMap[$colKey] ?? 'username';
        $dir = (strtolower($sort['order'] ?? '') === 'desc') ? 'DESC' : 'ASC';
        $sql .= " ORDER BY `$col` $dir";

     
        $limit = max(10, min($limit, 100));
        $sql .= " LIMIT $limit";

        $st = $db->prepare($sql);
        $st->execute($params);
        $results = $st->fetchAll(PDO::FETCH_ASSOC);
        
    
        foreach ($results as &$row) {
            $row['id'] = (int)$row['id'];
        }
        
        return $results;
        
    } catch (Throwable $e) {
        error_log("adminsearchUsers error: " . $e->getMessage());
        return [];
    }
}
function adminupdateUser(array $msg): array
{
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $id = (int)($msg['id'] ?? $msg['user_id'] ?? 0);
        if ($id <= 0) {
            return ["success" => false, "message" => "User ID is required."];
        }

        $checkStmt = $db->prepare("SELECT id FROM Users WHERE id = :id");
        $checkStmt->execute([":id" => $id]);
        if (!$checkStmt->fetch()) {
            return ["success" => false, "message" => "User not found."];
        }

        
        $set = [];
        $params = [":id" => $id];

        if (array_key_exists('email', $msg)) {
            $email = trim((string)$msg['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ["success" => false, "message" => "Invalid email format."];
            }
            $set[] = "`email` = :email";
            $params[":email"] = $email;
        }

        if (array_key_exists('username', $msg)) {
            $username = trim((string)$msg['username']);
            if (strlen($username) < 3 || strlen($username) > 30) {
                return ["success" => false, "message" => "Username must be 3-30 characters."];
            }
            $set[] = "`username` = :username";
            $params[":username"] = $username;
        }

        if (array_key_exists('password', $msg)) {
            $password = (string)$msg['password'];
            if (strlen($password) < 6) {
                return ["success" => false, "message" => "Password must be at least 6 characters."];
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                return ["success" => false, "message" => "Failed to hash password."];
            }
            $set[] = "`password` = :password";
            $params[":password"] = $hash;
        }

        if (!$set) {
            return ["success" => false, "message" => "No changes provided."];
        }
        
        $sql = "UPDATE `Users` SET " . implode(", ", $set) . " WHERE `id` = :id";
        $st = $db->prepare($sql);
        $st->execute($params);
        
        $affected = $st->rowCount();

        
        $st = $db->prepare("SELECT `id`, `username`, `email`, `created` FROM `Users` WHERE `id` = :id LIMIT 1");
        $st->execute([":id" => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ["success" => false, "message" => "User not found after update."];
        }

        return [
            "success" => true,
            "message" => "User updated successfully.",
            "affected_rows" => $affected,
            "user" => $row
        ];
        
    } catch (PDOException $e) {
        error_log("adminupdateUser PDO error: " . $e->getMessage());
        
        // Check for duplicate key errors
        if ($e->getCode() == 23000) {
            if (strpos($e->getMessage(), 'email') !== false) {
                return ["success" => false, "message" => "Email already exists."];
            } elseif (strpos($e->getMessage(), 'username') !== false) {
                return ["success" => false, "message" => "Username already exists."];
            }
            return ["success" => false, "message" => "Duplicate value detected."];
        }
        
        return ["success" => false, "message" => "Database error occurred."];
    } catch (Throwable $e) {
        error_log("adminupdateUser general error: " . $e->getMessage());
        return ["success" => false, "message" => "Error updating user."];
    }
}





function searchFlights(array $message): array
{
    try {
        $db      = getDB();
        $filters = $message['filters'] ?? [];
        $sort    = $message['sort']    ?? [];
        $limit   = isset($message['limit']) ? (int)$message['limit'] : 10;

        // Base query
        $sql = "
          SELECT
            id,
            flight_number,
            airline,
            origin_icao,
            destination_icao,
            departure_time,
            arrival_time,
            real_time_status,
            aircraft_model,
            seat_capacity,
            registration_date,
            aircraft_age
          FROM Flights
          WHERE 1=1
        ";
        $params = [];

        // Apply filters
        if (!empty($filters['name'])) {
            $sql .= " AND flight_number LIKE :flight_number";
            $params[':flight_number'] = "%" . $filters['name'] . "%";
        }
        if (!empty($filters['originicao'])) {
            $sql .= " AND origin_icao LIKE :origin_icao";
            $params[':origin_icao'] = "%" . $filters['originicao'] . "%";
        }
        if (!empty($filters['airline'])) {
            $sql .= " AND airline LIKE :airline";
            $params[':airline'] = "%" . $filters['airline'] . "%";
        }
       if (isset($filters['rating']) && $filters['rating'] !== "" && (int)$filters['rating'] > 0) {
            $sql .= " AND rating >= :rating";
            $params[':rating'] = (int)$filters['rating'];
        }
        
        
        $colMap = [
            'name'       => 'flight_number',
            'originicao' => 'origin_icao',
            'airline'    => 'airline',
            'rating'     => 'rating'
        ];
        $colKey = $sort['column'] ?? 'name';
        $col    = $colMap[$colKey] ?? 'flight_number';
        $dir    = (strtolower($sort['order'] ?? '') === 'desc') ? 'DESC' : 'ASC';
        $sql   .= " ORDER BY `$col` $dir";

        
        $limit = max(1, min($limit, 100));
        $sql   .= " LIMIT $limit";

        // Execute and fetch
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("searchFlights DB error: " . $e->getMessage());
        return [];
    } catch (Exception $e) {
        error_log("searchFlights error: " . $e->getMessage());
        return [];
    }
}

function getFlight($message){
    try {
        $db = getDB();

        // Validate and pull the flight ID
        $flightId = isset($message['id']) ? (int)$message['id'] : 0;
        if ($flightId <= 0) {
            return [];
        }

        // Query: join Flights to Airports twice for origin & destination details
        $sql = "
          SELECT
            f.id,
            f.flight_number,
            f.airline,
            f.departure_time,
            f.arrival_time,
            f.real_time_status,
            f.is_round_trip,
            f.aircraft_model,
            f.seat_capacity,
            f.registration_date,
            f.aircraft_age,
            f.aircraft_registration,
            f.aircraft_image_url,

            ao.icao   AS origin_icao,
            ao.name   AS origin_name,
            ao.city   AS origin_city,
            ao.country AS origin_country,

            ad.icao   AS dest_icao,
            ad.name   AS dest_name,
            ad.city   AS dest_city,
            ad.country AS dest_country

          FROM Flights AS f
          LEFT JOIN Airports AS ao
            ON f.origin_icao = ao.icao
          LEFT JOIN Airports AS ad
            ON f.destination_icao = ad.icao
          WHERE f.id = :id
          LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $flightId]);

        $flight = $stmt->fetch(PDO::FETCH_ASSOC);
        return $flight ?: [];

    } catch (PDOException $e) {
        error_log("getFlight DB error: " . $e->getMessage());
        return [];
    } catch (Exception $e) {
        error_log("getFlight error: " . $e->getMessage());
        return [];
    }
}


function adminsearchFlights(array $message): array
{
    try {
        $db      = getDB();
        $filters = $message['filters'] ?? [];
        $sort    = $message['sort']    ?? [];
        $limit   = isset($message['limit']) ? (int)$message['limit'] : 10;

        $sql = "
          SELECT
            id,
            flight_number           AS name,
            airline,
            origin_icao             AS airport,
            destination_icao        AS destination,
            departure_time,
            arrival_time,
            real_time_status        AS status,
            aircraft_model          AS model,
            seat_capacity           AS seats,
            registration_date,
            aircraft_age            AS aircraft_age_years,
            aircraft_registration,
            aircraft_image_url
          FROM Flights
          WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['name'])) {
            $sql .= " AND flight_number LIKE :name";
            $params[':name'] = '%' . $filters['name'] . '%';
        }
        if (!empty($filters['airline'])) {
            $sql .= " AND airline LIKE :airline";
            $params[':airline'] = '%' . $filters['airline'] . '%';
        }
        if (!empty($filters['originicao'])) {
            $sql .= " AND origin_icao LIKE :origin";
            $params[':origin'] = '%' . $filters['originicao'] . '%';
        }
        if (!empty($filters['destinationicao'])) {
            $sql .= " AND destination_icao LIKE :dest";
            $params[':dest'] = '%' . $filters['destinationicao'] . '%';
        }
        if (!empty($filters['model'])) {
            $sql .= " AND aircraft_model LIKE :model";
            $params[':model'] = '%' . $filters['model'] . '%';
        }
        if (!empty($filters['status'])) {
            $sql .= " AND real_time_status LIKE :status";
            $params[':status'] = '%' . $filters['status'] . '%';
        }

        $colMap = [
            'departure_time'    => 'departure_time',
            'airline'           => 'airline',
            'flight_number'     => 'flight_number',
            'origin_icao'       => 'origin_icao',
            'destination_icao'  => 'destination_icao',
            'registration_date' => 'registration_date'
        ];
        $colKey = $sort['column'] ?? 'departure_time';
        $col    = $colMap[$colKey] ?? 'departure_time';
        $dir    = (strtolower($sort['order'] ?? '') === 'desc') ? 'DESC' : 'ASC';
        $sql   .= " ORDER BY `$col` $dir";

        $limit = max(10, min($limit, 100));
        $sql  .= " LIMIT $limit";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            if ($r['aircraft_age_years'] === null || $r['aircraft_age_years'] === '') {
                if (!empty($r['registration_date'])) {
                    $ts = strtotime($r['registration_date']);
                    if ($ts) {
                        $r['aircraft_age_years'] = (int) floor((time() - $ts) / (365.25 * 86400));
                    }
                }
            }
            $r['summary'] = trim(
                ($r['airline'] ? $r['airline'] . ' ' : '') .
                ($r['name'] ? $r['name'] . ' ' : '') .
                (($r['model'] ?? '') ? '— ' . $r['model'] . ' ' : '') .
                ($r['airport'] ? '@' . $r['airport'] : '') .
                (!empty($r['destination']) ? ' → ' . $r['destination'] : '') .
                (isset($r['departure_time']) ? ' ' . $r['departure_time'] : '')
            );
        }
        unset($r);

        return $rows;

    } catch (PDOException $e) {
        error_log("adminsearchFlights DB error: " . $e->getMessage());
        return [];
    } catch (Throwable $e) {
        error_log("adminsearchFlights error: " . $e->getMessage());
        return [];
    }
}




function getFlightById($flightId) {
    $db = getDB();
    
    if (!$flightId) {
        return ["success" => false, "message" => "Flight ID is required"];
    }
    
    $stmt = $db->prepare("
        SELECT 
            id,
            flight_number,
            airline,
            origin_icao,
            destination_icao,
            departure_time,
            arrival_time,
            real_time_status,
            aircraft_model,
            aircraft_registration,
            aircraft_image_url,
            is_round_trip,
            seat_capacity,
            registration_date,
            aircraft_age
        FROM Flights 
        WHERE id = :flight_id
    ");
    
    try {
        $stmt->execute([':flight_id' => $flightId]);
        $flight = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($flight) {
            return ["success" => true, "flight" => $flight];
        } else {
            return ["success" => false, "message" => "Flight not found"];
        }
    } catch (PDOException $e) {
        error_log("Get flight error: " . $e->getMessage());
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}





function getFlightsByIds2($ids) {
    $db = getDB();
    $in  = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $db->prepare("SELECT * FROM Flights WHERE id IN ($in)");
    $stmt->execute($ids);
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("🧪 getFlightsByIds2 received IDs: " . json_encode($ids));
    error_log("🧪 Found flights: " . json_encode($flights));

    return [
        "success" => true,
        "flights" => $flights
    ];
}

function getadminFlight(array $message): array
{
    try {
        $db = getDB();

        // Accept both 'id' and 'flight_id'
        $id = isset($message['id']) ? (int)$message['id'] : (int)($message['flight_id'] ?? 0);
        if ($id <= 0) {
            return ["success" => false, "message" => "Flight ID is required"];
        }

        // Flight + origin/destination airport info
        $sql = "
          SELECT
            f.id,
            f.flight_number,
            f.airline,
            f.origin_icao,
            f.destination_icao,
            f.departure_time,
            f.arrival_time,
            f.real_time_status,
            f.aircraft_model,
            f.aircraft_registration,
            f.aircraft_image_url,
            f.is_round_trip,
            f.seat_capacity,
            f.registration_date,
            f.aircraft_age,

            ao.name      AS origin_name,
            ao.city      AS origin_city,
            ao.country   AS origin_country,
            ao.latitude  AS origin_lat,
            ao.longitude AS origin_lon,

            ad.name      AS dest_name,
            ad.city      AS dest_city,
            ad.country   AS dest_country,
            ad.latitude  AS dest_lat,
            ad.longitude AS dest_lon

          FROM Flights f
          LEFT JOIN Airports ao ON f.origin_icao      = ao.icao
          LEFT JOIN Airports ad ON f.destination_icao = ad.icao
          WHERE f.id = :id
          LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ["success" => false, "message" => "Flight not found"];
        }

        
        $row['distance_km'] = null;
        $row['distance_nm'] = null;
        if ($row['origin_lat'] !== null && $row['origin_lon'] !== null && $row['dest_lat'] !== null && $row['dest_lon'] !== null) {
            $lat1 = (float)$row['origin_lat'];
            $lon1 = (float)$row['origin_lon'];
            $lat2 = (float)$row['dest_lat'];
            $lon2 = (float)$row['dest_lon'];

            $toRad = M_PI / 180;
            $dLat  = ($lat2 - $lat1) * $toRad;
            $dLon  = ($lon2 - $lon1) * $toRad;
            $a = sin($dLat/2)**2 + cos($lat1*$toRad) * cos($lat2*$toRad) * sin($dLon/2)**2;
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $km = 6371.0 * $c;

            $row['distance_km'] = round($km, 1);
            $row['distance_nm'] = round($km / 1.852, 1);
        }

    
        $row['flight_time_minutes'] = null;
        if (!empty($row['departure_time']) && !empty($row['arrival_time'])) {
            try {
                $dep = new DateTime($row['departure_time']);
                $arr = new DateTime($row['arrival_time']);
                if ($arr < $dep) { $arr->modify('+1 day'); } // simple overnight case
                $mins = (int) round(($arr->getTimestamp() - $dep->getTimestamp()) / 60);
                if ($mins >= 0) {
                    $row['flight_time_minutes'] = $mins;
                }
            } catch (Throwable $e) {
             
            }
        }

   
        if ($row['aircraft_age'] === null || $row['aircraft_age'] === '' || $row['aircraft_age'] === 0) {
            if (!empty($row['registration_date'])) {
                $ts = strtotime($row['registration_date']);
                if ($ts) {
                    $row['aircraft_age'] = (int) floor((time() - $ts) / (365.25 * 86400));
                }
            }
        }
        $row['aircraft_age_years'] = ($row['aircraft_age'] !== null && $row['aircraft_age'] !== '') ? (int)$row['aircraft_age'] : null;

        
        $row['name']       = $row['flight_number'];        // display label
        $row['airport']    = $row['origin_icao'];          // origin for summary
        $row['destination']= $row['destination_icao'];     // convenience
        $row['model']      = $row['aircraft_model'];
        $row['seats']      = $row['seat_capacity'];
        $row['status']     = $row['real_time_status'];

        
        if (!array_key_exists('aircraft_brand', $row)) { $row['aircraft_brand'] = null; }
        if (!array_key_exists('aircraft_name',  $row)) { $row['aircraft_name']  = null; }

        
        if (isset($row['seat_capacity']))   { $row['seat_capacity'] = (int)$row['seat_capacity']; }
        if (isset($row['is_round_trip']))   { $row['is_round_trip'] = (int)$row['is_round_trip']; }
        if (isset($row['aircraft_age']))    { $row['aircraft_age']  = (int)$row['aircraft_age']; }

        return ["success" => true, "flight" => $row];

    } catch (PDOException $e) {
        error_log("getadminFlight DB error: " . $e->getMessage());
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    } catch (Throwable $e) {
        error_log("getadminFlight error: " . $e->getMessage());
        return ["success" => false, "message" => "Internal error: " . $e->getMessage()];
    }
}



function admindeleteFlight(array $message): array
{
    $db = null;
    try {
        $db = getDB();

        // accept id or flight_id
        $fid = (int)($message['flight_id'] ?? $message['id'] ?? 0);
        if ($fid <= 0) {
            return ["success" => false, "status" => "bad_request", "message" => "Flight ID is required"];
        }

        $db->beginTransaction();

      
        $deletedChildren = 0;
        try {
            $stmt = $db->prepare("DELETE FROM `user_flights` WHERE `flight_id` = :fid");
            $stmt->execute([":fid" => $fid]);
            $deletedChildren = $stmt->rowCount();
        } catch (PDOException $e) {
       
            error_log("admindeleteFlight note: child delete skipped ({$e->getMessage()})");
        }

        // delete the flight
        $stmt = $db->prepare("DELETE FROM `Flights` WHERE `id` = :fid LIMIT 1");
        $stmt->execute([":fid" => $fid]);

        if ($stmt->rowCount() < 1) {
            $db->rollBack();
            return ["success" => false, "status" => "not_found", "message" => "Flight not found"];
        }

        $db->commit();
        return [
            "success" => true,
            "status"  => "ok",
            "message" => "Flight deleted",
            "deleted" => 1,
            "deleted_children" => $deletedChildren
        ];

    } catch (PDOException $e) {
        if ($db && $db->inTransaction()) { $db->rollBack(); }
        error_log("admindeleteFlight DB error: " . $e->getMessage());
        return ["success" => false, "status" => "error", "message" => "Database error"];
    } catch (Throwable $e) {
        if ($db && $db->inTransaction()) { $db->rollBack(); }
        error_log("admindeleteFlight error: " . $e->getMessage());
        return ["success" => false, "status" => "error", "message" => "Internal error"];
    }
}



function updateFlight($flightData) {
    $db = getDB();
    
    $flightId = $flightData['id'] ?? null;
    if (!$flightId) {
        return ["success" => false, "message" => "Flight ID is required"];
    }
    
    $stmt = $db->prepare("
        UPDATE Flights SET
            flight_number = :flight_number,
            airline = :airline,
            departure_time = :departure_time,
            arrival_time = :arrival_time,
            real_time_status = :real_time_status,
            aircraft_registration = :aircraft_registration
        WHERE id = :flight_id
    ");
    
    try {
        $stmt->execute([
            ':flight_id' => $flightId,
            ':flight_number' => $flightData['FlightNumber'] ?? $flightData['flight_number'],
            ':airline' => $flightData['Airline'] ?? $flightData['airline'],
            ':departure_time' => $flightData['DepartureTime'] ?? $flightData['departure_time'],
            ':arrival_time' => $flightData['ArrivalTime'] ?? $flightData['arrival_time'],
            ':real_time_status' => $flightData['RealTimeStatus'] ?? $flightData['real_time_status'],
            ':aircraft_registration' => $flightData['Registration'] ?? $flightData['aircraft_registration']
        ]);
        
        if ($stmt->rowCount() > 0) {
            return ["success" => true, "message" => "Flight updated successfully"];
        } else {
            return ["success" => false, "message" => "No changes made or flight not found"];
        }
    } catch (PDOException $e) {
        error_log("Flight update error: " . $e->getMessage());
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}


function getAllFlights($limit = 50) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT 
            id,
            flight_number,
            airline,
            origin_icao,
            destination_icao,
            departure_time,
            arrival_time,
            real_time_status
        FROM Flights 
        ORDER BY departure_time DESC
        LIMIT :limit
    ");
    
    try {
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ["success" => true, "flights" => $flights];
    } catch (PDOException $e) {
        error_log("Get all flights error: " . $e->getMessage());
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}

function deleteFlight($flightId) {
    $db = getDB();
    
    if (!$flightId) {
        return ["success" => false, "message" => "Flight ID is required"];
    }
    
    $stmt = $db->prepare("DELETE FROM Flights WHERE id = :flight_id");
    
    try {
        $stmt->execute([':flight_id' => $flightId]);
        
        if ($stmt->rowCount() > 0) {
            return ["success" => true, "message" => "Flight deleted successfully"];
        } else {
            return ["success" => false, "message" => "Flight not found"];
        }
    } catch (PDOException $e) {
        error_log("Flight delete error: " . $e->getMessage());
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}
function getUserById($message) {
    $db = getDB(); 
    $userId = $message['id'] ?? $message['user_id'] ?? null;
    
    if (!$userId) {
        return ["success" => false, "message" => "User ID is required"];
    }
    
    $sql = "SELECT 
                u.id,
                u.email,
                u.username,
                u.created,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') as roles
            FROM Users u 
            LEFT JOIN UserRoles ur ON u.id = ur.user_id AND ur.is_active = 1
            LEFT JOIN Roles r ON ur.role_id = r.id AND r.is_active = 1
            WHERE u.id = ?
            GROUP BY u.id, u.email, u.username, u.created";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            if (!empty($user['roles'])) {
                $user['roles'] = explode(',', $user['roles']);
            } else {
                $user['roles'] = [];
            }
            
            // Return in the format the edit page expects
            return $user;
        } else {
            return null;
        }
    } catch (PDOException $e) {
        error_log("getUserById error: " . $e->getMessage());
        return null;
    }
}
