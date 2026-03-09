<?php
// products.php - Manage Products (Read-Only for Financial Manager)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$role = $_SESSION['role_name'] ?? 'unknown';
$can_add_edit = in_array($role, ['administrator', 'storekeeper']);
$can_delete   = ($role === 'administrator');
$read_only    = ($role === 'financial_manager');

// Fetch all products
$stmt = $pdo->query("SELECT * FROM products ORDER BY name ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle ADD (only if allowed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add' && $can_add_edit) {
    $code      = trim($_POST['code'] ?? '');
    $name      = trim($_POST['name'] ?? '');
    $category  = trim($_POST['category'] ?? 'General');
    $unit      = trim($_POST['unit'] ?? 'pcs');
    $min_stock = (float)($_POST['min_stock'] ?? 0);
    $unit_cost = (float)($_POST['unit_cost'] ?? 0);

    if (empty($code) || empty($name)) {
        $add_error = "Code and Name are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (code, name, category, unit, min_stock, unit_cost)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, $name, $category, $unit, $min_stock, $unit_cost]);
            header("Location: products.php?msg=Product+added+successfully");
            exit;
        } catch (PDOException $e) {
            $add_error = ($e->getCode() == 23000) ? "Code '$code' already exists." : $e->getMessage();
        }
    }
}

// Handle EDIT (only if allowed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit' && $can_add_edit) {
    $id        = (int)$_POST['id'];
    $code      = trim($_POST['code'] ?? '');
    $name      = trim($_POST['name'] ?? '');
    $category  = trim($_POST['category'] ?? 'General');
    $unit      = trim($_POST['unit'] ?? 'pcs');
    $min_stock = (float)($_POST['min_stock'] ?? 0);
    $unit_cost = (float)($_POST['unit_cost'] ?? 0);

    if ($id > 0 && !empty($code) && !empty($name)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET code = ?, name = ?, category = ?, unit = ?, min_stock = ?, unit_cost = ?
                WHERE id = ?
            ");
            $stmt->execute([$code, $name, $category, $unit, $min_stock, $unit_cost, $id]);
            header("Location: products.php?msg=Product+updated+successfully");
            exit;
        } catch (PDOException $e) {
            $edit_error = $e->getMessage();
        }
    }
}

// Handle DELETE (admin only)
if (isset($_GET['delete']) && $can_delete) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: products.php?msg=Product+deleted+successfully");
        exit;
    } catch (PDOException $e) {
        $delete_error = "Cannot delete: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">
        <?= $read_only ? 'View Products (Read Only)' : 'Manage Products' ?>
    </h2>

    <!-- Messages -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_GET['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($add_error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($add_error) ?></div>
    <?php endif; ?>

    <?php if (isset($edit_error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($edit_error) ?></div>
    <?php endif; ?>

    <?php if (isset($delete_error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($delete_error) ?></div>
    <?php endif; ?>

    <!-- Add Product Form - only if allowed -->
    <?php if ($can_add_edit): ?>
    <div class="card mb-5 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Add New Product</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="add">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required maxlength="50">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" value="General">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-control" value="pcs" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Min Stock</label>
                        <input type="number" name="min_stock" class="form-control" value="10" step="0.01" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit Cost (TZS)</label>
                        <input type="number" name="unit_cost" class="form-control" value="0.00" step="0.01" min="0">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Products Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Products List (<?= count($products) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Supplier</th>
                            <th>Unit</th>
                            <th class="text-end">Min Stock</th>
                            <th class="text-end">Unit Cost</th>
                            <?php if ($can_add_edit || $can_delete): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="<?= $can_add_edit || $can_delete ? 7 : 6 ?>" class="text-center py-4">No products yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['code']) ?></strong></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= htmlspecialchars($p['category']) ?></td>
                                <td><?= htmlspecialchars($p['unit']) ?></td>
                                <td class="text-end"><?= number_format($p['min_stock'], 2) ?></td>
                                <td class="text-end"><?= number_format($p['unit_cost'], 2) ?></td>
                                <?php if ($can_add_edit || $can_delete): ?>
                                    <td>
                                        <?php if ($can_add_edit): ?>
                                            <button class="btn btn-sm btn-warning edit-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal"
                                                    data-id="<?= $p['id'] ?>"
                                                    data-code="<?= htmlspecialchars($p['code']) ?>"
                                                    data-name="<?= htmlspecialchars($p['name']) ?>"
                                                    data-category="<?= htmlspecialchars($p['category']) ?>"
                                                    data-unit="<?= htmlspecialchars($p['unit']) ?>"
                                                    data-min="<?= $p['min_stock'] ?>"
                                                    data-cost="<?= $p['unit_cost'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($can_delete): ?>
                                            <a href="?delete=<?= $p['id'] ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Delete <?= htmlspecialchars(addslashes($p['name'])) ?>? This cannot be undone.')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal - only if allowed -->
    <?php if ($can_add_edit): ?>
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">

                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" id="edit_code" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="edit_category" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit</label>
                            <input type="text" name="unit" id="edit_unit" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Min Stock</label>
                            <input type="number" name="min_stock" id="edit_min_stock" class="form-control" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit Cost</label>
                            <input type="number" name="unit_cost" id="edit_unit_cost" class="form-control" step="0.01">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value        = this.dataset.id;
        document.getElementById('edit_code').value      = this.dataset.code;
        document.getElementById('edit_name').value      = this.dataset.name;
        document.getElementById('edit_category').value  = this.dataset.category;
        document.getElementById('edit_unit').value      = this.dataset.unit;
        document.getElementById('edit_min_stock').value = this.dataset.min;
        document.getElementById('edit_unit_cost').value = this.dataset.cost;
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>