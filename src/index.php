<?php
require_once __DIR__ . '/db.php';
ensureTable();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $message === '') {
        $error = "Both name and message are required.";
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("INSERT INTO messages (name, message) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $message);
        if ($stmt->execute()) {
            $success = "Saved! Thank you, " . htmlspecialchars($name) . ".";
        } else {
            $error = "Error saving message: " . $stmt->error;
        }
        $stmt->close();
        $conn->close();
    }
}

// Fetch existing messages
$conn = getConnection();
$result = $conn->query("SELECT name, message, created_at FROM messages ORDER BY id DESC LIMIT 20");
$rows = [];
while ($result && ($row = $result->fetch_assoc())) {
    $rows[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP + MySQL Demo App</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
        input, textarea { width: 100%; padding: 8px; margin: 6px 0; box-sizing: border-box; }
        button { padding: 8px 16px; background: #2c7be5; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .msg { background: #f4f4f4; padding: 10px; margin-bottom: 8px; border-radius: 4px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>PHP + MySQL Demo</h1>

    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <form method="POST">
        <label>Name</label>
        <input type="text" name="name" required>
        <label>Message</label>
        <textarea name="message" rows="3" required></textarea>
        <button type="submit">Submit</button>
    </form>

    <h2>Recent Messages</h2>
    <?php if (empty($rows)): ?>
        <p>No messages yet.</p>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <div class="msg">
                <strong><?= htmlspecialchars($row['name']) ?></strong>
                <span style="color:#888;font-size:0.85em;"> — <?= htmlspecialchars($row['created_at']) ?></span>
                <p><?= htmlspecialchars($row['message']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
