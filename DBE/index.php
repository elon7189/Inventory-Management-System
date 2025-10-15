<?php
require_once 'config.php';

$currentPage = getCurrentPage();
$navigation = getNavigation();

// Get dashboard statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$stats['total_products'] = $result->fetch_assoc()['count'];

// Total suppliers
$result = $conn->query("SELECT COUNT(*) as count FROM suppliers");
$stats['total_suppliers'] = $result->fetch_assoc()['count'];

// Total warehouses
$result = $conn->query("SELECT COUNT(*) as count FROM warehouses");
$stats['total_warehouses'] = $result->fetch_assoc()['count'];

// Total stock value
$result = $conn->query("
    SELECT SUM(s.quantity_on_hand * p.unit_cost) as total_value 
    FROM stocks s 
    JOIN products p ON s.product_id = p.product_id
");
$stats['total_stock_value'] = $result->fetch_assoc()['total_value'] ?? 0;

// Recent orders
try {
    $result = $conn->query("
        SELECT o.*, COUNT(oli.item_id) as item_count
        FROM orders o 
        LEFT JOIN order_line_items oli ON o.order_id = oli.order_id
        GROUP BY o.order_id
        ORDER BY COALESCE(o.order_date, o.date) DESC 
        LIMIT 5
    ");
    
    if ($result) {
        $recent_orders = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $recent_orders = [];
    }
} catch (mysqli_sql_exception $e) {
    // If the query fails due to missing column/table, try a simpler version
    try {
        $result = $conn->query("SELECT * FROM orders ORDER BY COALESCE(order_date, date) DESC LIMIT 5");
        if ($result) {
            $recent_orders = $result->fetch_all(MYSQLI_ASSOC);
            // Add item_count as 0 for each order since we can't count items
            foreach ($recent_orders as &$order) {
                $order['item_count'] = 0;
            }
        } else {
            $recent_orders = [];
        }
    } catch (mysqli_sql_exception $e2) {
        // If even the simple query fails, set empty array
        $recent_orders = [];
    }
}

// Low stock products
$result = $conn->query("
    SELECT p.product_name, s.quantity_on_hand, w.warehouse_name
    FROM stocks s
    JOIN products p ON s.product_id = p.product_id
    JOIN warehouses w ON s.warehouse_id = w.warehouse_id
    WHERE s.quantity_on_hand < 10
    ORDER BY s.quantity_on_hand ASC
");
$low_stock = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System - Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <h1>Inventory Management System</h1>
        <nav class="nav">
            <?php foreach ($navigation as $name => $url): ?>
                <a href="<?= $url ?>" class="<?= ($currentPage === strtolower($name)) ? 'active' : '' ?>">
                    <?= $name ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <div class="container">
        <h2>Dashboard Overview</h2>
        
        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="card">
                <h3>Total Products</h3>
                <div class="value"><?= $stats['total_products'] ?></div>
                <div class="subtitle">Active products in system</div>
            </div>
            
            <div class="card">
                <h3>Suppliers</h3>
                <div class="value"><?= $stats['total_suppliers'] ?></div>
                <div class="subtitle">Active suppliers</div>
            </div>
            
            <div class="card">
                <h3>Warehouses</h3>
                <div class="value"><?= $stats['total_warehouses'] ?></div>
                <div class="subtitle">Storage locations</div>
            </div>
            
            <div class="card">
                <h3>Stock Value</h3>
                <div class="value"><?= formatCurrency($stats['total_stock_value']) ?></div>
                <div class="subtitle">Total inventory value</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
            <!-- Recent Orders -->
            <div class="table-container">
                <h3>Recent Orders</h3>
                <?php if (empty($recent_orders)): ?>
                    <p>No orders found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Items</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><?= $order['order_id'] ?></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($order['order_date'] ?? $order['date'])) ?></td>
                                    <td><?= $order['item_count'] ?> items</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Low Stock Alert -->
            <div class="table-container">
                <h3>Low Stock Alert</h3>
                <?php if (empty($low_stock)): ?>
                    <div class="alert alert-success">
                        <strong>Good!</strong> No products are running low on stock.
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        <strong>Warning!</strong> <?= count($low_stock) ?> products are running low.
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Warehouse</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td class="stock-low"><?= $item['quantity_on_hand'] ?></td>
                                    <td><?= htmlspecialchars($item['warehouse_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="form-container" style="margin-top: 2rem;">
            <h3>Quick Actions</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="products.php" class="btn">Add New Product</a>
                <a href="orders.php" class="btn">Create New Order</a>
                <a href="stocks.php" class="btn">Update Stock Levels</a>
                <a href="suppliers.php" class="btn">Manage Suppliers</a>
            </div>
        </div>
    </div>
</body>
</html>
