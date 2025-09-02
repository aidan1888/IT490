<?php
require(__DIR__ . "/../../partials/nav.php");
reset_session(); // clear session to prevent login bleed
?>

<form onsubmit="return validate(this)" method="POST">
    <div>
        <label for="email">Email</label>
        <input type="email" name="email" required />
    </div>
    <div>
        <label for="username">Username</label>
        <input type="text" name="username" required maxlength="30" />
    </div>
    <div>
        <label for="pw">Password</label>
        <input type="password" id="pw" name="password" required minlength="8" />
    </div>
    <div>
        <label for="confirm">Confirm</label>
        <input type="password" name="confirm" required minlength="8" />
    </div>
    <input type="submit" value="Register" />
</form>

<script>
    function validate(form) {
        let isValid = true;
        const emailRegex = /^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
        const passwdRegex = /^.{8,}$/;
        const userNameRegex = /^[a-z0-9_-]{3,16}$/;

        if (!emailRegex.test(form.email.value)) {
            flash("[JS] Invalid email format");
            isValid = false;
        }

        if (!userNameRegex.test(form.username.value)) {
            flash("[JS] Invalid username format");
            isValid = false;
        }

        if (!passwdRegex.test(form.password.value)) {
            flash("[JS] Invalid password format");
            isValid = false;
        }

        if (!passwdRegex.test(form.confirm.value)) {
            flash("[JS] Invalid confirm password format");
            isValid = false;
        }

        if (form.password.value !== form.confirm.value) {
            flash("[JS] Passwords do not match");
            isValid = false;
        }

        return isValid;
    }
</script>

<?php
function registerToMQ($email, $username, $password) {
    require_once(__DIR__ . "/../../../IT490/path.inc");
    require_once(__DIR__ . "/../../../IT490/get_host_info.inc");
    require_once(__DIR__ . "/../../../IT490/rabbitMQLib.inc");

    error_reporting(E_ERROR);
    ini_set('display_errors', 1);
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $message = [
        "type" => "register",
        "email" => $email,
        "username" => $username,
        "password" => $password
    ];

    error_log("Sending register MQ request: " . json_encode($message));

    $response = $client->send_request($message);

    // Normalize response
    if (is_string($response)) {
        return ["success" => false, "message" => $response];
    }
    if (is_object($response)) {
        $response = json_decode(json_encode($response), true);
    }

    return $response;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = se($_POST, "email", "", false);
    $username = se($_POST, "username", "", false);
    $password = se($_POST, "password", "", false);
    $confirm = se($_POST, "confirm", "", false);

    $hasError = false;

    if (empty($email)) {
        flash("Email must not be empty", "danger");
        $hasError = true;
    }

    $email = sanitize_email($email);

    if (!is_valid_email($email)) {
        flash("Invalid email address", "danger");
        $hasError = true;
    }

    if (!preg_match('/^[a-z0-9_-]{3,16}$/', $username)) {
        flash("Username must only contain 3-16 characters: a-z, 0-9, _, or -", "danger");
        $hasError = true;
    }

    if (empty($password) || empty($confirm)) {
        flash("Password and Confirm Password must not be empty", "danger");
        $hasError = true;
    }

    if (strlen($password) < 8) {
        flash("Password too short", "danger");
        $hasError = true;
    }

    if ($password !== $confirm) {
        flash("Passwords must match", "danger");
        $hasError = true;
    }

    if (!$hasError) {
           $hash = password_hash($password, PASSWORD_BCRYPT);
        $result = registerToMQ($email, $username, $hash);

        if (isset($result["success"]) && $result["success"]) {
            flash("Successfully registered!", "success");
            header("Location: login.php");
            exit;
        } else {
            flash($result["message"] ?? "Registration failed", "danger");
        }
    }
}
?>

<?php require(__DIR__ . "/../../partials/flash.php"); ?>
