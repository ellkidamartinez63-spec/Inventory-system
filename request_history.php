<?php
// request_history.php - View Material Request History

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$is_site_manager = $_SESSION['role_name'] === 'site_manager';
$is_storekeeper  = in_array($_SESSION['role_name'], ['storekeeper', 'administrator']);

if (!$is_site_manager && !$is_storekeeper) {
    header("Location: index.php?error=Access denied");
    exit;
}

// Filter by status (optional via GET)
$status_filter = $_GET['status'] ?? 'all';
$valid_statuses = ['all', 'PENDING_REQUEST', 'APPROVED', 'REJECTED'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Build query
$query = "
    SELECT r.id, r.quantity, r.task_description, r.needed_by, r.created_at,
           r.status, r.attachments, r.rejected_reason, r.approved_at,
           p.code, p.name, p.unit,
           sm.full_name AS site_manager,
           sk.full_name AS storekeeper_name
    FROM material_requests r
    JOIN products p ON r.product_id = p.id
    JOIN users sm ON r.site_manager_id = sm.id
    LEFT JOIN users sk ON r.storekeeper_id = sk.id
";

$params = [];
if ($is_site_manager) {
    $query .= " WHERE r.site_manager_id = ?";
    $params[] = $_SESSION['user_id'];
} 

if ($status_filter !== 'all') {
    $query .= ($is_site_manager ? " AND" : " WHERE") . " r.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY r.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading history: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Request History - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">Material Request History</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filter Buttons -->
    <div class="mb-4">
        <a href="?status=all" class="btn <?= $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
        <a href="?status=PENDING_REQUEST" class="btn <?= $status_filter === 'PENDING_REQUEST' ? 'btn-warning' : 'btn-outline-warning' ?>">Pending</a>
        <a href="?status=APPROVED" class="btn <?= $status_filter === 'APPROVED' ? 'btn-success' : 'btn-outline-success' ?>">Approved</a>
        <a href="?status=REJECTED" class="btn <?= $status_filter === 'REJECTED' ? 'btn-danger' : 'btn-outline-danger' ?>">Rejected</a>
    </div>

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">No requests found matching the current filter.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <?php if ($is_storekeeper): ?>
                            <th>Requested By</th>
                        <?php endif; ?>
                        <th>Status</th>
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
                            <?php if ($is_storekeeper): ?>
                                <td><?= htmlspecialchars($r['site_manager']) ?></td>
                            <?php endif; ?>
                            <td>
                                <?php
                                $badge_class = $badge_text = '';
                                switch ($r['status']) {
                                    case 'PENDING_REQUEST':
                                        $badge_class = 'bg-warning text-dark';
                                        $badge_text = 'Pending';
                                        break;
                                    case 'APPROVED':
                                        $badge_class = 'bg-success';
                                        $badge_text = 'Approved ' . ($r['approved_at'] ? date('d M Y H:i', strtotime($r['approved_at'])) : '');
                                        break;
                                    case 'REJECTED':
                                        $badge_class = 'bg-danger';
                                        $badge_text = 'Rejected';
                                        break;
                                }
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $badge_text ?></span>
                            </td>
                            <td><?= date('d M Y', strtotime($r['needed_by'])) ?></td>
                            <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                            <td>
                                <?php 
                                if (!empty($r['attachments'])) {
                                    $files = json_decode($r['attachments'], true);
                                    foreach ($files as $file) {
                                        echo '<a href="' . htmlspecialchars($file) . '" target="_blank" class="d-block mb-1 small">
                                                <i class="fas fa-file-alt"></i> View
                                              </a>';
                                    }
                                } else {
                                    echo '<small class="text-muted">None</small>';
                                }
                                ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-outline-info btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModal<?= $r['id'] ?>">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </td>
                        </tr>

                        <!-- View Details Modal -->
                        <div class="modal fade" id="viewModal<?= $r['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Request #<?= $r['id'] ?> Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>Requested by:</strong> <?= htmlspecialchars($r['site_manager']) ?></p>
                                        <p><strong>Product:</strong> <?= htmlspecialchars($r['code'] . ' - ' . $r['name']) ?> (<?= $r['unit'] ?>)</p>
                                        <p><strong>Quantity:</strong> <?= number_format($r['quantity'], 2) ?></p>
                                        <p><strong>Needed by:</strong> <?= date('d M Y', strtotime($r['needed_by'])) ?></p>
                                        <p><strong>Requested on:</strong> <?= date('d M Y H:i', strtotime($r['created_at'])) ?></p>
                                        <?php if ($is_storekeeper): ?>
                                            <p><strong>Processed by:</strong> 
                                                <?= $r['storekeeper_name'] ? htmlspecialchars($r['storekeeper_name']) : '—' ?>
                                                <?php if ($r['approved_at']): ?>
                                                    <small>(Approved: <?= date('d M Y H:i', strtotime($r['approved_at'])) ?>)</small>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                        <hr>
                                        <p><strong>Task / Purpose:</strong></p>
                                        <p class="bg-light p-3 rounded"><?= nl2br(htmlspecialchars($r['task_description'])) ?></p>
                                        <?php if (!empty($r['rejected_reason'])): ?>
                                            <hr>
                                            <p><strong>Reject Reason:</strong></p>
                                            <p class="bg-danger-subtle p-3 rounded"><?= nl2br(htmlspecialchars($r['rejected_reason'])) ?></p>
                                        <?php endif; ?>
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
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary mt-4">Back to Dashboard</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>