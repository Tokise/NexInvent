# NexInvent - Inventory Management System 📦

<p align="center">
  <img src="assets/LOGO.png" alt="NexInvent Logo" width="200"/>
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
  - Sales, inventory, attendance, stock movement, purchase orders, employees, and categories reports
  - Customizable date range filtering
  - **PDF export**
  - **Charts and data visualization** (Chart.js in web UI, charts embedded in PDFs)
  - Company branding in PDF reports

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
- Customizable reports for all major entities
- Export to PDF 
- **Charts in web UI and embedded in PDFs**
- Company logo and branding in PDF
- Date range filtering
- Summary statistics

## 💻 Technical Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser
- XAMPP (recommended for local development)
- **PHP Extensions:**
  - `gd` (for image/chart support)
  - `zip` (for Excel export)
  - `mbstring`, `pdo_mysql`, `openssl`, `fileinfo` (recommended)
- **Composer** (for dependency management)
- **Node.js** (optional, for advanced chart/image generation)
- **External Tools:**
  - [wkhtmltoimage](https://wkhtmltopdf.org/downloads.html) (for embedding charts in PDFs)

## 🚀 Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Tokise/NexInvent.git
   ```

2. **Import the database schema:**
   ```bash
   mysql -u root -p nexinvent < database.sql
   ```

3. **Configure the database connection in `src/config/db.php`:**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'nexinvent');
   ```

4. **Enable required PHP extensions:**
   - Open your `php.ini` (see below for XAMPP or standalone Apache)
   - Uncomment or add these lines:
     ```ini
     extension=gd
     extension=zip
     extension=mbstring
     extension=pdo_mysql
     extension=openssl
     extension=fileinfo
     ```
   - Restart Apache after saving changes.

5. **Install Composer dependencies:**
   ```bash
   cd NexInvent
   composer install
   ```

6. **Install and configure wkhtmltoimage:**
   - Download from [wkhtmltopdf.org/downloads.html](https://wkhtmltopdf.org/downloads.html)
   - Install to the default location (e.g., `C:\Program Files\wkhtmltopdf\bin`)
   - Add the install directory to your Windows PATH:
     1. Open System Properties > Environment Variables
     2. Edit the `Path` variable
     3. Add: `C:\Program Files\wkhtmltopdf\bin`
     4. Restart your computer or log out/in
   - Test in Command Prompt:
     ```cmd
     wkhtmltoimage --version
     ```
   - If you see a version number, it's installed correctly.

7. **Add Font Awesome for icons:**
   - Font Awesome is included via CDN in the main header file. No extra setup needed.

8. **Set up your web server to point to the project directory**

9. **Access the application through your web browser**

## 🛠️ Troubleshooting

- **PDFs are blank or fail to load:**
  - Make sure there is no output (echo, whitespace, error) before PDF headers in PHP.
  - Check your PHP error log for errors during PDF generation.
  - Ensure all required PHP extensions are enabled.
  - If using charts in PDFs, make sure `wkhtmltoimage` is installed and in your PATH.

- **Excel export fails:**
  - Ensure `zip` and `gd` extensions are enabled.
  - Check for errors in your PHP log.

- **Charts do not appear in PDF:**
  - Confirm `wkhtmltoimage` is installed and working.
  - Check for errors in your PHP log.

- **JS errors about JSON or PDF:**
  - Make sure the backend returns valid JSON for AJAX/chart requests and binary for PDF downloads.
  - See the code for robust error handling in both JS and PHP.

- **General errors:**
  - Enable error reporting in PHP for debugging:
    ```php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    ```
  - Check your PHP error log for details.

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

For support, please email ablenjohnrey@gmail.com or open an issue in the GitHub repository. 