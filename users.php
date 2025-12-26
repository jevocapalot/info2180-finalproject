<?php
session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['name'] ?? '';

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
            } else {
                // Most likely duplicate email
                $error = "Failed to add user. Email may already be taken.";
            }
        }
    }
}

// Get all users for listing
$userSql = "SELECT id, firstname, lastname, email, role, created_at FROM users ORDER BY created_at DESC";
$userResult = $conn->query($userSql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dolphin CRM - Users</title>
    <link rel="stylesheet" href="css/style.css">
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
            <a href="dashboard.php"><span class="icon">🏠</span> Home</a>
            <a href="new_contact.php"><span class="icon">➕</span> New Contact</a>
            <a href="users.php" class="active-nav"><span class="icon">👥</span> Users</a>
            <a href="logout.php"><span class="icon">⤴</span> Logout</a>
        </nav>

    </aside>

    <main class="main">
        <div class="main-header">
            <h2>Users</h2>
            <!-- Optional: you already have add-user form below -->
        </div>

        <section class="card">
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($userResult && $userResult->num_rows > 0): ?>
                    <?php while ($u = $userResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['firstname'].' '.$u['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['role']); ?></td>
                            <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="card" style="margin-top:18px;">
            <h3 style="margin-bottom:8px;">Add New User</h3>

            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <form method="POST" action="users.php">
                <div class="form-grid-2">
                    <div>
                        <label>First Name
                            <input type="text" name="firstname" required>
                        </label>
                    </div>
                    <div>
                        <label>Last Name
                            <input type="text" name="lastname" required>
                        </label>
                    </div>

                    <div>
                        <label>Email
                            <input type="email" name="email" required>
                        </label>
                    </div>
                    <div>
                        <label>Password
                            <input type="password" name="password" required>
                        </label>
                    </div>

                    <div>
                        <label>Role
                            <select name="role" required>
                                <option value="">-- Select Role --</option>
                                <option value="Admin">Admin</option>
                                <option value="Member">Member</option>
                            </select>
                        </label>
                    </div>
                </div>

                <button type="submit" style="margin-top:16px;">Save User</button>
            </form>
        </section>
    </main>
</div>
<script src="app.js"></script>
</body>
</html>
