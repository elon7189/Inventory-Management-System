<?php
require_once 'config.php';

if (!isset($_GET['order_id'])) {
    echo '<p>Order ID not provided.</p>';
    exit;
}

$order_id = (int)$_GET['order_id'];

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo '<p>Order not found.</p>';
    exit;
}

// Get order line items with product details
try {
    $stmt = $conn->prepare("
        SELECT oli.*, p.product_name, p.sku, p.selling_price
        FROM order_line_items oli
        JOIN products p ON oli.product_id = p.product_id
        WHERE oli.order_id = ?
        ORDER BY oli.item_id
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $line_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // If there's an issue with the order_line_items table, set empty array
    $line_items = [];
}

$total_value = 0;
foreach ($line_items as $item) {
    $total_value += $item['quantity'] * $item['unit_price'];
}
?>

<div class="order-details">
    <h4>Order #<?= $order['order_id'] ?></h4>
    <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
    <p><strong>Date:</strong> <?= date('M j, Y H:i:s', strtotime($order['order_date'] ?? $order['date'])) ?></p>
    
    <h5>Order Items</h5>
    <table style="width: 100%; margin-top: 1rem;">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($line_items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td><?= htmlspecialchars($item['sku']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= formatCurrency($item['unit_price']) ?></td>
                    <td><?= formatCurrency($item['quantity'] * $item['unit_price']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background: #f8f9fa;">
                <td colspan="4">Total Order Value:</td>
                <td><?= formatCurrency($total_value) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
