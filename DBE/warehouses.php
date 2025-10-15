<?php
require_once 'config.php';

$currentPage = getCurrentPage();
$navigation = getNavigation();

$message = '';
$messageType = '';

// Handle Add Warehouse
if (isset($_POST['add_warehouse'])) {
    $warehouse_name = sanitizeInput($_POST['warehouse_name']);
    $location = sanitizeInput($_POST['location']);
    
    if (!empty($warehouse_name) && !empty($location)) {
        $stmt = $conn->prepare("INSERT INTO warehouses (warehouse_name, location) VALUES (?, ?)");
        $stmt->bind_param("ss", $warehouse_name, $location);
        
        if ($stmt->execute()) {
            $message = "Warehouse added successfully!";
            $messageType = "success";
        } else {
            $message = "Error adding warehouse: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    } else {
        $message = "Warehouse name and location are required.";
        $messageType = "error";
    }
}

// Handle Update Warehouse
if (isset($_POST['update_warehouse'])) {
    $warehouse_id = (int)$_POST['warehouse_id'];
    $warehouse_name = sanitizeInput($_POST['warehouse_name']);
    $location = sanitizeInput($_POST['location']);
    
    $stmt = $conn->prepare("UPDATE warehouses SET warehouse_name=?, location=? WHERE warehouse_id=?");
    $stmt->bind_param("ssi", $warehouse_name, $location, $warehouse_id);
    
    if ($stmt->execute()) {
        $message = "Warehouse updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating warehouse: " . $conn->error;
        $messageType = "error";
    }
    $stmt->close();
}

// Handle Delete Warehouse
if (isset($_POST['delete_warehouse'])) {
    $warehouse_id = (int)$_POST['warehouse_id'];
    
    // Check if warehouse has stock
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM stocks WHERE warehouse_id = ? AND quantity_on_hand > 0");
    $check_stmt->bind_param("i", $warehouse_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $message = "Cannot delete warehouse. It contains inventory with " . $result['count'] . " products in stock.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM warehouses WHERE warehouse_id = ?");
        $stmt->bind_param("i", $warehouse_id);
        
        if ($stmt->execute()) {
            $message = "Warehouse deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error deleting warehouse: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Get all warehouses
$result = $conn->query("SELECT * FROM warehouses ORDER BY warehouse_name");
$warehouses = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouses Management - Inventory System</title>
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

        <h2>Warehouses Management</h2>

        <!-- Add/Edit Warehouse Form -->
        <div class="form-container">
            <h3><?= isset($_POST['edit_warehouse']) ? 'Edit Warehouse' : 'Add New Warehouse' ?></h3>
            <form method="POST">
                <?php if (isset($_POST['edit_warehouse'])): ?>
                    <input type="hidden" name="warehouse_id" value="<?= (int)$_POST['warehouse_id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="warehouse_name">Warehouse Name *</label>
                    <input type="text" id="warehouse_name" name="warehouse_name" value="<?= isset($_POST['edit_warehouse_name']) ? htmlspecialchars($_POST['edit_warehouse_name']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="location">Location *</label>
                    <textarea id="location" name="location" rows="3" placeholder="Full address and location details" required><?= isset($_POST['edit_location']) ? htmlspecialchars($_POST['edit_location']) : '' ?></textarea>
                </div>
                
                <button type="submit" name="<?= isset($_POST['edit_warehouse']) ? 'update_warehouse' : 'add_warehouse' ?>" class="btn">
                    <?= isset($_POST['edit_warehouse']) ? 'Update Warehouse' : 'Add Warehouse' ?>
                </button>
                
                <?php if (isset($_POST['edit_warehouse'])): ?>
                    <a href="warehouses.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Warehouses Table -->
        <div class="table-container">
            <h3>All Warehouses</h3>
            <table>
                <thead>
                    <tr>
                        <th>Warehouse ID</th>
                        <th>Warehouse Name</th>
                        <th>Location</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($warehouses)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem;">No warehouses found. Add your first warehouse above.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <tr>
                                <td><?= $warehouse['warehouse_id'] ?></td>
                                <td><?= htmlspecialchars($warehouse['warehouse_name']) ?></td>
                                <td><?= htmlspecialchars($warehouse['location']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="warehouse_id" value="<?= $warehouse['warehouse_id'] ?>">
                                        <input type="hidden" name="edit_warehouse_name" value="<?= htmlspecialchars($warehouse['warehouse_name']) ?>">
                                        <input type="hidden" name="edit_location" value="<?= htmlspecialchars($warehouse['location']) ?>">
                                        <button type="submit" name="edit_warehouse" class="btn btn-sm btn-secondary">Edit</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this warehouse?')">
                                        <input type="hidden" name="warehouse_id" value="<?= $warehouse['warehouse_id'] ?>">
                                        <button type="submit" name="delete_warehouse" class="btn btn-sm btn-danger">Delete</button>
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
