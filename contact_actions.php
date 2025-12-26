<?php
session_start();
header('Content-Type: application/json');
require 'config.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'Not logged in']);
  exit();
}

$user_id = (int) $_SESSION['user_id'];

$action     = $_POST['action'] ?? '';
$contact_id = $_POST['contact_id'] ?? '';

if ($contact_id === '' || !is_numeric($contact_id)) {
  echo json_encode(['success' => false, 'error' => 'Invalid contact id']);
  exit();
}
$contact_id = (int) $contact_id;

if ($action === 'assign') {
  // assign to current user
  $sql = "UPDATE contacts SET assigned_to = ?, updated_at = NOW() WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $user_id, $contact_id);

  if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'DB error assigning contact']);
    exit();
  }

  // get user name + updated_at for UI
  $sql2 = "
    SELECT c.updated_at, u.firstname, u.lastname
    FROM contacts c
    LEFT JOIN users u ON c.assigned_to = u.id
    WHERE c.id = ?
  ";
  $stmt2 = $conn->prepare($sql2);
  $stmt2->bind_param("i", $contact_id);
  $stmt2->execute();
  $r = $stmt2->get_result()->fetch_assoc();

  $assigned_to = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));

  echo json_encode([
    'success'     => true,
    'assigned_to' => $assigned_to !== '' ? $assigned_to : 'You',
    'updated_at'  => $r['updated_at'] ?? ''
  ]);
  exit();
}

if ($action === 'toggle_type') {
  $new_type = $_POST['new_type'] ?? '';
  if (!in_array($new_type, ['Sales Lead', 'Support'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid type']);
    exit();
  }

  $sql = "UPDATE contacts SET type = ?, updated_at = NOW() WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("si", $new_type, $contact_id);

  if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'DB error updating type']);
    exit();
  }

  // Determine next button label + next type
  if ($new_type === 'Sales Lead') {
    $next_label = "Switch to Support";
    $next_newType = "Support";
  } else {
    $next_label = "Switch to Sales Lead";
    $next_newType = "Sales Lead";
  }

  // Get updated_at
  $stmt2 = $conn->prepare("SELECT updated_at FROM contacts WHERE id = ?");
  $stmt2->bind_param("i", $contact_id);
  $stmt2->execute();
  $r = $stmt2->get_result()->fetch_assoc();

  echo json_encode([
    'success'       => true,
    'type'          => $new_type,
    'updated_at'    => $r['updated_at'] ?? '',
    'next_label'    => $next_label,
    'next_newType'  => $next_newType
  ]);
  exit();
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
exit();
