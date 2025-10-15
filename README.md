# Inventory Management System

A complete PHP-based inventory management system with MySQL database integration.

## Features

- **Dashboard**: Overview with statistics and alerts
- **Products Management**: Full CRUD operations for products with SKU, pricing, and supplier linking
- **Suppliers Management**: Manage supplier information and contacts
- **Warehouses Management**: Track multiple warehouse locations
- **Stock Management**: Real-time inventory tracking with automatic status indicators
- **Orders Management**: Create orders with automatic stock deduction and order history
- **Responsive Design**: Modern, mobile-friendly interface

## Installation

1. **Database Setup**:
   - Import the `database_setup.sql` file into your MySQL database
   - This will create the `inventory_simple` database with all required tables and sample data

2. **Configuration**:
   - Update the database connection settings in `config.php` if needed
   - Default settings: localhost, root user, no password, inventory_simple database

3. **Web Server**:
   - Place all files in your web server directory (e.g., `htdocs/www/DBE/`)
   - Ensure PHP and MySQL are running
   - Access the system through your web browser

## File Structure

```
├── config.php              # Database configuration and helper functions
├── index.php               # Dashboard with navigation
├── products.php            # Products management
├── suppliers.php           # Suppliers management
├── warehouses.php          # Warehouses management
├── stocks.php              # Stock levels management
├── orders.php              # Orders management
├── order_details.php       # Order details modal (AJAX)
├── style.css               # Modern CSS styling
├── database_setup.sql      # Database schema and sample data
└── README.md               # This file
```

## Database Schema

The system uses the following main tables:
- `products` - Product information with SKU, pricing, and supplier links
- `suppliers` - Supplier contact information
- `warehouses` - Warehouse locations
- `stocks` - Inventory levels per product/warehouse
- `orders` - Customer orders
- `order_line_items` - Individual items within orders

## Key Features

### Stock Management
- Real-time inventory tracking across multiple warehouses
- Automatic stock status indicators (Low Stock, Out of Stock, etc.)
- Stock value calculations

### Order Processing
- Create orders with multiple line items
- Automatic stock deduction from specified warehouse
- Order history with detailed item breakdown
- Stock restoration when orders are deleted

### Data Validation
- Form validation and error handling
- SQL injection prevention with prepared statements
- Input sanitization

### User Interface
- Responsive design that works on desktop and mobile
- Modern gradient styling with hover effects
- Intuitive navigation between modules
- Real-time feedback with success/error messages

## Usage

1. **Start with the Dashboard**: View system overview and key metrics
2. **Add Suppliers**: Create supplier records before adding products
3. **Add Warehouses**: Set up your storage locations
4. **Add Products**: Create products with SKU, pricing, and supplier information
5. **Manage Stock**: Update inventory levels across warehouses
6. **Process Orders**: Create orders that automatically deduct stock

## Sample Data

The system includes sample data:
- 3 suppliers (ABC Electronics, Tech Supply Co, Office Solutions)
- 3 warehouses (Main Warehouse, North Branch, South Depot)
- 5 products (Laptop, Mouse, Keyboard, Monitor, Desk)
- Stock levels across all warehouses

## Security Features

- Prepared statements prevent SQL injection
- Input sanitization with `htmlspecialchars()`
- Form validation on both client and server side
- Transaction support for data integrity

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Responsive design for mobile devices
- JavaScript required for order details modal

## Support

For issues or questions, check the code comments and ensure:
- PHP 7.0+ is installed
- MySQL 5.7+ is running
- Web server has proper file permissions
- Database connection settings are correct
