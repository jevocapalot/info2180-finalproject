<?php
session_start();
require 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["name"] = $user["firstname"] . " " . $user["lastname"];

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dolphin CRM - Login</title>
</head>
<body>

<h2>Login</h2>

<form method="POST">
    <label>Email</label>
    <input type="email" name="email" required><br>

    <label>Password</label>
    <input type="password" name="password" required><br>

    <button type="submit">Login</button>
</form>

<p style="color:red;"><?php echo $error; ?></p>

</body>
</html>
