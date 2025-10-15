<?php
require_once 'config.php';

$currentPage = getCurrentPage();
$navigation = getNavigation();

$message = '';
$messageType = '';

// Handle Update Stock
if (isset($_POST['update_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Check if stock record exists
    $check_stmt = $conn->prepare("SELECT stock_id FROM stocks WHERE product_id = ? AND warehouse_id = ?");
    $check_stmt->bind_param("ii", $product_id, $warehouse_id);
    $check_stmt->execute();
    $existing_stock = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing_stock) {
        // Update existing stock
        $stmt = $conn->prepare("UPDATE stocks SET quantity_on_hand = ? WHERE product_id = ? AND warehouse_id = ?");
        $stmt->bind_param("iii", $quantity, $product_id, $warehouse_id);
    } else {
        // Create new stock record
        $stmt = $conn->prepare("INSERT INTO stocks (product_id, warehouse_id, quantity_on_hand) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $product_id, $warehouse_id, $quantity);
    }
    
    if ($stmt->execute()) {
        $message = "Stock updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating stock: " . $conn->error;
        $messageType = "error";
    }
    $stmt->close();
    $check_stmt->close();
}

// Handle Delete Stock Record
if (isset($_POST['delete_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    
    $stmt = $conn->prepare("DELETE FROM stocks WHERE product_id = ? AND warehouse_id = ?");
    $stmt->bind_param("ii", $product_id, $warehouse_id);
    
    if ($stmt->execute()) {
        $message = "Stock record deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Error deleting stock record: " . $conn->error;
        $messageType = "error";
    }
    $stmt->close();
}

// Get all stock levels with product and warehouse information
$result = $conn->query("
    SELECT s.*, p.product_name, p.sku, p.unit_cost, p.selling_price, w.warehouse_name
    FROM stocks s
    JOIN products p ON s.product_id = p.product_id
    JOIN warehouses w ON s.warehouse_id = w.warehouse_id
    ORDER BY p.product_name, w.warehouse_name
");
$stocks = $result->fetch_all(MYSQLI_ASSOC);

// Get products and warehouses for dropdowns
$products_result = $conn->query("SELECT product_id, product_name, sku FROM products ORDER BY product_name");
$products = $products_result->fetch_all(MYSQLI_ASSOC);

$warehouses_result = $conn->query("SELECT warehouse_id, warehouse_name FROM warehouses ORDER BY warehouse_name");
$warehouses = $warehouses_result->fetch_all(MYSQLI_ASSOC);

// Calculate total stock value
$total_stock_value = 0;
foreach ($stocks as $stock) {
    $total_stock_value += $stock['quantity_on_hand'] * $stock['unit_cost'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - Inventory System</title>
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
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <h2>Stock Management</h2>

        <!-- Stock Overview -->
        <div class="dashboard-grid">
            <div class="card">
                <h3>Total Stock Value</h3>
                <div class="value"><?= formatCurrency($total_stock_value) ?></div>
                <div class="subtitle">Current inventory value</div>
            </div>
            
            <div class="card">
                <h3>Total Items</h3>
                <div class="value"><?= count($stocks) ?></div>
                <div class="subtitle">Stock records</div>
            </div>
        </div>

        <!-- Update Stock Form -->
        <div class="form-container">
            <h3><?= isset($_POST['edit_stock']) ? 'Edit Stock Level' : 'Update Stock Level' ?></h3>
            <form method="POST">
                <?php if (isset($_POST['edit_stock'])): ?>
                    <input type="hidden" name="product_id" value="<?= (int)$_POST['edit_product_id'] ?>">
                    <input type="hidden" name="warehouse_id" value="<?= (int)$_POST['edit_warehouse_id'] ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="product_id">Product *</label>
                        <select id="product_id" name="product_id" required <?= isset($_POST['edit_stock']) ? 'disabled' : '' ?>>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['product_id'] ?>" 
                                    <?= (isset($_POST['edit_product_id']) && $_POST['edit_product_id'] == $product['product_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($product['sku'] . ' - ' . $product['product_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="warehouse_id">Warehouse *</label>
                        <select id="warehouse_id" name="warehouse_id" required <?= isset($_POST['edit_stock']) ? 'disabled' : '' ?>>
                            <option value="">Select Warehouse</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?= $warehouse['warehouse_id'] ?>" 
                                    <?= (isset($_POST['edit_warehouse_id']) && $_POST['edit_warehouse_id'] == $warehouse['warehouse_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" min="0" value="<?= isset($_POST['edit_quantity']) ? $_POST['edit_quantity'] : '' ?>" required>
                    </div>
                </div>
                
                <button type="submit" name="update_stock" class="btn">
                    <?= isset($_POST['edit_stock']) ? 'Update Stock' : 'Update Stock' ?>
                </button>
                
                <?php if (isset($_POST['edit_stock'])): ?>
                    <a href="stocks.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Stock Levels Table -->
        <div class="table-container">
            <h3>Current Stock Levels</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Warehouse</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Selling Price</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stocks)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem;">No stock records found. Update stock levels above.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stocks as $stock): ?>
                            <?php
                            $total_value = $stock['quantity_on_hand'] * $stock['unit_cost'];
                            $status_class = '';
                            $status_text = '';
                            
                            if ($stock['quantity_on_hand'] == 0) {
                                $status_class = 'stock-low';
                                $status_text = 'Out of Stock';
                            } elseif ($stock['quantity_on_hand'] < 5) {
                                $status_class = 'stock-low';
                                $status_text = 'Low Stock';
                            } elseif ($stock['quantity_on_hand'] < 20) {
                                $status_class = 'stock-medium';
                                $status_text = 'Medium';
                            } else {
                                $status_class = 'stock-good';
                                $status_text = 'Good';
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($stock['product_name']) ?></td>
                                <td><?= htmlspecialchars($stock['sku']) ?></td>
                                <td><?= htmlspecialchars($stock['warehouse_name']) ?></td>
                                <td><?= $stock['quantity_on_hand'] ?></td>
                                <td><?= formatCurrency($stock['unit_cost']) ?></td>
                                <td><?= formatCurrency($stock['selling_price']) ?></td>
                                <td><?= formatCurrency($total_value) ?></td>
                                <td class="<?= $status_class ?>"><?= $status_text ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="edit_product_id" value="<?= $stock['product_id'] ?>">
                                        <input type="hidden" name="edit_warehouse_id" value="<?= $stock['warehouse_id'] ?>">
                                        <input type="hidden" name="edit_quantity" value="<?= $stock['quantity_on_hand'] ?>">
                                        <button type="submit" name="edit_stock" class="btn btn-sm btn-secondary">Edit</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this stock record?')">
                                        <input type="hidden" name="product_id" value="<?= $stock['product_id'] ?>">
                                        <input type="hidden" name="warehouse_id" value="<?= $stock['warehouse_id'] ?>">
                                        <button type="submit" name="delete_stock" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
