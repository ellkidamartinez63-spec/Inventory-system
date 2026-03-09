<?php
// request_material.php - Site Manager Requests Materials (with File Upload)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SESSION['role_name'] !== 'site_manager') {
    header("Location: index.php?error=Access denied");
    exit;
}

// Fetch products for dropdown
$products_stmt = $pdo->query("SELECT id, code, name, unit FROM products ORDER BY name ASC");
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Upload folder (make sure it exists and is writable)
$upload_dir = __DIR__ . '/uploads/requests/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Allowed file types & max size (5MB)
$allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
$max_size = 5 * 1024 * 1024; // 5MB

// Handle form submission
$success = $error = '';
$uploaded_files = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id       = (int)($_POST['product_id'] ?? 0);
    $quantity         = (float)($_POST['quantity'] ?? 0);
    $task_description = trim($_POST['task_description'] ?? '');
    $needed_by        = $_POST['needed_by'] ?? '';

    // Validation
    if ($product_id <= 0 || $quantity <= 0 || empty($task_description) || empty($needed_by)) {
        $error = "All fields are required.";
    } else {
        // Handle file uploads
        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                $file_name = $_FILES['attachments']['name'][$key];
                $file_tmp  = $_FILES['attachments']['tmp_name'][$key];
                $file_size = $_FILES['attachments']['size'][$key];
                $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                // Validate file
                if (!in_array($file_ext, $allowed_types)) {
                    $error .= "Invalid file type: $file_name. Allowed: jpg, png, pdf, doc, docx.<br>";
                } elseif ($file_size > $max_size) {
                    $error .= "File too large: $file_name (max 5MB).<br>";
                } else {
                    // Unique name to prevent overwrite
                    $new_name = uniqid('req_') . '_' . time() . '.' . $file_ext;
                    $dest = $upload_dir . $new_name;

                    if (move_uploaded_file($file_tmp, $dest)) {
                        $uploaded_files[] = 'uploads/requests/' . $new_name;
                    } else {
                        $error .= "Failed to upload: $file_name.<br>";
                    }
                }
            }
        }

        // If no errors, save request
        if (empty($error)) {
            try {
                $attachments_json = !empty($uploaded_files) ? json_encode($uploaded_files) : null;

                $stmt = $pdo->prepare("
                    INSERT INTO material_requests (
                        site_manager_id, product_id, quantity,
                        task_description, needed_by, attachments
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $product_id,
                    $quantity,
                    $task_description,
                    $needed_by,
                    $attachments_json
                ]);

                $success = "Material request submitted successfully! " . 
                           (!empty($uploaded_files) ? "Files uploaded: " . count($uploaded_files) : "");
            } catch (PDOException $e) {
                $error = "Error submitting request: " . $e->getMessage();
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
    <title>Request Materials - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">Request Materials for Site Task</h2>

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

    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5>New Material Request</h5>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['code']) ?> - <?= htmlspecialchars($p['name']) ?> (<?= $p['unit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Quantity Needed <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" step="0.01" min="0.01" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Needed By <span class="text-danger">*</span></label>
                        <input type="date" name="needed_by" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Task Description / Purpose <span class="text-danger">*</span></label>
                        <textarea name="task_description" class="form-control" rows="4" required 
                                  placeholder="e.g. Concrete pouring for foundation - Phase 2, Block A"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Attachments (optional - max 3 files, 5MB each)</label>
                        <input type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        <small class="form-text text-muted">Upload supporting docs/photos (e.g. task plan, drawing, site photo)</small>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-paper-plane me-2"></i> Submit Request
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg ms-3">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <a href="index.php" class="btn btn-outline-secondary mt-4">Back to Dashboard</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>