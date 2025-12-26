<?php
session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? '';

// Only Admin can access this page
if ($user_role !== 'Admin') {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied. Admins only.");
}

$error = "";
$success = "";

// Handle form submit to add user
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? '';

    // Basic validation
    if ($firstname === "" || $lastname === "" || $email === "" || $password === "" || $role === "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!in_array($role, ['Admin', 'Member'])) {
        $error = "Invalid role.";
    } else {
        // Password rules:
        // at least 8 chars, at least one uppercase, one lowercase, one digit
        $passwordErrors = [];

        if (strlen($password) < 8) {
            $passwordErrors[] = "at least 8 characters";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $passwordErrors[] = "at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $passwordErrors[] = "at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $passwordErrors[] = "at least one digit";
        }

        if (!empty($passwordErrors)) {
            $error = "Password must contain " . implode(", ", $passwordErrors) . ".";
        } else {
            // Hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $sql = "INSERT INTO users (firstname, lastname, email, password, role, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $firstname, $lastname, $email, $hashed, $role);

            if ($stmt->execute()) {
                $success = "User added successfully.";
                // Redirect to users list after success? Or stay here?
                // Wireframe doesn't say, but usually redirect to list is good UX.
                header("Location: users.php");
                exit();
            } else {
                // Most likely duplicate email
                $error = "Failed to add user. Email may already be taken.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dolphin CRM - New User</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-logo">
        <span>🐬</span>
        Dolphin CRM
    </div>
</header>

<div class="app">
    <aside class="sidebar">
        <div class="sidebar-user">
            Logged in as <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>
            (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)
        </div>
        <nav>
            <a href="dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="new_contact.php"><i class="fa-solid fa-user-plus"></i> New Contact</a>
            <a href="users.php" class="active-nav"><i class="fa-solid fa-users"></i> Users</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <main class="main">
        <div class="main-header">
            <h2>New User</h2>
        </div>

        <section class="card">
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <form method="POST" action="add_user.php">
                <div class="form-grid-2">
                    <div>
                        <label>First Name</label>
                        <input type="text" name="firstname" placeholder="Jane" required>
                    </div>
                    <div>
                        <label>Last Name</label>
                        <input type="text" name="lastname" placeholder="Doe" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" placeholder="something@example.com" required>
                    </div>
                    <div>
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label>Role</label>
                        <select name="role" required>
                            <option value="Member">Member</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">Save</button>
                </div>
            </form>
        </section>
    </main>
</div>
<script src="app.js"></script>
</body>
</html>
