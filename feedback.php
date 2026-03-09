<?php
// feedback.php - Site Manager Feedback Submission

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

// Only site_manager can access
if ($_SESSION['role_name'] !== 'site_manager') {
    header("Location: index.php?error=Access denied");
    exit;
}

// Handle form
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $products_used = trim($_POST['products_used'] ?? '');
    $work_done = trim($_POST['work_done'] ?? '');
    $challenges = trim($_POST['challenges'] ?? '');
    $successes = trim($_POST['successes'] ?? '');

    if (empty($products_used) || empty($work_done)) {
        $error = "Products used and work done are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO site_feedback (
                    site_manager_id, products_used, work_done, challenges, successes
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $products_used, $work_done, $challenges, $successes]);
            $success = "Feedback submitted successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Feedback - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">Submit Site Feedback</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-bold">Total Products Used <span class="text-danger">*</span></label>
                    <textarea name="products_used" class="form-control" rows="4" required placeholder="e.g. 100 bags cement, 50 steel bars"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Total Work Done <span class="text-danger">*</span></label>
                    <textarea name="work_done" class="form-control" rows="4" required placeholder="e.g. Completed 80% of foundation, installed 50m piping"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Challenges</label>
                    <textarea name="challenges" class="form-control" rows="3" placeholder="e.g. Rain delays, supply shortages"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Successes</label>
                    <textarea name="successes" class="form-control" rows="3" placeholder="e.g. Ahead of schedule on wiring, team morale high"></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i> Submit Feedback
                </button>
                <a href="index.php" class="btn btn-secondary btn-lg ms-3">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>