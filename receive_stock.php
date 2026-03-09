<?php
// receive_stock.php - Site Manager Confirm / Reject Receipt (Updated with Notification)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$success = '';  // Initialize
$error   = '';  // Initialize

if ($_SESSION['role_name'] !== 'site_manager') {
    header("Location: index.php?error=Access denied");
    exit;
}

// Fetch pending transfers count (for notification)
$pending_count = 0;
try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM stock_transactions t
        JOIN users u ON t.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE t.type = 'OUT'
          AND t.status = 'PENDING'
          AND (t.transfer_to_user_id = ? OR t.transfer_to_site_id = ?)
          AND r.role_name IN ('storekeeper', 'administrator')
    ");
    $count_stmt->execute([$_SESSION['user_id'], $_SESSION['site_id'] ?? 0]);
    $pending_count = $count_stmt->fetchColumn();
} catch (PDOException $e) {
    $error = "Error checking pending transfers: " . $e->getMessage();
}

// Fetch pending transfers list
$transfers = [];
try {
    $pending = $pdo->prepare("
        SELECT t.id, t.quantity, t.reference, t.notes,
               p.code, p.name, p.unit,
               u.full_name AS issued_by
        FROM stock_transactions t
        JOIN products p ON t.product_id = p.id
        JOIN users u ON t.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE t.type = 'OUT'
          AND t.status = 'PENDING'
          AND (t.transfer_to_user_id = ? OR t.transfer_to_site_id = ?)
          AND r.role_name IN ('storekeeper', 'administrator')
        ORDER BY t.transaction_date DESC
    ");
    $pending->execute([$_SESSION['user_id'], $_SESSION['site_id'] ?? 0]);
    $transfers = $pending->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading transfers: " . $e->getMessage();
}

// Handle Confirm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm') {
    $trans_id = (int)$_POST['trans_id'];

    try {
        $pdo->beginTransaction();

        $update = $pdo->prepare("
            UPDATE stock_transactions 
            SET status = 'RECEIVED',
                confirmed_by = ?,
                confirmed_at = NOW()
            WHERE id = ? AND status = 'PENDING'
        ");
        $update->execute([$_SESSION['user_id'], $trans_id]);

        $insert = $pdo->prepare("
            INSERT INTO stock_transactions (
                product_id, type, quantity, reference,
                notes, user_id, transaction_date
            )
            SELECT product_id, 'IN', quantity, CONCAT('Received from transfer #', id),
                   'Confirmed receipt', ?, NOW()
            FROM stock_transactions
            WHERE id = ?
        ");
        $insert->execute([$_SESSION['user_id'], $trans_id]);

        $pdo->commit();
        $success = "Receipt confirmed successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error confirming: " . $e->getMessage();
    }
}

// Handle Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    $trans_id = (int)$_POST['trans_id'];
    $reason   = trim($_POST['reject_reason'] ?? '');

    if (empty($reason)) {
        $error = "Reject reason is required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE stock_transactions 
                SET status = 'REJECTED',
                    rejected_by = ?,
                    rejected_at = NOW(),
                    reject_reason = ?
                WHERE id = ? AND status = 'PENDING'
            ");
            $stmt->execute([$_SESSION['user_id'], $reason, $trans_id]);

            $success = "Transfer rejected successfully. Reason: " . htmlspecialchars($reason);
        } catch (PDOException $e) {
            $error = "Error rejecting: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Stock Transfers - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">Receive Stock Transfers</h2>

    <!-- Notification Banner -->
    <?php if ($pending_count > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="fas fa-bell me-3 fa-2x"></i>
            <div>
                <strong>New pending transfers!</strong><br>
                You have <strong><?= $pending_count ?></strong> new stock transfer(s) waiting for you to confirm or reject.
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php else: ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="fas fa-check-circle me-3 fa-2x"></i>
            <div>
                <strong>All caught up!</strong><br>
                No pending stock transfers at the moment.
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Success / Error Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Transfers Table (only shown if there are transfers) -->
    <?php if (!empty($transfers)): ?>
        <div class="table-responsive mt-4">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Issued By</th>
                        <th>Reference</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['code']) ?> - <?= htmlspecialchars($t['name']) ?></td>
                            <td><?= number_format($t['quantity'], 2) ?> <?= $t['unit'] ?></td>
                            <td><?= htmlspecialchars($t['issued_by']) ?></td>
                            <td><?= htmlspecialchars($t['reference'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($t['notes'] ?: '-') ?></td>
                            <td>
                                <!-- Confirm -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="confirm">
                                    <input type="hidden" name="trans_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm me-2">
                                        <i class="fas fa-check"></i> Confirm Receipt
                                    </button>
                                </form>

                                <!-- Reject -->
                                <button type="button" class="btn btn-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#rejectModal<?= $t['id'] ?>">
                                    <i class="fas fa-times"></i> Reject
                                </button>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Transfer #<?= $t['id'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="trans_id" value="<?= $t['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Reason for Rejection <span class="text-danger">*</span></label>
                                                        <textarea name="reject_reason" class="form-control" rows="4" required 
                                                                  placeholder="e.g. Wrong quantity, damaged items, not as ordered..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Confirm Reject</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary mt-4">Back to Dashboard</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>