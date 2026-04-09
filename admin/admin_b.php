<?php
// Includes using __DIR__ to go up one folder
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $email = $_POST['email'] ?? "";
    $password = $_POST['password'] ?? "";

    if ($email === "" || $password === "") {
        $_SESSION['login_message'] = "Email and password are required.";
        header("Location: admin_login.php");
        exit;
    }

    try {
        // Initialize Firebase
        $rdb = new firebaseRDB($databaseURL);

        // Retrieve admin data by email
        $retrieve = $rdb->retrieve("/admin", "email", "EQUAL", $email);
        $data = json_decode($retrieve, true);

        // If /admin doesn't exist yet or email not found
        if (!is_array($data) || count($data) === 0) {
            $_SESSION['login_message'] = "Email not registered.";
            header("Location: admin_login.php");
            exit;
        }

        // Get first admin ID
        $id = array_keys($data)[0];

        // Verify password
        if (password_verify($password, $data[$id]['password'])) {
            $_SESSION['admin_id'] = $id;
            $_SESSION['admin_name'] = $data[$id]['name'];
            $_SESSION['admin_email'] = $email;

            // Redirect to admin dashboard
            header("Location: admin_index.php");
            exit;
        } else {
            $_SESSION['login_message'] = "Incorrect password.";
            header("Location: admin_login.php");
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['login_message'] = "Error: " . $e->getMessage();
        header("Location: admin_login.php");
        exit;
    }

} else {
    // Prevent direct access
    header("Location: admin_login.php");
    exit;
}
?>