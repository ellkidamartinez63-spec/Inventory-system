<?php
// index.php - Role-Aware Dashboard (Regenerated - Complete with Request History)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

require_login();

// Fetch current stock summary (common)
try {
    $stmt = $pdo->query("SELECT * FROM current_stock ORDER BY name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_products = count($products);
    $low_stock      = 0;
    $out_of_stock   = 0;
    $total_value    = 0;

    foreach ($products as $p) {
        $total_value += $p['current_qty'] * $p['unit_cost'];
        if ($p['current_qty'] <= 0) $out_of_stock++;
        elseif ($p['current_qty'] <= $p['min_stock']) $low_stock++;
    }
} catch (PDOException $e) {
    $stock_error = $e->getMessage();
}

// Pending TRA/EFD & high-value receipts - financial manager only
$pending_efd = [];
$high_value_receipts = [];
if ($_SESSION['role_name'] === 'financial_manager') {
    try {
        $pending_stmt = $pdo->query("
            SELECT t.id, t.transaction_date, t.quantity,
                   p.code, p.name, p.unit,
                   t.supplier_tin, t.efd_receipt_number, 
                   t.efd_verification_code
            FROM stock_transactions t
            JOIN products p ON t.product_id = p.id
            WHERE t.type = 'IN'
              AND (t.efd_verification_code IS NULL OR t.efd_verification_code = ''
                   OR t.supplier_tin IS NULL OR t.supplier_tin = ''
                   OR t.efd_receipt_number IS NULL OR t.efd_receipt_number = '')
            ORDER BY t.transaction_date DESC
            LIMIT 10
        ");
        $pending_efd = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

        $high_value_stmt = $pdo->query("
            SELECT t.id, t.transaction_date, t.quantity, p.unit_cost,
                   (t.quantity * p.unit_cost) AS total_value,
                   p.code, p.name, p.unit,
                   t.supplier_tin, t.efd_verification_code
            FROM stock_transactions t
            JOIN products p ON t.product_id = p.id
            WHERE t.type = 'IN'
              AND (t.quantity * p.unit_cost) > 5000000
            ORDER BY t.transaction_date DESC
            LIMIT 5
        ");
        $high_value_receipts = $high_value_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $finance_error = $e->getMessage();
    }
}

// Site Manager pending transfer count
$pending_count = 0;
if ($_SESSION['role_name'] === 'site_manager') {
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
}

// Role title & welcome
$role = $_SESSION['role_name'] ?? 'unknown';
$dashboard_title = "Dashboard";
$welcome = "Welcome back, <strong>" . htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') . "</strong>";

switch ($role) {
    case 'administrator':
        $dashboard_title = "Admin Control Center";
        $welcome .= " (Full Access)";
        break;
    case 'financial_manager':
        $dashboard_title = "Financial & Compliance Overview";
        $welcome .= " (Financial Manager)";
        break;
    case 'site_manager':
        $dashboard_title = "Site / Project Dashboard";
        $welcome .= " (Site Manager)";
        break;
    case 'storekeeper':
        $dashboard_title = "Warehouse Dashboard";
        $welcome .= " (Storekeeper)";
        break;
}
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0"><?= $dashboard_title ?></h1>
        <span class="badge bg-secondary fs-6 px-3 py-2">
            <?= ucfirst(str_replace('_', ' ', $role)) ?>
        </span>
    </div>

    <p class="lead mb-5"><?= $welcome ?></p>

    <?php if (isset($stock_error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($stock_error) ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-5 g-4">
        <div class="col-md-3"><div class="card text-white bg-primary shadow h-100">
            <div class="card-body text-center"><h5>Total Products</h5><h2 class="display-5 fw-bold"><?= number_format($total_products) ?></h2></div>
        </div></div>
        <div class="col-md-3"><div class="card text-white bg-warning shadow h-100">
            <div class="card-body text-center"><h5>Low Stock</h5><h2 class="display-5 fw-bold"><?= number_format($low_stock) ?></h2></div>
        </div></div>
        <div class="col-md-3"><div class="card text-white bg-danger shadow h-100">
            <div class="card-body text-center"><h5>Out of Stock</h5><h2 class="display-5 fw-bold"><?= number_format($out_of_stock) ?></h2></div>
        </div></div>
        <div class="col-md-3"><div class="card text-white bg-info shadow h-100">
            <div class="card-body text-center"><h5>Total Stock Value</h5><h2 class="display-5 fw-bold"><?= number_format($total_value, 2) ?> TZS</h2></div>
        </div></div>
    </div>

    <!-- Financial Manager Sections -->
    <?php if ($role === 'financial_manager'): ?>
    <div class="row mb-5 g-4">
        <div class="col-lg-6">
            <div class="card shadow border-danger">
                <div class="card-header bg-danger text-white"><h5>Pending TRA/EFD Verification</h5></div>
                <div class="card-body">
                    <?php if (isset($finance_error)): ?>
                        <div class="alert alert-warning"><?= htmlspecialchars($finance_error) ?></div>
                    <?php elseif (empty($pending_efd)): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>All receipts verified.</div>
                    <?php else: ?>
                        <p><strong><?= count($pending_efd) ?></strong> receipts need attention.</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead><tr><th>Date</th><th>Product</th><th>Qty</th><th>TIN</th><th>EFD Receipt</th><th>Code</th></tr></thead>
                                <tbody>
                                    <?php foreach ($pending_efd as $t): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
                                            <td><?= htmlspecialchars($t['code'] . ' - ' . $t['name']) ?></td>
                                            <td><?= number_format($t['quantity'], 2) . ' ' . $t['unit'] ?></td>
                                            <td><?= empty($t['supplier_tin']) ? '<span class="badge bg-danger">Missing</span>' : htmlspecialchars($t['supplier_tin']) ?></td>
                                            <td><?= empty($t['efd_receipt_number']) ? '<span class="badge bg-danger">Missing</span>' : htmlspecialchars($t['efd_receipt_number']) ?></td>
                                            <td><?= empty($t['efd_verification_code']) ? '<span class="badge bg-danger">Missing</span>' : htmlspecialchars($t['efd_verification_code']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow border-warning">
                <div class="card-header bg-warning text-dark"><h5>High-Value Receipts (>5M TZS)</h5></div>
                <div class="card-body">
                    <?php if (empty($high_value_receipts)): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>No high-value receipts.</div>
                    <?php else: ?>
                        <p><strong><?= count($high_value_receipts) ?></strong> high-value receipts found.</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead><tr><th>Date</th><th>Product</th><th>Qty</th><th>Value</th><th>TIN</th><th>EFD</th></tr></thead>
                                <tbody>
                                    <?php foreach ($high_value_receipts as $t): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
                                            <td><?= htmlspecialchars($t['code'] . ' - ' . $t['name']) ?></td>
                                            <td><?= number_format($t['quantity'], 2) . ' ' . $t['unit'] ?></td>
                                            <td class="fw-bold"><?= number_format($t['total_value'], 2) ?></td>
                                            <td><?= htmlspecialchars($t['supplier_tin'] ?? '—') ?></td>
                                            <td><?= !empty($t['efd_verification_code']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-light"><h5>Quick Actions</h5></div>
                <div class="card-body">
                    <div class="d-grid gap-3 d-md-flex flex-wrap justify-content-center">
                        <?php if (in_array($role, ['administrator', 'storekeeper'])): ?>
                            <a href="stock_in.php" class="btn btn-success btn-lg"><i class="fas fa-plus-circle me-2"></i>Stock In</a>
                        <?php endif; ?>

                        <?php if (in_array($role, ['administrator', 'storekeeper', 'site_manager'])): ?>
                            <a href="stock_out.php" class="btn btn-danger btn-lg"><i class="fas fa-minus-circle me-2"></i>Stock Out</a>
                        <?php endif; ?>

                        <?php if (in_array($role, ['administrator', 'storekeeper'])): ?>
                            <a href="products.php" class="btn btn-primary btn-lg"><i class="fas fa-boxes-stacked me-2"></i>Manage Products</a>
                            <a href="approve_requests.php" class="btn btn-info btn-lg"><i class="fas fa-tasks me-2"></i>Approve Requests</a>
                            <a href="request_history.php" class="btn btn-secondary btn-lg"><i class="fas fa-history me-2"></i>Request History</a>
                        <?php endif; ?>

                        <?php if ($role === 'financial_manager'): ?>
                            <a href="products.php" class="btn btn-primary btn-lg"><i class="fas fa-boxes-stacked me-2"></i>View Products (Read Only)</a>
                            <a href="export.php?report=feedback" class="btn btn-info btn-lg"><i class="fas fa-file-csv me-2"></i>Export Site Feedback</a>
                        <?php endif; ?>

                        <?php if ($role === 'administrator'): ?>
                            <a href="users.php" class="btn btn-info btn-lg"><i class="fas fa-users-cog me-2"></i>Manage Users</a>
                            <a href="export.php?report=stock" class="btn btn-success btn-lg"><i class="fas fa-file-csv me-2"></i>Export Stock</a>
                            <a href="export.php?report=transactions" class="btn btn-warning btn-lg"><i class="fas fa-history me-2"></i>Export Transactions</a>
                        <?php endif; ?>

                        <?php if ($role === 'site_manager'): ?>
                            <a href="request_material.php" class="btn btn-info btn-lg"><i class="fas fa-file-import me-2"></i>Request Materials</a>
                            <a href="receive_stock.php" class="btn btn-primary btn-lg position-relative">
                                <i class="fas fa-check-circle me-2"></i>Receive Transfers
                                <?php if ($pending_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?= $pending_count ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <a href="feedback.php" class="btn btn-warning btn-lg"><i class="fas fa-comment-dots me-2"></i>Submit Feedback</a>
                            <a href="request_history.php" class="btn btn-secondary btn-lg"><i class="fas fa-history me-2"></i>Request History</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Overview -->
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white"><h5>Current Stock Overview</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>Code</th><th>Name</th><th>Unit</th><th class="text-end">Qty</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="5" class="text-center py-4">No products yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <?php
                                $class = $text = '';
                                if ($p['current_qty'] <= 0) { $class = 'bg-danger text-white'; $text = 'OUT'; }
                                elseif ($p['current_qty'] <= $p['min_stock']) { $class = 'bg-warning'; $text = 'LOW'; }
                                else { $class = 'bg-success text-white'; $text = 'OK'; }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['code']) ?></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= htmlspecialchars($p['unit']) ?></td>
                                    <td class="text-end fw-bold"><?= number_format($p['current_qty'], 2) ?></td>
                                    <td><span class="badge <?= $class ?> fs-6"><?= $text ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>