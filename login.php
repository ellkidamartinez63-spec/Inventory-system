<?php
// login.php – fully compatible with your current users table

session_start();

require_once 'includes/config.php';
require_once 'includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.username,
                    u.full_name,
                    u.password_hash,
                    r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.username = ? 
                  AND u.active = 1
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // ── Successful login ────────────────────────────────────────
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['username']    = $user['username'];
                $_SESSION['full_name']   = $user['full_name'];
                $_SESSION['role_name']   = $user['role_name'];

                // Update last login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                    ->execute([$user['id']]);

                // Regenerate session ID for security
                session_regenerate_id(true);

                header("Location: index.php?msg=Login+successful");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockFlow - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { background: white; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; }
        .login-header { background: #0d6efd; color: white; padding: 2rem; text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="login-card mt-5">
                <div class="login-header">
                    <h3>StockFlow</h3>
                    <p class="mb-0">On-site Inventory System</p>
                </div>
                <div class="card-body p-4 p-md-5">

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Username</label>
                            <input type="text" name="username" class="form-control form-control-lg" 
                                   required autofocus placeholder="Enter your username">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg" 
                                   required placeholder="••••••••">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>