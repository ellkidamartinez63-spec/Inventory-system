<?php
// stock_in.php - Record Stock In (Goods Received) with File Upload

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$allowed_roles = ['administrator', 'storekeeper', 'site_manager'];
if (!in_array($_SESSION['role_name'] ?? '', $allowed_roles)) {
    header("Location: index.php?error=Access denied");
    exit;
}

// Load products
$products = $pdo->query("SELECT id, code, name, unit FROM products ORDER BY name ASC")->fetchAll();

// Handle form submission
$success = $error = '';
$uploaded_names = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id            = (int)($_POST['product_id'] ?? 0);
    $quantity              = (float)($_POST['quantity'] ?? 0);
    $reference             = trim($_POST['reference'] ?? '');
    $proforma_number       = trim($_POST['proforma_number'] ?? '');
    $proforma_date         = $_POST['proforma_date'] ?? null;
    $receipt_number        = trim($_POST['receipt_number'] ?? '');
    $efd_receipt_number    = trim($_POST['efd_receipt_number'] ?? '');
    $efd_verification_code = trim($_POST['efd_verification_code'] ?? '');
    $efd_issue_date        = $_POST['efd_issue_date'] ?? null;
    $supplier_tin          = trim($_POST['supplier_tin'] ?? '');
    $notes                 = trim($_POST['notes'] ?? '');

    // Validation
    $missing = [];
    if ($product_id <= 0)               $missing[] = "Product";
    if ($quantity <= 0)                 $missing[] = "Quantity";
    if (empty($proforma_number))        $missing[] = "Proforma number";
    if (empty($receipt_number))         $missing[] = "Receipt / GRN number";
    if (empty($efd_receipt_number))     $missing[] = "EFD Receipt number";
    if (empty($efd_verification_code))  $missing[] = "EFD Verification code";
    if (empty($supplier_tin))           $missing[] = "Supplier TIN";

    if (!empty($missing)) {
        $error = "Required fields: " . implode(", ", $missing) . ".";
    } else {
        // File upload handling
        $upload_base = __DIR__ . '/uploads/stock_in/';
        $month_folder = date('Y-m');
        $upload_dir = $upload_base . $month_folder . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $attachments = [];
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['attachments']['name'][$key];
                    $file_size = $_FILES['attachments']['size'][$key];
                    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (in_array($file_ext, $allowed_types) && $file_size <= $max_size) {
                        $new_name = uniqid('doc_') . '_' . time() . '.' . $file_ext;
                        $dest_path = $upload_dir . $new_name;

                        if (move_uploaded_file($tmp_name, $dest_path)) {
                            $web_path = "uploads/stock_in/$month_folder/$new_name";
                            $attachments[] = $web_path;
                            $uploaded_names[] = $file_name;
                        } else {
                            $error = "Failed to save file: $file_name";
                        }
                    } else {
                        $error = "Invalid file: $file_name (allowed: jpg, png, pdf; max 5MB)";
                    }
                }
            }
        }

        if (empty($error)) {
            $attachments_str = !empty($attachments) ? implode(',', $attachments) : null;

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO stock_transactions (
                        product_id, type, quantity, reference,
                        proforma_number, proforma_date,
                        receipt_number,
                        efd_receipt_number, efd_verification_code, efd_issue_date,
                        supplier_tin, notes,
                        attachments,
                        user_id, transaction_date
                    ) VALUES (
                        ?, 'IN', ?, ?,
                        ?, ?,
                        ?,
                        ?, ?, ?,
                        ?, ?,
                        ?,
                        ?, NOW()
                    )
                ");

                $stmt->execute([
                    $product_id, $quantity, $reference,
                    $proforma_number, $proforma_date,
                    $receipt_number,
                    $efd_receipt_number, $efd_verification_code, $efd_issue_date,
                    $supplier_tin, $notes,
                    $attachments_str,
                    $_SESSION['user_id']
                ]);

                $success = "Stock IN recorded successfully!";
                if (!empty($uploaded_names)) {
                    $success .= "<br>Uploaded files: " . implode(', ', $uploaded_names);
                }
                header("Refresh: 2; url=index.php");
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
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
    <title>Stock In - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">Record Stock In (Goods Received)</h2>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h5>New Stock Receipt</h5>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select form-select-lg" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['code']) ?> - <?= htmlspecialchars($p['name']) ?> (<?= $p['unit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control form-control-lg" step="0.01" min="0.01" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Reference / PO#</label>
                        <input type="text" name="reference" class="form-control form-control-lg">
                    </div>

                    <!-- TRA Fields -->
                    <div class="col-12 mt-4"><h6 class="border-bottom pb-2">TRA & Supplier Documents (Required)</h6></div>

                    <div class="col-md-4">
                        <label class="form-label">Proforma No. <span class="text-danger">*</span></label>
                        <input type="text" name="proforma_number" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Proforma Date</label>
                        <input type="date" name="proforma_date" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Receipt/GRN No. <span class="text-danger">*</span></label>
                        <input type="text" name="receipt_number" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">EFD Receipt No. <span class="text-danger">*</span></label>
                        <input type="text" name="efd_receipt_number" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">EFD Verification Code <span class="text-danger">*</span></label>
                        <input type="text" name="efd_verification_code" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">EFD Issue Date</label>
                        <input type="datetime-local" name="efd_issue_date" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Supplier TIN <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_tin" class="form-control" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes / Remarks</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>

                    <!-- File Upload -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Attach Documents (Proforma, Receipt, EFD, etc.)</label>
                        <input type="file" name="attachments[]" class="form-control" multiple accept="image/*,.pdf">
                        <small class="form-text text-muted">Max 5MB per file. Allowed: jpg, png, pdf</small>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-save me-2"></i> Save Stock In
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg ms-3">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>