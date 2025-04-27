<?php
require_once '../../includes/require_auth.php';

// Add aggressive history protection to prevent back button to login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Store session info in browser storage for history tracking
$session_id = session_id();
$user_id = $_SESSION['user_id'];
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to access POS
if (!hasAnyPermission(['manage_sales', 'view_sales'])) {
    $_SESSION['error'] = "You don't have permission to access the Point of Sale system.";
    header("Location: ../dashboard/index.php");
    exit();
}

// Fetch all active products with stock
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        WHERE p.out_stock_quantity > 0 
        ORDER BY p.name";
$products = fetchAll($sql);

// Fetch categories for filtering
$categories = fetchAll("SELECT * FROM categories ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Point of Sale</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .product-card {
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .product-img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-bottom: 1px solid #e0e0e0;
        }
        .cart-item-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .cart-container {
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }
        .category-filter {
            overflow-x: auto;
            white-space: nowrap;
            padding: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }
        .category-filter::-webkit-scrollbar {
            height: 6px;
        }
        .category-filter::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .category-filter::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        .cart-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quantity-control button {
            width: 28px;
            height: 28px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-total {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .search-container {
            position: relative;
        }
        .search-container .clear-search {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: #6c757d;
            cursor: pointer;
        }
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
    
      
    </style>
</head>
<body>


<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid">
         <h2 class="mb-4">Point of Sale</h2>
             <div class="row">
            <!-- Products Section (Left Side) -->
                 <div class="col-lg-8">
                        <div class="card">
               
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Products</h5>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" id="searchProduct" placeholder="Search products...">
                            <button class="btn btn-sm btn-light" onclick="clearSearch()">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Category Filter -->
                        <div class="category-filter border-bottom">
                            <button class="btn btn-outline-primary me-2 active" onclick="filterByCategory('all')">All</button>
                            <?php foreach ($categories as $category): ?>
                                <button class="btn btn-outline-primary me-2" 
                                        onclick="filterByCategory(<?php echo $category['category_id']; ?>)">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Products Grid -->
                        <div class="product-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="card product-card" 
                                     data-category="<?php echo $product['category_id']; ?>"
                                     data-product='<?php echo json_encode([
                                         'product_id' => (int)$product['product_id'],
                                         'name' => $product['name'],
                                         'sku' => $product['sku'],
                                         'unit_price' => (float)$product['unit_price'],
                                         'image_url' => $product['image_url'],
                                         'out_stock_quantity' => (int)$product['out_stock_quantity']
                                     ]); ?>'>
                                    <div class="position-relative">
                                        <?php if ($product['out_stock_quantity'] <= $product['out_threshold_amount']): ?>
                                            <span class="badge bg-warning product-badge">Low Stock</span>
                                        <?php endif; ?>
                                        <?php if ($product['image_url']): ?>
                                            <img src="../../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                 class="product-img" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <img src="../../assets/images/no-image.svg" 
                                                 class="product-img" 
                                                 alt="No image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <p class="card-text text-muted small mb-2">
                                            SKU: <?php echo htmlspecialchars($product['sku']); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-primary fw-bold">
                                                $<?php echo number_format($product['unit_price'], 2); ?>
                                            </span>
                                            <span class="badge <?php echo $product['out_stock_quantity'] <= $product['out_threshold_amount'] ? 'bg-warning' : 'bg-success'; ?>">
                                                Stock: <?php echo $product['out_stock_quantity']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cart Section (Right Side) -->
            <div class="col-lg-4">
                <div class="cart-container">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Shopping Cart</h5>
                        </div>
                        <div class="card-body">
                            <div id="cartItems" class="mb-3">
                                <!-- Cart items will be dynamically added here -->
                            </div>
                            <hr>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span id="subtotal">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax (10%):</span>
                                    <span id="tax">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total:</span>
                                    <span id="total">$0.00</span>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-select" id="paymentMethod">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <button class="btn btn-success w-100" onclick="processTransaction()">
                                <i class="bi bi-cart-check"></i> Complete Sale
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let cart = [];
const TAX_RATE = 0.10;

$(document).ready(function() {
    // Initialize click handlers for product cards
    $('.product-card').on('click', function() {
        try {
            const productData = $(this).data('product');
            if (productData) {
                addToCart(productData);
            }
        } catch (error) {
            console.error('Error adding product to cart:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to add product to cart'
            });
        }
    });

    $('#salesTable').DataTable({
        "order": [[1, "desc"]],
        "pageLength": 25
    });
});

function addToCart(product) {
    const existingItem = cart.find(item => item.product_id === product.product_id);
    
    if (existingItem) {
        if (existingItem.quantity < product.out_stock_quantity) {
            existingItem.quantity++;
            updateCart();
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Stock Limit Reached',
                text: 'No more stock available for this product'
            });
        }
    } else {
        if (product.out_stock_quantity > 0) {
            cart.push({
                ...product,
                quantity: 1
            });
            updateCart();
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Out of Stock',
                text: 'This product is currently out of stock'
            });
        }
    }
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
}

function updateQuantity(index, change) {
    const item = cart[index];
    const newQuantity = item.quantity + change;
    
    if (newQuantity > 0 && newQuantity <= item.out_stock_quantity) {
        item.quantity = newQuantity;
        updateCart();
    }
}

function updateCart() {
    const cartContainer = document.getElementById('cartItems');
    cartContainer.innerHTML = '';
    
    let subtotal = 0;
    
    cart.forEach((item, index) => {
        const itemTotal = parseFloat(item.quantity) * parseFloat(item.unit_price);
        subtotal += itemTotal;
        
        cartContainer.innerHTML += `
            <div class="cart-item">
                <div class="d-flex align-items-center">
                    <img src="${item.image_url ? '../../' + item.image_url : '../../assets/images/no-image.svg'}" 
                         class="cart-item-img me-3" alt="${item.name}">
                    <div class="flex-grow-1">
                        <h6 class="mb-1 text-dark">${item.name}</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="quantity-control">
                                <button class="btn btn-sm btn-outline-secondary rounded-circle" 
                                        onclick="event.stopPropagation(); updateQuantity(${index}, -1)">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <span class="mx-2 fw-bold">${item.quantity}</span>
                                <button class="btn btn-sm btn-outline-secondary rounded-circle" 
                                        onclick="event.stopPropagation(); updateQuantity(${index}, 1)">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">$${(parseFloat(item.unit_price) * item.quantity).toFixed(2)}</div>
                                <small class="text-muted">$${parseFloat(item.unit_price).toFixed(2)} each</small>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger ms-2" 
                            onclick="event.stopPropagation(); removeFromCart(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    const tax = subtotal * TAX_RATE;
    const total = subtotal + tax;
    
    document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `$${tax.toFixed(2)}`;
    document.getElementById('total').textContent = `$${total.toFixed(2)}`;
}

function filterByCategory(categoryId) {
    const products = document.querySelectorAll('.product-card');
    const buttons = document.querySelectorAll('.category-filter .btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    products.forEach(product => {
        if (categoryId === 'all' || product.dataset.category === categoryId.toString()) {
            product.style.display = 'block';
        } else {
            product.style.display = 'none';
        }
    });
}

function clearSearch() {
    document.getElementById('searchProduct').value = '';
    const products = document.querySelectorAll('.product-card');
    products.forEach(product => product.style.display = 'block');
}

document.getElementById('searchProduct').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const products = document.querySelectorAll('.product-card');
    
    products.forEach(product => {
        const name = product.querySelector('.card-title').textContent.toLowerCase();
        const sku = product.querySelector('.card-text').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || sku.includes(searchTerm)) {
            product.style.display = 'block';
        } else {
            product.style.display = 'none';
        }
    });
});

function processTransaction() {
    if (cart.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Empty Cart',
            text: 'Please add items to the cart before proceeding'
        });
        return;
    }

    const paymentMethod = document.getElementById('paymentMethod').value;
    const total = parseFloat(document.getElementById('total').textContent.replace('$', ''));
    const subtotal = parseFloat(document.getElementById('subtotal').textContent.replace('$', ''));
    const tax = parseFloat(document.getElementById('tax').textContent.replace('$', ''));

    const saleData = {
        items: cart,
        payment_method: paymentMethod,
        subtotal: subtotal,
        tax: tax,
        total: total
    };

    $.ajax({
        url: 'ajax/process_sale.php',
        method: 'POST',
        data: JSON.stringify(saleData),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sale Complete',
                    text: 'Transaction has been processed successfully',
                    showCancelButton: true,
                    confirmButtonText: 'Print Receipt',
                    cancelButtonText: 'Close',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    // Print receipt if confirmed
                    if (result.isConfirmed) {
                        window.open(`../sales/receipt.php?sale_id=${response.sale_id}`, '_blank');
                    }
                    
                    // Update product quantities in the UI
                    cart.forEach(item => {
                        const productCard = document.querySelector(`.product-card[data-product*='"product_id":${item.product_id}']`);
                        if (productCard) {
                            const productData = JSON.parse(productCard.dataset.product);
                            productData.out_stock_quantity -= item.quantity;
                            
                            // Update the product card's data attribute
                            productCard.dataset.product = JSON.stringify(productData);
                            
                            // Update the stock display
                            const stockBadge = productCard.querySelector('.badge:not(.product-badge)');
                            if (stockBadge) {
                                stockBadge.textContent = `Stock: ${productData.out_stock_quantity}`;
                                
                                // Update badge color based on stock level
                                if (productData.out_stock_quantity <= productData.out_threshold_amount) {
                                    stockBadge.classList.remove('bg-success');
                                    stockBadge.classList.add('bg-warning');
                                }
                                
                                // Hide product if out of stock
                                if (productData.out_stock_quantity === 0) {
                                    productCard.style.display = 'none';
                                }
                            }
                        }
                    });
                    
                    // Clear the cart
                    cart = [];
                    updateCart();
                });
            } else {
                throw new Error(response.error || 'Failed to process sale');
            }
        },
        error: function(xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.error || 'Failed to process sale'
            });
        }
    });
}
</script>

</body>
</html> 