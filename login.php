

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
    <meta charset="UTF-8">
    <title>Dolphin CRM - Login</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">
    <header class="topbar">
        <div class="topbar-logo">
            <span>🐬</span>
            Dolphin CRM
        </div>
    </header>

    <main class="login-main">
        <div class="login-card">
            <h2>Login</h2>

            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST">
                <label>Email address
                    <input type="email" name="email" required>
                </label>

                <label>Password
                    <input type="password" name="password" required>
                </label>

                <button type="submit">
                    🔐 Login
                </button>
            </form>
        </div>
    </main>

    <footer class="login-footer">
        Copyright © <?php echo date('Y'); ?> Dolphin CRM
    </footer>
</body>
