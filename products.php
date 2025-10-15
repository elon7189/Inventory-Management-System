<?php
require_once 'config.php';

$currentPage = getCurrentPage();
$navigation = getNavigation();

$message = '';
$messageType = '';

// Handle Add Product
if (isset($_POST['add_product'])) {
    $sku = sanitizeInput($_POST['sku']);
    $product_name = sanitizeInput($_POST['product_name']);
    $description = sanitizeInput($_POST['description']);
    $supplier_id = (int)$_POST['supplier_id'];
    $unit_cost = (float)$_POST['unit_cost'];
    $selling_price = (float)$_POST['selling_price'];
    
    if (!empty($sku) && !empty($product_name) && $unit_cost > 0 && $selling_price > 0) {
        $stmt = $conn->prepare("INSERT INTO products (sku, product_name, description, supplier_id, unit_cost, selling_price) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssidd", $sku, $product_name, $description, $supplier_id, $unit_cost, $selling_price);
        
        if ($stmt->execute()) {
            $message = "Product added successfully!";
            $messageType = "success";
        } else {
            $message = "Error adding product: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    } else {
        $message = "Please fill in all required fields with valid values.";
        $messageType = "error";
    }
}

// Handle Update Product
if (isset($_POST['update_product'])) {
    $product_id = (int)$_POST['product_id'];
    $sku = sanitizeInput($_POST['sku']);
    $product_name = sanitizeInput($_POST['product_name']);
    $description = sanitizeInput($_POST['description']);
    $supplier_id = (int)$_POST['supplier_id'];
    $unit_cost = (float)$_POST['unit_cost'];
    $selling_price = (float)$_POST['selling_price'];
    
    $stmt = $conn->prepare("UPDATE products SET sku=?, product_name=?, description=?, supplier_id=?, unit_cost=?, selling_price=? WHERE product_id=?");
    $stmt->bind_param("sssiddi", $sku, $product_name, $description, $supplier_id, $unit_cost, $selling_price, $product_id);
    
    if ($stmt->execute()) {
        $message = "Product updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating product: " . $conn->error;
        $messageType = "error";
    }
    $stmt->close();
}

// Handle Delete Product
if (isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];
    
    // Start transaction to ensure data consistency
    $conn->begin_transaction();
    
    try {
        // First, check if product is used in any orders
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_line_items WHERE product_id = ?");
        $check_stmt->bind_param("i", $product_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($result['count'] > 0) {
            // Product is used in orders, delete order line items first
            $delete_stmt = $conn->prepare("DELETE FROM order_line_items WHERE product_id = ?");
            $delete_stmt->bind_param("i", $product_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        
        // Also delete from stocks table
        $stock_stmt = $conn->prepare("DELETE FROM stocks WHERE product_id = ?");
        $stock_stmt->bind_param("i", $product_id);
        $stock_stmt->execute();
        $stock_stmt->close();
        
        // Finally, delete the product
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            $message = "Product deleted successfully!";
            $messageType = "success";
        } else {
            $conn->rollback();
            $message = "Error deleting product: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error deleting product: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all products with supplier names
$result = $conn->query("
    SELECT p.*, s.supplier_name 
    FROM products p 
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id 
    ORDER BY p.product_name
");
$products = $result->fetch_all(MYSQLI_ASSOC);

// Get suppliers for dropdown
$result = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name");
$suppliers = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Inventory System</title>
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

        <h2>Products Management</h2>

        <!-- Add/Edit Product Form -->
        <div class="form-container">
            <h3><?= isset($_POST['edit_product']) ? 'Edit Product' : 'Add New Product' ?></h3>
            <form method="POST">
                <?php if (isset($_POST['edit_product'])): ?>
                    <input type="hidden" name="product_id" value="<?= (int)$_POST['product_id'] ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sku">SKU *</label>
                        <input type="text" id="sku" name="sku" value="<?= isset($_POST['edit_sku']) ? htmlspecialchars($_POST['edit_sku']) : '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="product_name">Product Name *</label>
                        <input type="text" id="product_name" name="product_name" value="<?= isset($_POST['edit_product_name']) ? htmlspecialchars($_POST['edit_product_name']) : '' ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"><?= isset($_POST['edit_description']) ? htmlspecialchars($_POST['edit_description']) : '' ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="supplier_id">Supplier *</label>
                        <select id="supplier_id" name="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['supplier_id'] ?>" 
                                    <?= (isset($_POST['edit_supplier_id']) && $_POST['edit_supplier_id'] == $supplier['supplier_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['supplier_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="unit_cost">Unit Cost *</label>
                        <input type="number" id="unit_cost" name="unit_cost" step="0.01" min="0" value="<?= isset($_POST['edit_unit_cost']) ? $_POST['edit_unit_cost'] : '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="selling_price">Selling Price *</label>
                        <input type="number" id="selling_price" name="selling_price" step="0.01" min="0" value="<?= isset($_POST['edit_selling_price']) ? $_POST['edit_selling_price'] : '' ?>" required>
                    </div>
                </div>
                
                <button type="submit" name="<?= isset($_POST['edit_product']) ? 'update_product' : 'add_product' ?>" class="btn">
                    <?= isset($_POST['edit_product']) ? 'Update Product' : 'Add Product' ?>
                </button>
                
                <?php if (isset($_POST['edit_product'])): ?>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Products Table -->
        <div class="table-container">
            <h3>All Products</h3>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Supplier</th>
                        <th>Unit Cost</th>
                        <th>Selling Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">No products found. Add your first product above.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['sku']) ?></td>
                                <td><?= htmlspecialchars($product['product_name']) ?></td>
                                <td><?= htmlspecialchars(substr($product['description'], 0, 50)) ?><?= strlen($product['description']) > 50 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars($product['supplier_name'] ?? 'N/A') ?></td>
                                <td><?= formatCurrency($product['unit_cost']) ?></td>
                                <td><?= formatCurrency($product['selling_price']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                        <input type="hidden" name="edit_sku" value="<?= htmlspecialchars($product['sku']) ?>">
                                        <input type="hidden" name="edit_product_name" value="<?= htmlspecialchars($product['product_name']) ?>">
                                        <input type="hidden" name="edit_description" value="<?= htmlspecialchars($product['description']) ?>">
                                        <input type="hidden" name="edit_supplier_id" value="<?= $product['supplier_id'] ?>">
                                        <input type="hidden" name="edit_unit_cost" value="<?= $product['unit_cost'] ?>">
                                        <input type="hidden" name="edit_selling_price" value="<?= $product['selling_price'] ?>">
                                        <button type="submit" name="edit_product" class="btn btn-sm btn-secondary">Edit</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product? This will also remove it from all orders and stock records.')">
                                        <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                        <button type="submit" name="delete_product" class="btn btn-sm btn-danger">Delete</button>
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
