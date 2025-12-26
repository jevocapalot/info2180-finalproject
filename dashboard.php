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
    <style>
        /* super simple styling, can replace with your CSS later */
        body { font-family: Arial, sans-serif; margin: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        nav a { margin-right: 10px; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f4f4f4; text-align: left; }
        .filters a { margin-right: 10px; }
        .active-filter { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>

<header>
    <div>
        <h1>Dolphin CRM</h1>
        <p>Welcome, <?php echo htmlspecialchars($user_name); ?></p>
    </div>
    <nav>
        <a href="dashboard.php">Home</a>
        <a href="new_contact.php">New Contact</a> <!-- to be built -->
        <a href="users.php">Users</a>             <!-- to be built -->
        <a href="logout.php">Logout</a>           <!-- to be built -->
    </nav>
</header>

<section>
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Contacts</h2>
        <a href="new_contact.php">
            <button type="button">Add New Contact</button>
        </a>
    </div>

    <div class="filters">
        <span>Filter by: </span>
        <a href="dashboard.php?filter=all"
           class="<?php echo ($filter === 'all') ? 'active-filter' : ''; ?>">All Contacts</a>

        <a href="dashboard.php?filter=sales"
           class="<?php echo ($filter === 'sales') ? 'active-filter' : ''; ?>">Sales Leads</a>

        <a href="dashboard.php?filter=support"
           class="<?php echo ($filter === 'support') ? 'active-filter' : ''; ?>">Support</a>

        <a href="dashboard.php?filter=assigned"
           class="<?php echo ($filter === 'assigned') ? 'active-filter' : ''; ?>">Assigned to me</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Title & Name</th>
                <th>Email</th>
                <th>Company</th>
                <th>Type</th>
                <th>Assigned To</th>
                <th>View</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php
                            echo htmlspecialchars(trim($row['title'] . ' ' . $row['firstname'] . ' ' . $row['lastname']));
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['company']); ?></td>
                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                    <td>
                        <?php
                            if ($row['assigned_firstname']) {
                                echo htmlspecialchars($row['assigned_firstname'] . ' ' . $row['assigned_lastname']);
                            } else {
                                echo 'Unassigned';
                            }
                        ?>
                    </td>
                    <td>
                        <a href="contact.php?id=<?php echo $row['id']; ?>">View</a>
                        <!-- contact.php will show full details -->
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No contacts found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

</body>
</html>
