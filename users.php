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

// Get all users for listing
$userSql = "SELECT id, firstname, lastname, email, role, created_at FROM users ORDER BY created_at DESC";
$userResult = $conn->query($userSql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dolphin CRM - Users</title>
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
            <h2>Users</h2>
            <a href="add_user.php" class="button">+ Add User</a>
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
                            <td><strong><?php echo htmlspecialchars($u['firstname'].' '.$u['lastname']); ?></strong></td>
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
    </main>
</div>
<script src="app.js"></script>
</body>
</html>
