CREATE DATABASE IF NOT EXISTS inventory_simple;
USE inventory_simple;

-- Products table
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    supplier_id INT,
    unit_cost DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL
);

-- Suppliers table
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    contact_info TEXT
);

-- Warehouses table
CREATE TABLE warehouses (
    warehouse_id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_name VARCHAR(255) NOT NULL,
    location TEXT NOT NULL
);

-- Stocks table
CREATE TABLE stocks (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantity_on_hand INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_warehouse (product_id, warehouse_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    customer_name VARCHAR(255) NOT NULL
);

-- Order line items table
CREATE TABLE order_line_items (
    line_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- Add foreign key for products.supplier_id
ALTER TABLE products
ADD CONSTRAINT fk_supplier_id FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL;

-- Insert sample data
INSERT INTO suppliers (supplier_name, contact_info) VALUES 
('ABC Electronics', 'Phone: 555-0101, Email: abc@electronics.com'),
('Tech Supply Co', 'Phone: 555-0102, Email: orders@techsupply.com'),
('Office Solutions', 'Phone: 555-0103, Email: contact@officesolutions.com');

INSERT INTO warehouses (warehouse_name, location) VALUES 
('Main Warehouse', '123 Industrial Blvd, City Center'),
('North Branch', '456 North Ave, North District'),
('South Depot', '789 South St, South Quarter');

INSERT INTO products (sku, product_name, description, supplier_id, unit_cost, selling_price) VALUES 
('LAPTOP001', 'Gaming Laptop', 'High-performance gaming laptop with RTX graphics', 1, 800.00, 1200.00),
('MOUSE001', 'Wireless Mouse', 'Ergonomic wireless mouse with RGB lighting', 2, 25.00, 45.00),
('KEYBOARD001', 'Mechanical Keyboard', 'RGB mechanical keyboard with blue switches', 2, 60.00, 95.00),
('MONITOR001', '4K Monitor', '27-inch 4K Ultra HD monitor', 1, 300.00, 450.00),
('DESK001', 'Standing Desk', 'Adjustable height standing desk', 3, 200.00, 320.00);

INSERT INTO stocks (product_id, warehouse_id, quantity_on_hand) VALUES 
(1, 1, 15), (1, 2, 8), (1, 3, 5),
(2, 1, 50), (2, 2, 25), (2, 3, 30),
(3, 1, 20), (3, 2, 12), (3, 3, 18),
(4, 1, 10), (4, 2, 6), (4, 3, 4),
(5, 1, 8), (5, 2, 5), (5, 3, 7);
