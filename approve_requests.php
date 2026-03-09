<?php
// approve_requests.php - Storekeeper Approves / Rejects Material Requests (Updated)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

// Only storekeeper and admin can access
if (!in_array($_SESSION['role_name'], ['administrator', 'storekeeper'])) {
    header("Location: index.php?error=Access denied");
    exit;
}

$success = '';  // Initialize to prevent undefined variable
$error   = '';  // Initialize to prevent undefined variable

// Fetch pending requests (only PENDING_REQUEST status)
try {
    $stmt = $pdo->query("
        SELECT r.id, r.quantity, r.task_description, r.needed_by, r.created_at,
               r.attachments, r.rejected_reason,
               p.code, p.name, p.unit,
               u.full_name AS site_manager
        FROM material_requests r
        JOIN products p ON r.product_id = p.id
        JOIN users u ON r.site_manager_id = u.id
        WHERE r.status = 'PENDING_REQUEST'
        ORDER BY r.created_at DESC
    ");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading requests: " . $e->getMessage();
}

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req_id = (int)($_POST['req_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        try {
            $pdo->beginTransaction();

            // Mark request as approved
            $update_req = $pdo->prepare("
                UPDATE material_requests 
                SET status = 'APPROVED',
                    storekeeper_id = ?,
                    approved_at = NOW()
                WHERE id = ? AND status = 'PENDING_REQUEST'
            ");
            $update_req->execute([$_SESSION['user_id'], $req_id]);

            // Create pending OUT transfer to site manager
            $insert_transfer = $pdo->prepare("
                INSERT INTO stock_transactions (
                    product_id, type, quantity, reference,
                    notes, user_id, transaction_date,
                    transfer_to_user_id, status
                )
                SELECT product_id, 'OUT', quantity, CONCAT('Transfer for request #', id),
                       CONCAT('For task: ', task_description), ?, NOW(), site_manager_id, 'PENDING'
                FROM material_requests
                WHERE id = ?
            ");
            $insert_transfer->execute([$_SESSION['user_id'], $req_id]);

            $pdo->commit();
            $success = "Request #$req_id approved successfully. Transfer created and pending site manager confirmation.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error approving request: " . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reject_reason'] ?? '');

        if (empty($reason)) {
            $error = "Reject reason is required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE material_requests 
                    SET status = 'REJECTED',
                        storekeeper_id = ?,
                        rejected_reason = ?
                    WHERE id = ? AND status = 'PENDING_REQUEST'
                ");
                $stmt->execute([$_SESSION['user_id'], $reason, $req_id]);

                $success = "Request #$req_id rejected successfully. Reason saved.";
            } catch (PDOException $e) {
                $error = "Error rejecting request: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Material Requests - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">Pending Material Requests</h2>

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

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">No pending material requests at the moment.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Requested By</th>
                        <th>Task / Purpose</th>
                        <th>Needed By</th>
                        <th>Requested On</th>
                        <th>Attachments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['code'] . ' - ' . $r['name']) ?> (<?= $r['unit'] ?>)</td>
                            <td><?= number_format($r['quantity'], 2) ?></td>
                            <td><?= htmlspecialchars($r['site_manager']) ?></td>
                            <td><?= htmlspecialchars(substr($r['task_description'], 0, 80)) . (strlen($r['task_description']) > 80 ? '...' : '') ?></td>
                            <td><?= date('d M Y', strtotime($r['needed_by'])) ?></td>
                            <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                            <td>
                                <?php 
                                if (!empty($r['attachments'])) {
                                    $files = json_decode($r['attachments'], true);
                                    foreach ($files as $file) {
                                        echo '<a href="' . htmlspecialchars($file) . '" target="_blank" class="d-block mb-1 small">
                                                <i class="fas fa-file-alt"></i> View File
                                              </a>';
                                    }
                                } else {
                                    echo '<small class="text-muted">None</small>';
                                }
                                ?>
                            </td>
                            <td>
                                <!-- Approve -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm me-2">
                                        <i class="fas fa-check"></i> Approve & Issue
                                    </button>
                                </form>

                                <!-- Reject (modal trigger) -->
                                <button type="button" class="btn btn-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#rejectModal<?= $r['id'] ?>">
                                    <i class="fas fa-times"></i> Reject
                                </button>

                                <!-- View Details Modal -->
                                <button type="button" class="btn btn-outline-info btn-sm ms-2" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModal<?= $r['id'] ?>">
                                    <i class="fas fa-eye"></i> Details
                                </button>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?= $r['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Request #<?= $r['id'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Reason for Rejection <span class="text-danger">*</span></label>
                                                        <textarea name="reject_reason" class="form-control" rows="3" required 
                                                                  placeholder="e.g. Insufficient stock, wrong specification, not urgent..."></textarea>
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

                                <!-- View Details Modal -->
                                <div class="modal fade" id="viewModal<?= $r['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Request Details #<?= $r['id'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Requested by:</strong> <?= htmlspecialchars($r['site_manager']) ?></p>
                                                <p><strong>Product:</strong> <?= htmlspecialchars($r['code'] . ' - ' . $r['name']) ?> (<?= $r['unit'] ?>)</p>
                                                <p><strong>Quantity:</strong> <?= number_format($r['quantity'], 2) ?></p>
                                                <p><strong>Needed by:</strong> <?= date('d M Y', strtotime($r['needed_by'])) ?></p>
                                                <p><strong>Requested on:</strong> <?= date('d M Y H:i', strtotime($r['created_at'])) ?></p>
                                                <hr>
                                                <p><strong>Task / Purpose:</strong></p>
                                                <p class="bg-light p-3 rounded"><?= nl2br(htmlspecialchars($r['task_description'])) ?></p>
                                                <hr>
                                                <p><strong>Attachments:</strong></p>
                                                <?php 
                                                if (!empty($r['attachments'])) {
                                                    $files = json_decode($r['attachments'], true);
                                                    foreach ($files as $file) {
                                                        echo '<a href="' . htmlspecialchars($file) . '" target="_blank" class="d-block mb-2">
                                                                <i class="fas fa-file-alt"></i> ' . basename($file) . '
                                                              </a>';
                                                    }
                                                } else {
                                                    echo '<p class="text-muted">No attachments uploaded.</p>';
                                                }
                                                ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
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