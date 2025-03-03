<?php
session_start();

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'customer') {
        header("Location: ../modules/customer/index.php");
    } else {
        header("Location: ../modules/index.php");
    }
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../modules/config/db.php';
    
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    $errors = [];
    
    // Validate username
    if (empty($username)) {
        $errors['username'] = "Username is required";
    } else {
        // Check if username exists
        $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
        if (fetchValue($sql, [$username]) > 0) {
            $errors['username'] = "Username already exists";
        }
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    } else {
        // Check if email exists
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        if (fetchValue($sql, [$email]) > 0) {
            $errors['email'] = "Email already exists";
        }
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters";
    }
    
    // Validate confirm password
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    // Validate full name
    if (empty($full_name)) {
        $errors['full_name'] = "Full name is required";
    }

    // Validate phone
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required";
    }

    // Validate address
    if (empty($address)) {
        $errors['address'] = "Address is required";
    }
    
    if (empty($errors)) {
        try {
            // Start transaction
            $conn = getDBConnection();
            $conn->beginTransaction();
            
            // Create user account with explicit role
            $user_data = [
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'email' => $email,
                'full_name' => $full_name,
                'role' => 'customer',
                'status' => 'active',
                'created_by' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $user_id = insert('users', $user_data);
            
            // Check if customer already exists with this email
            $existing_customer = fetchOne("SELECT customer_id FROM customers WHERE email = ?", [$email]);
            
            if ($existing_customer) {
                // Update existing customer record
                $customer_data = [
                    'name' => $full_name,
                    'phone' => $phone,
                    'address' => $address,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                executeQuery("UPDATE customers SET name = ?, phone = ?, address = ?, updated_at = ? WHERE customer_id = ?",
                    [$customer_data['name'], $customer_data['phone'], $customer_data['address'], $customer_data['updated_at'], $existing_customer['customer_id']]);
                $customer_id = $existing_customer['customer_id'];
            } else {
                // Create new customer record
                $customer_data = [
                    'name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $customer_id = insert('customers', $customer_data);
            }
            
            // Create customer profile
            $profile_data = [
                'user_id' => $user_id,
                'default_shipping_address' => $address,
                'default_billing_address' => $address,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            insert('customer_profiles', $profile_data);
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['success'] = "Registration successful! You can now login.";
            header("Location: ../login/index.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors['general'] = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - NexInvent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
           :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --font-primary: 'Poppins', sans-serif;
            --font-secondary: 'Inter', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            padding: 2rem 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .register-container {
            max-width: 800px;
            width: 90%;
            background-color: white;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border-radius: 15px;
            padding: 2.5rem;
            margin: 0 auto;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-container img {
            width: 200px;
            height: auto;
            margin-bottom: 1rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
        }
        .form-control:focus {
            box-shadow: 0 0 0 2px rgba(13, 71, 161, 0.25);
            border-color: #0d47a1;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            color:var(--primary-dark);
            font-family: 'Inter', sans-serif;
        }
        .btn-primary {
            font-family: var(--font-primary);
            font-weight: 600;
            padding: 0.875rem;
            border-radius: 12px;
            background: linear-gradient(145deg, var(--primary-color), var(--primary-dark));
            border: none;
            letter-spacing: 0.025em;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.2);
        }
          .form-label {
            font-family: var(--font-primary);
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        a {
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        a:hover {
            color: var(--primary-dark);
            text-decoration: none;
        }
        .alert {
            border-radius: 8px;
            border: none;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
        }
        .invalid-feedback {
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
        }
        .text-center p {
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo-container">
            <img src="../../assets/LOGO.png" alt="NexInvent Logo">
        </div>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-row">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                               id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['full_name']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-at"></i></span>
                        <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                               id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                               id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                               id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                        <?php if (isset($errors['phone'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                    <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                              id="address" name="address" rows="2" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    <?php if (isset($errors['address'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['address']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                               id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                               id="confirm_password" name="confirm_password" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Register</button>
        </form>

        <div class="text-center mt-3">
            <p class="mb-0">Already have an account? <a href="../login/index.php">Login here</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Toggle confirm password visibility
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>