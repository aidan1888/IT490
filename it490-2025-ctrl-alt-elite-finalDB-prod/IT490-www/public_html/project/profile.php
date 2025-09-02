<?php
require_once(__DIR__ . "/../../lib/functions.php");
require_once(__DIR__ . "/../../partials/nav.php");
is_logged_in(true);

require_once(__DIR__ . "/../../../IT490/path.inc");
require_once(__DIR__ . "/../../../IT490/get_host_info.inc");
require_once(__DIR__ . "/../../../IT490/rabbitMQLib.inc");

error_reporting(E_ERROR);
ini_set('display_errors', 1);

function sendMQRequest($message) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    return $client->send_request($message);
}

if (isset($_POST["save"])) {
    $email = se($_POST, "email", null, false);
    $username = se($_POST, "username", null, false);

    $hasError = false;

    // Basic validation
    if (empty($email) || empty($username)) {
        flash("Email and username cannot be empty", "danger");
        $hasError = true;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash("Invalid email address", "danger");
        $hasError = true;
    }

    if (!preg_match('/^[a-z0-9_-]{3,16}$/', $username)) {
        flash("Username must be 3-16 chars: a-z, 0-9, _ or -", "danger");
        $hasError = true;
    }

    // Update profile via MQ
    if (!$hasError) {
        $profileMessage = [
            "type" => "update_profile",
            "email" => $email,
            "username" => $username
        ];

        $response = sendMQRequest($profileMessage);

        if ($response["success"]) {
            $_SESSION["user"]["email"] = $email;
            $_SESSION["user"]["username"] = $username;
            flash($response["message"], "success");
        } else {
            flash($response["message"], "danger");
        }
    }

    // Password update
    $current_password = se($_POST, "currentPassword", null, false);
    $new_password = se($_POST, "newPassword", null, false);
    $confirm_password = se($_POST, "confirmPassword", null, false);

    if (!$hasError && !empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            flash("New passwords do not match", "warning");
        } else {
            $passwordMessage = [
                "type" => "update_password",
                "email" => $email,
                "current_password" => $current_password,
                "new_password" => $new_password,
            ];

            $passResponse = sendMQRequest($passwordMessage);

            if ($passResponse["success"]) {
                flash($passResponse["message"], "success");
            } else {
                flash($passResponse["message"], "danger");
            }
        }
    }
}

$email = get_user_email();
$username = get_username();
?>

<form method="POST" onsubmit="return validate(this);">
    <div class="mb-3">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="<?php se($email); ?>" required />
    </div>
    <div class="mb-3">
        <label for="username">Username</label>
        <input type="text" name="username" id="username" value="<?php se($username); ?>" required />
    </div>

    <hr />
    <h5>Password Reset (Optional)</h5>
    <div class="mb-3">
        <label for="cp">Current Password</label>
        <input type="password" name="currentPassword" id="cp" />
    </div>
    <div class="mb-3">
        <label for="np">New Password</label>
        <input type="password" name="newPassword" id="np" />
    </div>
    <div class="mb-3">
        <label for="conp">Confirm New Password</label>
        <input type="password" name="confirmPassword" id="conp" />
    </div>

    <input type="submit" value="Update Profile" name="save" class="btn btn-primary" />
</form>

<script>


function validate(form) {
    let isValid = true;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
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

    let current = form.currentPassword.value;
    let newPass = form.newPassword.value;
    let confirm = form.confirmPassword.value;

    if (current || newPass || confirm) {
        if (!passwdRegex.test(current)) {
            flash("[JS] Invalid current password format (min 8 chars)");
            isValid = false;
        }
        if (!passwdRegex.test(newPass)) {
            flash("[JS] Invalid new password format (min 8 chars)");
            isValid = false;
        }
        if (!passwdRegex.test(confirm)) {
            flash("[JS] Invalid confirm password format (min 8 chars)");
            isValid = false;
        }
        if (newPass !== confirm) {
            flash("New password and confirmation do not match");
            isValid = false;
        }
    }

    return isValid;
}
</script>

<?php require_once(__DIR__ . "/../../partials/flash.php"); ?>
