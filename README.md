# NexInvent - Inventory Management System 📦

<p align="center">
  <img src="assets/img/logo.png" alt="NexInvent Logo" width="200"/>
</p>

NexInvent is a comprehensive inventory management system designed to help businesses efficiently track and manage their stock, purchase orders, and sales. Built with PHP and MySQL, it offers a modern and user-friendly interface for managing all aspects of inventory control.

## ✨ Features

- **👥 User Management**
  - Role-based access control (Admin, Manager, Employee)
  - Secure authentication and authorization
  - User activity tracking

- **📦 Inventory Management**
  - Real-time stock tracking
  - Low stock alerts
  - Stock movement history
  - Multiple categories support
  - SKU management

- **📝 Purchase Order System**
  - Automated PO generation for low stock
  - Multi-step approval process
  - Partial receipt handling
  - Supplier management
  - Purchase history tracking

- **💰 Sales Management**
  - Point of Sale (POS) system
  - Sales history
  - Payment method tracking
  - Daily sales summary

- **📊 Reporting**
  - Sales reports
  - Inventory reports
  - Employee reports
  - Purchase reports
  - Custom date range filtering

## 📁 Project Structure

```
NexInvent/
├── assets/              # 🎨 Static assets
├── logs/               # 📝 System logs
├── src/
│   ├── modules/        # 🔧 Core modules
│   │   ├── products/   # 📦 Product management
│   │   ├── purchases/  # 🛍️ Purchase orders
│   │   ├── sales/      # 💰 Sales management
│   │   ├── stock/      # 📊 Stock management
│   │   ├── reports/    # 📈 Reporting system
│   │   └── users/      # 👥 User management
│   ├── uploads/        # 📤 File uploads
│   ├── register/       # ✍️ User registration
│   ├── config/         # ⚙️ Configuration files
│   ├── css/           # 🎨 Stylesheets
│   ├── includes/      # 🔧 Common includes
│   └── login/         # 🔐 Authentication
├── database.sql        # 💾 Database schema
├── setup.php          # 🔧 Installation setup
└── README.md          # 📖 Documentation
```

## 🔧 Module Details

### 📦 Products Module
- Product CRUD operations
- Category management
- SKU generation
- Price management
- Stock level tracking

### 🛍️ Purchases Module
- Purchase order creation
- Approval workflow
- Receipt management
- Supplier information
- Stock updates on receipt

### 💰 Sales Module
- POS interface
- Payment processing
- Receipt generation
- Sales history
- Daily summaries

### 📊 Stock Module
- Stock movement tracking
- Transfer between locations
- Stock adjustments
- Movement history
- Stock counts

### 📈 Reports Module
- Customizable reports
- Export functionality
- Data visualization
- Date range filtering
- Summary statistics

## 💻 Technical Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser
- XAMPP (recommended for local development)

## 🚀 Installation

1. Clone the repository to your web server directory:
   ```bash
   git clone https://github.com/Tokise/NexInvent.git
   ```

2. Import the database schema:
   ```bash
   mysql -u root -p nexinvent < database.sql
   ```

3. Configure the database connection in `src/config/db.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'nexinvent');
   ```

4. Enable GD Library in PHP (required for image processing):
   - For XAMPP:
     1. Open XAMPP Control Panel
     2. Click on "Config" button for Apache
     3. Select "PHP (php.ini)"
     4. Find the line `;extension=gd`
     5. Remove the semicolon to make it `extension=gd`
     6. Save the file
     7. Restart Apache through XAMPP Control Panel

   - For standalone Apache:
     1. Locate your php.ini file (typically in /etc/php/ or C:\php\)
     2. Open php.ini in a text editor
     3. Find the line `;extension=gd`
     4. Remove the semicolon to make it `extension=gd`
     5. Save the file
     6. Restart Apache service

   - Verify Installation:
     1. Create a PHP file with `<?php phpinfo(); ?>`
     2. Open it in browser
     3. Search for "gd" - you should see GD Support enabled

5. Set up your web server to point to the project directory

6. Access the application through your web browser

## 🔑 Default Login

- Username: admin
- Password: password
- Role: Administrator

## 🔒 Security Features

- Password hashing using bcrypt
- Role-based access control
- SQL injection prevention
- XSS protection
- CSRF protection
- Session security

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request



## 💬 Support

For support, please email ablenjohnre@gmail.com or open an issue in the GitHub repository. 