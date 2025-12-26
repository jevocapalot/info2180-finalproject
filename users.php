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
    <title>Users - Dolphin CRM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        nav a { margin-right: 10px; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f4f4f4; text-align: left; }
        form { max-width: 500px; margin-top: 20px; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 6px; margin-top: 4px; }
        button { margin-top: 15px; padding: 8px 16px; }
        .error { color: red; margin-top: 10px; }
        .success { color: green; margin-top: 10px; }
    </style>
</head>
<body>

<header>
    <div>
        <h1>Dolphin CRM</h1>
        <p>Logged in as <?php echo htmlspecialchars($user_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</p>
    </div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="new_contact.php">New Contact</a>
        <a href="users.php">Users</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<section>
    <h2>Users</h2>

    <table>
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($userResult && $userResult->num_rows > 0): ?>
            <?php while ($u = $userResult->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['firstname'] . ' ' . $u['lastname']); ?></td>
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

<section>
    <h2>Add New User</h2>

    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <form method="POST" action="users.php">
        <label>First Name
            <input type="text" name="firstname" required>
        </label>

        <label>Last Name
            <input type="text" name="lastname" required>
        </label>

        <label>Email
            <input type="email" name="email" required>
        </label>

        <label>Password
            <input type="password" name="password" required>
        </label>
        <small>
            Password must be at least 8 characters, with upper & lowercase letters and a number.
        </small>

        <label>Role
            <select name="role" required>
                <option value="">-- Select Role --</option>
                <option value="Admin">Admin</option>
                <option value="Member">Member</option>
            </select>
        </label>

        <button type="submit">Create User</button>
    </form>
</section>

</body>
</html>
