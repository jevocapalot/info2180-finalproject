<?php
session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid contact.");
}

$contact_id = (int) $_GET['id'];

// Handle actions: assign to me, toggle type, add note
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $sql = "UPDATE contacts SET assigned_to = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $contact_id);
        $stmt->execute();
    } elseif ($action === 'toggle_type') {
        $new_type = $_POST['new_type'] ?? '';
        if (in_array($new_type, ['Sales Lead', 'Support'])) {
            $sql = "UPDATE contacts SET type = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_type, $contact_id);
            $stmt->execute();
        }
    } elseif ($action === 'add_note') {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment !== '') {
            // Insert note
            $sql = "INSERT INTO notes (contact_id, comment, created_by, created_at)
                    VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isi", $contact_id, $comment, $user_id);
            $stmt->execute();

            // Update contact updated_at
            $sql2 = "UPDATE contacts SET updated_at = NOW() WHERE id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("i", $contact_id);
            $stmt2->execute();
        }
    }

    // Avoid resubmission on refresh
    header("Location: contact.php?id=" . $contact_id);
    exit();
}

// Get contact details 
$sql = "
    SELECT c.*,
           creator.firstname AS creator_first,
           creator.lastname  AS creator_last,
           assignee.firstname AS assignee_first,
           assignee.lastname  AS assignee_last
    FROM contacts c
    LEFT JOIN users creator ON c.created_by = creator.id
    LEFT JOIN users assignee ON c.assigned_to = assignee.id
    WHERE c.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $contact_id);
$stmt->execute();
$contact_result = $stmt->get_result();

if ($contact_result->num_rows !== 1) {
    die("Contact not found.");
}

$contact = $contact_result->fetch_assoc();

// Get notes for this contact
$note_sql = "
    SELECT n.comment, n.created_at,
           u.firstname, u.lastname
    FROM notes n
    JOIN users u ON n.created_by = u.id
    WHERE n.contact_id = ?
    ORDER BY n.created_at DESC
";
$note_stmt = $conn->prepare($note_sql);
$note_stmt->bind_param("i", $contact_id);
$note_stmt->execute();
$notes = $note_stmt->get_result();

// Determine toggle label & target type
$current_type = $contact['type'];
if ($current_type === 'Sales Lead') {
    $toggle_label = "Switch to Support";
    $new_type = "Support";
} else {
    $toggle_label = "Switch to Sales Lead";
    $new_type = "Sales Lead";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dolphin CRM - Contact</title>
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
            <h2>Contact</h2>
        </div>

        <section class="card">
            <div class="contact-header">
                <div class="contact-name">
                    <h2>
                        <?php echo htmlspecialchars(trim($contact['title'].' '.$contact['firstname'].' '.$contact['lastname'])); ?>
                    </h2>
                    <div class="contact-meta">
                        Created on <?php echo htmlspecialchars($contact['created_at']); ?>
                        by <?php echo htmlspecialchars(trim($contact['creator_first'].' '.$contact['creator_last'])); ?>
                        <br>
                        Last updated:
                        <span id="contact-updated-at">
                            <?php echo htmlspecialchars($contact['updated_at']); ?>
                        </span>
                    </div>
                </div>

                <div class="contact-actions">
                    <button type="button" id="assign-btn">Assign to me</button>
                    <button type="button"
                            id="toggle-type-btn"
                            data-new-type="<?php echo htmlspecialchars($new_type); ?>">
                        <?php echo htmlspecialchars($toggle_label); ?>
                    </button>
                </div>
            </div>

            <dl>
                <dt>Email</dt>
                <dd><?php echo htmlspecialchars($contact['email']); ?></dd>

                <dt>Telephone</dt>
                <dd><?php echo htmlspecialchars($contact['telephone']); ?></dd>

                <dt>Company</dt>
                <dd><?php echo htmlspecialchars($contact['company']); ?></dd>

                <dt>Type</dt>
                <dd id="contact-type"><?php echo htmlspecialchars($contact['type']); ?></dd>

                <dt>Assigned To</dt>
                <dd id="contact-assigned-to">
                    <?php
                    if ($contact['assignee_first']) {
                        echo htmlspecialchars($contact['assignee_first'].' '.$contact['assignee_last']);
                    } else {
                        echo "Unassigned";
                    }
                    ?>
                </dd>
            </dl>
        </section>

        <section class="card notes">
            <h3>Notes</h3>

            <div id="notes-list">
                <?php if ($notes->num_rows > 0): ?>
                    <?php while ($note = $notes->fetch_assoc()): ?>
                        <div class="note">
                            <p><?php echo nl2br(htmlspecialchars($note['comment'])); ?></p>
                            <small>
                                By <?php echo htmlspecialchars($note['firstname'].' '.$note['lastname']); ?>
                                on <?php echo htmlspecialchars($note['created_at']); ?>
                            </small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No notes yet.</p>
                <?php endif; ?>
            </div>

            <h4 style="margin-top:12px;">Add a note about this contact</h4>
            <form id="note-form">
                <textarea name="comment" required></textarea>
                <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                <button type="submit" style="margin-top:10px;">Add Note</button>
            </form>
            <p id="note-error" class="error"></p>
        </section>
    </main>
</div>
<script src="./app.js"></script> 
</body>
</html>
