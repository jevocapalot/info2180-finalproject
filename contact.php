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
    <title>Contact Details - Dolphin CRM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        nav a { margin-right: 10px; text-decoration: none; }
        .contact-header { display: flex; justify-content: space-between; align-items: center; }
        .buttons form { display: inline-block; margin-left: 10px; }
        .notes { margin-top: 30px; }
        .note { border-bottom: 1px solid #ddd; padding: 8px 0; }
        textarea { width: 100%; min-height: 80px; }
        button { padding: 6px 12px; margin-top: 6px; }
        dt { font-weight: bold; }
        dd { margin: 0 0 8px 0; }
    </style>
</head>
<body>

<header>
    <h1>Dolphin CRM</h1>
    <nav>
        <a href="dashboard.php">Home</a>
        <a href="new_contact.php">New Contact</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<section class="contact-details">
    <div class="contact-header">
        <div>
            <h2>
                <?php echo htmlspecialchars(trim($contact['title'] . ' ' . $contact['firstname'] . ' ' . $contact['lastname'])); ?>
            </h2>
            <p>
                Created on <?php echo htmlspecialchars($contact['created_at']); ?>
                by <?php echo htmlspecialchars(trim($contact['creator_first'] . ' ' . $contact['creator_last'])); ?><br>
                Last updated: <?php echo htmlspecialchars($contact['updated_at']); ?>
            </p>
        </div>

        <div class="buttons">
            <!-- Assign to me -->
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <button type="submit">Assign to me</button>
            </form>

            <!-- Toggle type -->
            <form method="POST">
                <input type="hidden" name="action" value="toggle_type">
                <input type="hidden" name="new_type" value="<?php echo htmlspecialchars($new_type); ?>">
                <button type="submit"><?php echo htmlspecialchars($toggle_label); ?></button>
            </form>
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
        <dd><?php echo htmlspecialchars($contact['type']); ?></dd>

        <dt>Assigned To</dt>
        <dd>
            <?php
            if ($contact['assignee_first']) {
                echo htmlspecialchars($contact['assignee_first'] . ' ' . $contact['assignee_last']);
            } else {
                echo "Unassigned";
            }
            ?>
        </dd>
    </dl>
</section>

<section class="notes">
    <h3>Notes</h3>

    <div id="notes-list">
    <?php if ($notes->num_rows > 0): ?>
        <?php while ($note = $notes->fetch_assoc()): ?>
            <div class="note">
                <p><?php echo nl2br(htmlspecialchars($note['comment'])); ?></p>
                <small>
                    By <?php echo htmlspecialchars($note['firstname'] . ' ' . $note['lastname']); ?>
                    on <?php echo htmlspecialchars($note['created_at']); ?>
                </small>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No notes yet.</p>
    <?php endif; ?>
    </div>

    
    <h4>Add a note about this contact</h4>
    <form id="note-form">
        <textarea name="comment" required></textarea>
        <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
        <button type="submit">Add Note</button>
    </form>
    <p id="note-error" style="color:red;"></p>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('note-form');
    const notesList = document.getElementById('notes-list');
    const errorBox = document.getElementById('note-error');

    form.addEventListener('submit', function (e) {
        e.preventDefault(); // stop normal form submit / page reload

        errorBox.textContent = "";

        const formData = new FormData(form);

        fetch('add_note.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                errorBox.textContent = data.error || 'Error adding note.';
                return;
            }

            // build a new note HTML block
            const div = document.createElement('div');
            div.className = 'note';
            div.innerHTML = `
                <p>${escapeHtml(data.comment).replace(/\n/g, '<br>')}</p>
                <small>By ${escapeHtml(data.user_name)} on ${escapeHtml(data.created_at)}</small>
            `;

            // put new note at top
            notesList.insertBefore(div, notesList.firstChild);

            // clear textarea
            form.comment.value = "";
        })
        .catch(err => {
            console.error(err);
            errorBox.textContent = 'Network error.';
        });
    });

    // Basic escape to avoid injecting HTML
    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
});
</script>

</body>
</html>
