<?php
// export.php - Real CSV Export for Admin

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

// Only admin can export
if ($_SESSION['role_name'] !== 'administrator') {
    header("Location: index.php?error=Access denied");
    exit;
}

$report = $_GET['report'] ?? '';

if ($report === 'stock') {
    $filename = "current_stock_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // CSV Headers
    fputcsv($output, [
        'Code', 'Name', 'Category', 'Unit', 'Current Qty', 
        'Min Stock', 'Unit Cost', 'Total Value', 'Status'
    ]);

    $stmt = $pdo->query("SELECT * FROM current_stock ORDER BY name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['current_qty'] <= 0 ? 'OUT OF STOCK' 
                : ($row['current_qty'] <= $row['min_stock'] ? 'LOW STOCK' : 'OK');
        $value = $row['current_qty'] * $row['unit_cost'];

        fputcsv($output, [
            $row['code'],
            $row['name'],
            $row['category'],
            $row['unit'],
            number_format($row['current_qty'], 2),
            number_format($row['min_stock'], 2),
            number_format($row['unit_cost'], 2),
            number_format($value, 2),
            $status
        ]);
    }

    fclose($output);
    exit;

} elseif ($report === 'transactions') {
    $filename = "transactions_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'Date', 'Type', 'Product', 'Quantity', 'Reference', 'User', 'Notes'
    ]);

    $stmt = $pdo->query("
        SELECT t.transaction_date, t.type, t.quantity, t.reference, t.notes,
               p.name AS product_name, u.full_name AS user_name
        FROM stock_transactions t
        JOIN products p ON t.product_id = p.id
        JOIN users u ON t.user_id = u.id
        ORDER BY t.transaction_date DESC
        LIMIT 100
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            date('Y-m-d H:i:s', strtotime($row['transaction_date'])),
            $row['type'],
            $row['product_name'],
            number_format($row['quantity'], 2),
            $row['reference'] ?: '-',
            $row['user_name'],
            $row['notes'] ?: '-'
        ]);
    }
    } elseif ($report === 'feedback') {
    // Export Site Feedback (CSV)
    $filename = "site_feedback_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, [
        'Date', 'Site Manager', 'Products Used', 'Work Done', 
        'Challenges', 'Successes'
    ]);

    $stmt = $pdo->query("
        SELECT f.created_at, u.full_name AS site_manager,
               f.products_used, f.work_done, f.challenges, f.successes
        FROM site_feedback f
        JOIN users u ON f.site_manager_id = u.id
        ORDER BY f.created_at DESC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            date('Y-m-d H:i', strtotime($row['created_at'])),
            $row['site_manager'],
            $row['products_used'],
            $row['work_done'],
            $row['challenges'],
            $row['successes']
        ]);
    }

    

    

    fclose($output);
    exit;

} else {
    header("Location: index.php?error=Invalid report");
    exit;
}