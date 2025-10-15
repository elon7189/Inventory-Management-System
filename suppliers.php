<?php
require_once 'config.php';

$currentPage = getCurrentPage();
$navigation = getNavigation();

$message = '';
$messageType = '';

// Handle Add Supplier
if (isset($_POST['add_supplier'])) {
    $supplier_name = sanitizeInput($_POST['supplier_name']);
    $contact_info = sanitizeInput($_POST['contact_info']);
    
    if (!empty($supplier_name)) {
        try {
            $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_info) VALUES (?, ?)");
            $stmt->bind_param("ss", $supplier_name, $contact_info);
            
            if ($stmt->execute()) {
                $message = "Supplier added successfully!";
                $messageType = "success";
            } else {
                $message = "Error adding supplier: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // If contact_info column doesn't exist, try without it
            try {
                $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name) VALUES (?)");
                $stmt->bind_param("s", $supplier_name);
                
                if ($stmt->execute()) {
                    $message = "Supplier added successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error adding supplier: " . $conn->error;
                    $messageType = "error";
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e2) {
                $message = "Error adding supplier: " . $e2->getMessage();
                $messageType = "error";
            }
        }
    } else {
        $message = "Supplier name is required.";
        $messageType = "error";
    }
}

// Handle Update Supplier
if (isset($_POST['update_supplier'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $supplier_name = sanitizeInput($_POST['supplier_name']);
    $contact_info = sanitizeInput($_POST['contact_info']);
    
    try {
        $stmt = $conn->prepare("UPDATE suppliers SET supplier_name=?, contact_info=? WHERE supplier_id=?");
        $stmt->bind_param("ssi", $supplier_name, $contact_info, $supplier_id);
        
        if ($stmt->execute()) {
            $message = "Supplier updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating supplier: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        // If contact_info column doesn't exist, try without it
        try {
            $stmt = $conn->prepare("UPDATE suppliers SET supplier_name=? WHERE supplier_id=?");
            $stmt->bind_param("si", $supplier_name, $supplier_id);
            
            if ($stmt->execute()) {
                $message = "Supplier updated successfully!";
                $messageType = "success";
            } else {
                $message = "Error updating supplier: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e2) {
            $message = "Error updating supplier: " . $e2->getMessage();
            $messageType = "error";
        }
    }
}

// Handle Delete Supplier
if (isset($_POST['delete_supplier'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    
    // Start transaction to handle foreign key constraints
    $conn->begin_transaction();
    
    try {
        // Check if supplier has products
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE supplier_id = ?");
        $check_stmt->bind_param("i", $supplier_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        // Check if supplier has orders
        $order_check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE supplier_id = ?");
        $order_check_stmt->bind_param("i", $supplier_id);
        $order_check_stmt->execute();
        $order_result = $order_check_stmt->get_result()->fetch_assoc();
        $order_check_stmt->close();
        
        if ($result['count'] > 0 || $order_result['count'] > 0) {
            $conn->rollback();
            $message = "Cannot delete supplier. They have " . ($result['count'] + $order_result['count']) . " products and orders assigned to them.";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
            $stmt->bind_param("i", $supplier_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $message = "Supplier deleted successfully!";
                $messageType = "success";
            } else {
                $conn->rollback();
                $message = "Error deleting supplier: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error deleting supplier: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all suppliers
$result = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name");
$suppliers = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers Management - Inventory System</title>
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

        <h2>Suppliers Management</h2>

        <!-- Add/Edit Supplier Form -->
        <div class="form-container">
            <h3><?= isset($_POST['edit_supplier']) ? 'Edit Supplier' : 'Add New Supplier' ?></h3>
            <form method="POST">
                <?php if (isset($_POST['edit_supplier'])): ?>
                    <input type="hidden" name="supplier_id" value="<?= (int)$_POST['supplier_id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="supplier_name">Supplier Name *</label>
                    <input type="text" id="supplier_name" name="supplier_name" value="<?= isset($_POST['edit_supplier_name']) ? htmlspecialchars($_POST['edit_supplier_name']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="contact_info">Contact Information</label>
                    <textarea id="contact_info" name="contact_info" rows="3" placeholder="Phone, email, address, etc."><?= isset($_POST['edit_contact_info']) ? htmlspecialchars($_POST['edit_contact_info']) : '' ?></textarea>
                </div>
                
                <button type="submit" name="<?= isset($_POST['edit_supplier']) ? 'update_supplier' : 'add_supplier' ?>" class="btn">
                    <?= isset($_POST['edit_supplier']) ? 'Update Supplier' : 'Add Supplier' ?>
                </button>
                
                <?php if (isset($_POST['edit_supplier'])): ?>
                    <a href="suppliers.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Suppliers Table -->
        <div class="table-container">
            <h3>All Suppliers</h3>
            <table>
                <thead>
                    <tr>
                        <th>Supplier ID</th>
                        <th>Supplier Name</th>
                        <th>Contact Information</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem;">No suppliers found. Add your first supplier above.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?= $supplier['supplier_id'] ?></td>
                                <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                                <td><?= htmlspecialchars($supplier['contact_info'] ?? '') ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="supplier_id" value="<?= $supplier['supplier_id'] ?>">
                                        <input type="hidden" name="edit_supplier_name" value="<?= htmlspecialchars($supplier['supplier_name']) ?>">
                                        <input type="hidden" name="edit_contact_info" value="<?= htmlspecialchars($supplier['contact_info'] ?? '') ?>">
                                        <button type="submit" name="edit_supplier" class="btn btn-sm btn-secondary">Edit</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this supplier?')">
                                        <input type="hidden" name="supplier_id" value="<?= $supplier['supplier_id'] ?>">
                                        <button type="submit" name="delete_supplier" class="btn btn-sm btn-danger">Delete</button>
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
