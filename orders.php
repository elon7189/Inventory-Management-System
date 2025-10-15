<?php
require_once 'config.php';

$currentPage = getCurrentPage();
$navigation = getNavigation();

$message = '';
$messageType = '';

// Handle Create Order
if (isset($_POST['create_order'])) {
    $customer_name = sanitizeInput($_POST['customer_name']);
    $shipping_address = sanitizeInput($_POST['shipping_address'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $date = date('Y-m-d H:i:s');
    
    if (!empty($customer_name) && !empty($_POST['product_ids'])) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert order - try with actual column names first
            try {
                $stmt = $conn->prepare("INSERT INTO orders (order_date, customer_name, shipping_address, supplier_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $date, $customer_name, $shipping_address, $supplier_id);
                $stmt->execute();
                $order_id = $conn->insert_id;
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                // Fallback to expected column name
                $stmt = $conn->prepare("INSERT INTO orders (date, customer_name) VALUES (?, ?)");
                $stmt->bind_param("ss", $date, $customer_name);
                $stmt->execute();
                $order_id = $conn->insert_id;
                $stmt->close();
            }
            
            // Insert order line items and update stock
            $product_ids = $_POST['product_ids'];
            $quantities = $_POST['quantities'];
            $warehouse_id = (int)$_POST['warehouse_id'];
            
            for ($i = 0; $i < count($product_ids); $i++) {
                if (!empty($product_ids[$i]) && !empty($quantities[$i]) && $quantities[$i] > 0) {
                    $product_id = (int)$product_ids[$i];
                    $quantity = (int)$quantities[$i];
                    
                    // Validate that product exists
                    $check_stmt = $conn->prepare("SELECT product_id FROM products WHERE product_id = ?");
                    $check_stmt->bind_param("i", $product_id);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        $check_stmt->close();
                        throw new Exception("Product ID {$product_id} does not exist. Please check the product ID and try again.");
                    }
                    $check_stmt->close();
                    
                    // Get product price for unit_price
                    $price_stmt = $conn->prepare("SELECT selling_price FROM products WHERE product_id = ?");
                    $price_stmt->bind_param("i", $product_id);
                    $price_stmt->execute();
                    $price_result = $price_stmt->get_result()->fetch_assoc();
                    $unit_price = $price_result['selling_price'];
                    $price_stmt->close();
                    
                    // Insert order line item
                    $stmt = $conn->prepare("INSERT INTO order_line_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiid", $order_id, $product_id, $quantity, $unit_price);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Update stock (deduct from warehouse)
                    $stmt = $conn->prepare("UPDATE stocks SET quantity_on_hand = quantity_on_hand - ? WHERE product_id = ? AND warehouse_id = ?");
                    $stmt->bind_param("iii", $quantity, $product_id, $warehouse_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Commit transaction
            $conn->commit();
            $message = "Order #{$order_id} created successfully!";
            $messageType = "success";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = "Error creating order: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Customer name and at least one product are required.";
        $messageType = "error";
    }
}

// Handle Delete Order
if (isset($_POST['delete_order'])) {
    $order_id = (int)$_POST['order_id'];
    
    $conn->begin_transaction();
    
    try {
        // Get order line items to restore stock
        $stmt = $conn->prepare("SELECT product_id, quantity FROM order_line_items WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $line_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Restore stock (assuming main warehouse for simplicity)
        $main_warehouse_id = 1; // You might want to store this per order
        foreach ($line_items as $item) {
            $stmt = $conn->prepare("UPDATE stocks SET quantity_on_hand = quantity_on_hand + ? WHERE product_id = ? AND warehouse_id = ?");
            $stmt->bind_param("iii", $item['quantity'], $item['product_id'], $main_warehouse_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Delete order (cascades to line items)
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        $message = "Order deleted successfully!";
        $messageType = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error deleting order: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all orders with item counts
try {
    $result = $conn->query("
        SELECT o.*, COUNT(oli.item_id) as item_count, SUM(oli.quantity * oli.unit_price) as total_value
        FROM orders o 
        LEFT JOIN order_line_items oli ON o.order_id = oli.order_id
        GROUP BY o.order_id
        ORDER BY COALESCE(o.order_date, o.date) DESC
    ");
    
    if ($result) {
        $orders = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $orders = [];
    }
} catch (mysqli_sql_exception $e) {
    // If the query fails due to missing column/table, try a simpler version
    try {
        $result = $conn->query("SELECT * FROM orders ORDER BY COALESCE(order_date, date) DESC");
        if ($result) {
            $orders = $result->fetch_all(MYSQLI_ASSOC);
            // Add item_count and total_value as 0 for each order since we can't calculate them
            foreach ($orders as &$order) {
                $order['item_count'] = 0;
                $order['total_value'] = 0;
            }
        } else {
            $orders = [];
        }
    } catch (mysqli_sql_exception $e2) {
        // If even the simple query fails, set empty array
        $orders = [];
    }
}

// Get products, warehouses, and suppliers for dropdowns
$products_result = $conn->query("SELECT product_id, product_name, sku, selling_price, supplier_id FROM products ORDER BY product_name");
$products = $products_result->fetch_all(MYSQLI_ASSOC);

$warehouses_result = $conn->query("SELECT warehouse_id, warehouse_name FROM warehouses ORDER BY warehouse_name");
$warehouses = $warehouses_result->fetch_all(MYSQLI_ASSOC);

$suppliers_result = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name");
$suppliers = $suppliers_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Inventory System</title>
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

        <h2>Orders Management</h2>

        <!-- Available Products Reference -->
        <div class="form-container" style="background: #f8f9fa; border: 1px solid #dee2e6;">
            <h4>Available Products</h4>
            <p><em>Click on a product to auto-fill the form below:</em></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <?php foreach ($products as $product): ?>
                    <div class="product-card" style="padding: 1rem; background: white; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.3s;" 
                         onclick="selectProduct(<?= $product['product_id'] ?>, <?= $product['supplier_id'] ?? 'null' ?>)"
                         onmouseover="this.style.borderColor='#007bff'; this.style.transform='scale(1.02)'"
                         onmouseout="this.style.borderColor='#ddd'; this.style.transform='scale(1)'">
                        <div style="font-size: 1.2em; font-weight: bold; color: #007bff; margin-bottom: 0.5rem;">
                            Product ID: <?= $product['product_id'] ?>
                        </div>
                        <div style="font-weight: bold; margin-bottom: 0.3rem;">
                            <?= htmlspecialchars($product['sku']) ?> - <?= htmlspecialchars($product['product_name']) ?>
                        </div>
                        <div style="color: #666; font-size: 0.9em; margin-bottom: 0.3rem;">
                            Price: $<?= $product['selling_price'] ?>
                        </div>
                        <div style="color: #28a745; font-size: 0.8em;">
                            Supplier ID: <?= $product['supplier_id'] ?? 'N/A' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Available Suppliers Reference -->
        <div class="form-container" style="background: #fff3cd; border: 1px solid #ffeaa7;">
            <h4>Available Suppliers</h4>
            <p><em>Use these Supplier IDs for purchase orders:</em></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <?php foreach ($suppliers as $supplier): ?>
                    <div style="padding: 0.8rem; background: white; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="font-weight: bold; color: #856404; margin-bottom: 0.3rem;">
                            Supplier ID: <?= $supplier['supplier_id'] ?>
                        </div>
                        <div style="font-size: 0.9em;">
                            <?= htmlspecialchars($supplier['supplier_name']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Create Order Form -->
        <div class="form-container">
            <h3>Create New Order</h3>
            <form method="POST" id="orderForm">
                <div class="form-group">
                    <label for="customer_name">Customer Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>
                
                <div class="form-group">
                    <label for="shipping_address">Shipping Address</label>
                    <textarea id="shipping_address" name="shipping_address" rows="3" placeholder="Enter shipping address"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="supplier_id">Supplier ID (for Purchase Orders)</label>
                    <input type="number" id="supplier_id" name="supplier_id" placeholder="Enter Supplier ID (Optional)" min="1">
                    <small style="color: #666; display: block; margin-top: 0.25rem;">
                        Will be auto-filled when you select a product above
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="warehouse_id">Fulfill from Warehouse *</label>
                    <select id="warehouse_id" name="warehouse_id" required>
                        <option value="">Select Warehouse</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= $warehouse['warehouse_id'] ?>">
                                <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <h4>Order Items</h4>
                <div id="orderItems">
                    <div class="order-item">
                        <input type="number" name="product_ids[]" placeholder="Product ID" min="1" required>
                        <input type="number" name="quantities[]" placeholder="Quantity" min="1" required>
                        <button type="button" onclick="removeOrderItem(this)" class="btn btn-sm btn-danger">Remove</button>
                    </div>
                </div>
                
                <button type="button" onclick="addOrderItem()" class="btn btn-secondary">Add Another Item</button>
                <button type="submit" name="create_order" class="btn">Create Order</button>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="table-container">
            <h3>Order History</h3>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">No orders found. Create your first order above.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= $order['order_id'] ?></td>
                                <td><?= date('M j, Y H:i', strtotime($order['order_date'] ?? $order['date'])) ?></td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= $order['item_count'] ?> items</td>
                                <td><?= formatCurrency($order['total_value'] ?? 0) ?></td>
                                <td>
                                    <button onclick="viewOrderDetails(<?= $order['order_id'] ?>)" class="btn btn-sm btn-secondary">View Details</button>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this order? Stock will be restored.')">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        <button type="submit" name="delete_order" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 15px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto;">
            <h3>Order Details</h3>
            <div id="orderDetails"></div>
            <button onclick="closeOrderModal()" class="btn btn-secondary" style="margin-top: 1rem;">Close</button>
        </div>
    </div>

    <script>
        function selectProduct(productId, supplierId) {
            // Auto-fill the first product ID field
            const firstProductInput = document.querySelector('input[name="product_ids[]"]');
            if (firstProductInput) {
                firstProductInput.value = productId;
            }
            
            // Auto-fill supplier ID if available
            if (supplierId && supplierId !== 'null') {
                document.getElementById('supplier_id').value = supplierId;
            }
            
            // Scroll to the form
            document.getElementById('orderForm').scrollIntoView({ behavior: 'smooth' });
            
            // Highlight the selected product
            document.querySelectorAll('.product-card').forEach(card => {
                card.style.borderColor = '#ddd';
                card.style.backgroundColor = 'white';
            });
            
            event.currentTarget.style.borderColor = '#28a745';
            event.currentTarget.style.backgroundColor = '#f8fff8';
        }
        
        function addOrderItem() {
            const container = document.getElementById('orderItems');
            const newItem = document.createElement('div');
            newItem.className = 'order-item';
            newItem.innerHTML = `
                <input type="number" name="product_ids[]" placeholder="Product ID" min="1" required>
                <input type="number" name="quantities[]" placeholder="Quantity" min="1" required>
                <button type="button" onclick="removeOrderItem(this)" class="btn btn-sm btn-danger">Remove</button>
            `;
            container.appendChild(newItem);
        }

        function removeOrderItem(button) {
            const items = document.querySelectorAll('.order-item');
            if (items.length > 1) {
                button.parentElement.remove();
            }
        }

        function viewOrderDetails(orderId) {
            fetch(`order_details.php?order_id=${orderId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('orderDetails').innerHTML = html;
                    document.getElementById('orderModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetails').innerHTML = '<p>Error loading order details.</p>';
                    document.getElementById('orderModal').style.display = 'block';
                });
        }

        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });
    </script>
</body>
</html>
