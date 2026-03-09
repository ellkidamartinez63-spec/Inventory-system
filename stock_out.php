<?php
// stock_out.php - Record Stock Out (Issue Materials) from Site

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

// Allowed roles
$allowed_roles = ['administrator', 'storekeeper', 'site_manager'];
if (!in_array($_SESSION['role_name'] ?? '', $allowed_roles)) {
    header("Location: index.php?error=Access denied");
    exit;
}

// Load products with current stock
try {
    $stmt = $pdo->query("
        SELECT p.id, p.code, p.name, p.unit,
               COALESCE(cs.current_qty, 0) AS current_qty
        FROM products p
        LEFT JOIN current_stock cs ON p.id = cs.id
        ORDER BY p.name ASC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $load_error = "Error loading products: " . $e->getMessage();
}

// Handle form submission
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id     = (int)($_POST['product_id'] ?? 0);
    $quantity       = (float)($_POST['quantity'] ?? 0);
    $reference      = trim($_POST['reference'] ?? '');
    $issued_to      = trim($_POST['issued_to'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');

    // Find current stock for selected product
    $current_stock = 0;
    $unit = '';
    foreach ($products as $p) {
        if ($p['id'] == $product_id) {
            $current_stock = $p['current_qty'];
            $unit = $p['unit'];
            break;
        }
    }

    // Validation
    if ($product_id <= 0) {
        $error = "Please select a product.";
    } elseif ($quantity <= 0) {
        $error = "Quantity must be greater than 0.";
    } elseif ($quantity > $current_stock) {
        $error = "Not enough stock! Available: " . number_format($current_stock, 2) . " $unit";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO stock_transactions (
                    product_id, type, quantity, reference,
                    notes,
                    user_id, transaction_date
                ) VALUES (
                    ?, 'OUT', ?, ?,
                    ?,
                    ?, NOW()
                )
            ");

            $stmt->execute([
                $product_id, $quantity, $reference,
                $notes,
                $_SESSION['user_id']
            ]);

            $success = "Stock OUT recorded successfully!";
            header("Refresh: 2; url=index.php");
        } catch (PDOException $e) {
            $error = "Error saving record: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Out - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">Record Stock Out (Issue Materials)</h2>

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

    <?php if (isset($load_error)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($load_error) ?></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-danger text-white">
            <h5>Issue Materials from Stock</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">

                    <!-- Product -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select form-select-lg" required onchange="showAvailable(this)">
                            <option value="">-- Select product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" 
                                        data-available="<?= $p['current_qty'] ?>"
                                        data-unit="<?= htmlspecialchars($p['unit']) ?>">
                                    <?= htmlspecialchars($p['code']) ?> — <?= htmlspecialchars($p['name']) ?> 
                                    (Available: <?= number_format($p['current_qty'], 2) ?> <?= $p['unit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="available-info" class="form-text mt-1 fw-bold"></div>
                    </div>

                    <!-- Quantity -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Quantity to Issue <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control form-control-lg" 
                               step="0.01" min="0.01" required placeholder="e.g. 50">
                    </div>

                    <!-- Reference / Purpose -->
                    <div class="col-md-3">
                        <label class="form-label">Reference / Purpose</label>
                        <input type="text" name="reference" class="form-control form-control-lg" 
                               placeholder="e.g. IV-2025-045, Foundation Works">
                    </div>

                    <!-- Issued To (free text) -->
                    <div class="col-md-6">
                        <label class="form-label">Issued To / Worker / Subcontractor</label>
                        <input type="text" name="issued_to" class="form-control form-control-lg" 
                               placeholder="e.g. Mr. Juma, Team A, Site Manager John">
                    </div>

                    <!-- Notes -->
                    <div class="col-12">
                        <label class="form-label">Notes / Remarks</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="e.g. Used for emergency repair"></textarea>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-danger btn-lg px-5">
                            <i class="fas fa-minus-circle me-2"></i> Confirm Stock Out
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg ms-3">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAvailable(select) {
    const info = document.getElementById('available-info');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        const qty = parseFloat(option.dataset.available);
        const unit = option.dataset.unit;
        info.innerHTML = `Available now: <strong>${qty.toFixed(2)} ${unit}</strong>`;
        
        if (qty <= 0) {
            info.className = 'text-danger fw-bold';
        } else {
            info.className = 'text-success fw-bold';
        }
    } else {
        info.innerHTML = '';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>