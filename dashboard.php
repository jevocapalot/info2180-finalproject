<?php
session_start();
require 'config.php';

// If user is not logged in, send them back to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? '';
$user_role = $_SESSION['role'] ?? '';

$filter = $_GET['filter'] ?? 'all';

// Base query: join contacts with users to show "Assigned To" name
$sql = "
    SELECT c.id, c.title, c.firstname, c.lastname,
           c.email, c.company, c.type,
           u.firstname AS assigned_firstname,
           u.lastname  AS assigned_lastname
    FROM contacts c
    LEFT JOIN users u ON c.assigned_to = u.id
";

// Add WHERE clause based on filter
$params = [];
$types  = "";

if ($filter === 'sales') {
    $sql .= " WHERE c.type = ?";
    $params[] = 'Sales Lead';
    $types   .= "s";
} elseif ($filter === 'support') {
    $sql .= " WHERE c.type = ?";
    $params[] = 'Support';
    $types   .= "s";
} elseif ($filter === 'assigned') {
    $sql .= " WHERE c.assigned_to = ?";
    $params[] = $user_id;
    $types   .= "i";
}

$sql .= " ORDER BY c.lastname, c.firstname";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dolphin CRM - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
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
            <a href="dashboard.php" class="active-nav"><span class="icon">🏠</span> Home</a>
            <a href="new_contact.php"><span class="icon">➕</span> New Contact</a>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <a href="users.php"><span class="icon">👥</span> Users</a>
            <?php endif; ?>
            <a href="logout.php"><span class="icon">⤴</span> Logout</a>
        </nav>
    </aside>

    <main class="main">
        <div class="main-header">
            <h2>Dashboard</h2>
            <a href="new_contact.php" class="button">+ Add Contact</a>
        </div>

        <section class="card">
            <div class="filters">
                <span>Filter by:</span>
                <div class="filter-links">
                    <a href="dashboard.php?filter=all"
                       class="<?php echo ($filter === 'all') ? 'active-filter' : ''; ?>">All</a>
                    <a href="dashboard.php?filter=sales"
                       class="<?php echo ($filter === 'sales') ? 'active-filter' : ''; ?>">Sales Leads</a>
                    <a href="dashboard.php?filter=support"
                       class="<?php echo ($filter === 'support') ? 'active-filter' : ''; ?>">Support</a>
                    <a href="dashboard.php?filter=assigned"
                       class="<?php echo ($filter === 'assigned') ? 'active-filter' : ''; ?>">Assigned to me</a>
                </div>
            </div>

            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Type</th>
                    <th>Assigned To</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(trim($row['title'].' '.$row['firstname'].' '.$row['lastname'])); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['company']); ?></td>
                            <td>
                                <?php
                                $type = $row['type'];
                                $badgeClass = $type === 'Support' ? 'badge-support' : 'badge-sales';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo strtoupper($type); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if ($row['assigned_firstname']) {
                                    echo htmlspecialchars($row['assigned_firstname'].' '.$row['assigned_lastname']);
                                } else {
                                    echo 'Unassigned';
                                }
                                ?>
                            </td>
                            <td><a href="contact.php?id=<?php echo $row['id']; ?>">View</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">No contacts found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>
