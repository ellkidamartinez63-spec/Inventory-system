<?php
// users.php - Manage Users (Admin only)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

// Only administrator can manage users
if ($_SESSION['role_name'] !== 'administrator') {
    header("Location: index.php?error=Access denied - Administrator only");
    exit;
}

// Fetch all users + their roles
try {
    $stmt = $pdo->query("
        SELECT 
            u.id, u.username, u.full_name, u.email,
            r.role_name, u.active, u.last_login, u.created_at
        FROM users u
        JOIN roles r ON u.role_id = r.id
        ORDER BY u.full_name ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading users: " . $e->getMessage();
}

// Fetch all available roles for dropdown
$roles_stmt = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name");
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission - Add new user
$add_success = $add_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username     = trim($_POST['username'] ?? '');
    $full_name    = trim($_POST['full_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $plain_pass   = $_POST['password'] ?? '';
    $role_id      = (int)($_POST['role_id'] ?? 0);
    $active       = isset($_POST['active']) ? 1 : 0;

    // Basic validation
    if (empty($username) || empty($full_name) || empty($plain_pass) || $role_id <= 0) {
        $add_error = "All required fields must be filled.";
    } elseif (strlen($plain_pass) < 6) {
        $add_error = "Password must be at least 6 characters.";
    } else {
        try {
            // Check if username or email already exists
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR (email = ? AND email IS NOT NULL)");
            $check->execute([$username, $email]);
            if ($check->fetchColumn() > 0) {
                $add_error = "Username or email already exists.";
            } else {
                $hash = password_hash($plain_pass, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users 
                    (username, full_name, email, password_hash, role_id, active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$username, $full_name, $email ?: null, $hash, $role_id, $active]);

                $add_success = "User '$username' created successfully!";
            }
        } catch (PDOException $e) {
            $add_error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - StockFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4">User Management <small class="text-muted">(Administrator only)</small></h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($add_success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($add_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($add_error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($add_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add User Form -->
    <div class="card mb-5 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Add New User</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required maxlength="50">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" id="active" value="1" checked>
                            <label class="form-check-label" for="active">Active</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" name="add_user" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Create User
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Existing Users (<?= count($users) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Active</th>
                            <th>Last Login</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center py-4">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($u['role_name']) ?></td>
                                <td>
                                    <span class="badge <?= $u['active'] ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $u['active'] ? 'Yes' : 'No' ?>
                                    </span>
                                </td>
                                <td><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
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