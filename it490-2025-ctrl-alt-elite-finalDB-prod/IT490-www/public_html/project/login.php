<?php
require_once(__DIR__ . "/../../lib/functions.php");
require_once(__DIR__ . "/../../partials/nav.php");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



function loginToMQ($email, $password) {
    require_once(__DIR__ . "/../../../IT490/path.inc");
    require_once(__DIR__ . "/../../../IT490/get_host_info.inc");
    require_once(__DIR__ . "/../../../IT490/rabbitMQLib.inc");
    error_reporting(E_ERROR);
    ini_set('display_errors', 1);
    $client = null;
    $response = [];

    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

        $message = [
            "type" => "login",
            "user" => $email,
            "pass" => $password
        ];
        error_log("Sending Message Array: " . json_encode($message));
        $result = $client->send_request($message);
        error_log("Sending result Array: " . json_encode($result));

        if (is_object($result)) {
            $result = json_decode(json_encode($result), true);
        }

        if (is_array($result) && isset($result['success'])) {
            $response = $result;
        } else {
            $response = [
                "success" => false,
                "message" => $result['message'] ?? "Invalid response from login service."
            ];
        }
    } catch (Exception $e) {
        error_log("MQ Exception: " . $e->getMessage());
        if ($client !== null) {
            unset($client);
        }
        $response['message'] = "Failed to contact login service.";
    }

    return $response;
}

// POST handling and redirect must come BEFORE any output:
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = se($_POST, "email", "", false);
    $password = se($_POST, "password", "", false);

    $hasError = false;

    if (empty($email)) {
        flash("Email must be provided <br>");
        $hasError = true;
    }

    if (str_contains($email, "@")) {
        $email = sanitize_email($email);
        if (!is_valid_email($email)) {
            flash("Invalid email address");
            $hasError = true;
        }
    } else {
        if (!is_valid_username($email)) {
            flash("Invalid username");
            $hasError = true;
        }
    }

    if (empty($password)) {
        flash("Password must be provided <br>");
        $hasError = true;
    }
    if (strlen($password) < 8) {
        flash("Password must be at least 8 characters long <br>");
        $hasError = true;
    }

    if (!$hasError) {
        $mqResult = loginToMQ($email, $password);
        error_log("DEBUG: MQ result: " . var_export($mqResult, true));
        if (isset($mqResult["success"]) && $mqResult["success"] === true) {
            $user = $mqResult["user"] ?? [];
            unset($user["password"]);
            $_SESSION["user"] = $user;
            $_SESSION["user"]["roles"] = $mqResult["roles"] ?? [];

            flash("Welcome, " . ($_SESSION["user"]["username"] ?? "User"));
            header("Location: home.php");
            exit;
        } else {
            flash($mqResult["message"] ?? "Login failed");
            header("Location: login.php");
            exit;
        }
    }
}
?>

<?php require_once(__DIR__ . "/../../partials/nav.php"); ?>

<form onsubmit="return validate(this)" method="POST">
    <div>
        <label for="email">Email/Username</label>
        <input type="text" name="email" required />
    </div>
    <div>
        <label for="pw">Password</label>
        <input type="password" id="pw" name="password" required minlength="8" />
    </div>
    <input type="submit" value="Login" />
</form>

<script>
function validate(form) {
    const emailRegex = /^([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})*$/;
    const passwdRegex = /^.{8,}$/;
    const userNameRegex = /^[a-z0-9_-]{3,16}$/;
    let isValid = true;

    if ((form.email.value).includes('@')) {
        if (!emailRegex.test(form.email.value) || form.email.value == "") {
            alert("[JS] Invalid email format");
            isValid = false;
        }
    } else {
        if (!userNameRegex.test(form.email.value) || form.email.value == "") {
            alert("[JS] Invalid username format");
            isValid = false;
        }
    }
    if (!passwdRegex.test(form.password.value) || form.password.value == "") {
        alert("[JS] Invalid password format");
        isValid = false;
    }
    return isValid;
}
</script>

<?php require_once(__DIR__ . "/../../partials/flash.php"); ?>
