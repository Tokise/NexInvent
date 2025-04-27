<?php
require_once '../../includes/require_auth.php';
require_once '../../includes/settings.php';

// Add aggressive history protection to prevent back button to login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Store session info in browser storage for history tracking
$session_id = session_id();
$user_id = $_SESSION['user_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}



// Get current currency settings
$currencySettings = getCurrencySettings();

// Get detected currency settings from IP
$detectedCurrency = detectCurrencyFromIP();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;
    
    if (isset($_POST['action'])) {
        // Auto-detect currency
        if ($_POST['action'] === 'auto_detect') {
            if (autoUpdateCurrencyFromIP(true)) {
                $success = "Currency settings updated automatically based on your location.";
                $currencySettings = getCurrencySettings(); // Refresh settings
            } else {
                $errors['general'] = "Failed to update currency settings automatically.";
            }
        }
        // Manual update
        else if ($_POST['action'] === 'manual_update') {
            $currencyCode = $_POST['currency_code'] ?? '';
            $currencySymbol = $_POST['currency_symbol'] ?? '';
            $currencyPosition = $_POST['currency_position'] ?? 'before';
            
            if (empty($currencyCode)) {
                $errors['currency_code'] = "Currency code is required";
            }
            
            if (empty($currencySymbol)) {
                $errors['currency_symbol'] = "Currency symbol is required";
            }
            
            if (empty($errors)) {
                if (updateCurrencySettings($currencyCode, $currencySymbol, $currencyPosition)) {
                    $success = "Currency settings updated successfully!";
                    $currencySettings = getCurrencySettings(); // Refresh settings
                } else {
                    $errors['general'] = "Failed to update settings. Please try again.";
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - System Settings</title>
    <!-- Block browser caching -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- History protection script -->
    <script>
    // History protection to prevent back button to login
    (function() {
        try {
            // Save session info to sessionStorage for the sidebar script
            sessionStorage.setItem('nexinvent_auth', JSON.stringify({
                session_id: '<?php echo $session_id; ?>',
                user_id: <?php echo $user_id; ?>,
                timestamp: Date.now()
            }));
            
            // Replace current history state to mark this as protected
            if (history.replaceState) {
                history.replaceState({page: 'protected'}, document.title, location.href);
            }
            
            // When page loads
            window.addEventListener('load', function() {
                // Block back button navigation to login page
                window.addEventListener('popstate', function(e) {
                    // If we're going back to an unmarked page
                    if (!e.state || e.state.page !== 'protected') {
                        // Force forward to stay on protected page
                        history.go(1);
                    }
                });
            });
        } catch(e) {}
    })();
    </script>
</head>
<body>

<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">System Settings</h4>
                    </div>
                    
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $errors['general']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="currency-tab" data-bs-toggle="tab" 
                                        data-bs-target="#currencySettings" type="button" role="tab" 
                                        aria-controls="currencySettings" aria-selected="true">
                                    <i class="bi bi-currency-exchange me-2"></i>Currency Settings
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- Currency Settings Tab -->
                            <div class="tab-pane fade show active" id="currencySettings" role="tabpanel" aria-labelledby="currency-tab">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-header">
                                                <h5 class="mb-0">Current Settings</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <strong>Currency Code:</strong> <?php echo htmlspecialchars($currencySettings['code']); ?>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Currency Symbol:</strong> <?php echo htmlspecialchars($currencySettings['symbol']); ?>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Symbol Position:</strong> 
                                                    <?php echo $currencySettings['position'] === 'before' 
                                                        ? 'Before amount (e.g. $100)' 
                                                        : 'After amount (e.g. 100 €)'; ?>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Example:</strong> 
                                                    <?php echo formatAmount(1234.56); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-header">
                                                <h5 class="mb-0">Detected Settings (Based on IP)</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <strong>Country:</strong> <?php echo htmlspecialchars($detectedCurrency['country'] ?? 'Unknown'); ?>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Currency Code:</strong> <?php echo htmlspecialchars($detectedCurrency['code']); ?>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Currency Symbol:</strong> <?php echo htmlspecialchars($detectedCurrency['symbol']); ?>
                                                </div>
                                                <div class="mb-3">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="auto_detect">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="bi bi-magic me-2"></i>Use Detected Settings
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Manual Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="manual_update">
                                            
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="currency_code" class="form-label">Currency Code</label>
                                                    <input type="text" class="form-control <?php echo isset($errors['currency_code']) ? 'is-invalid' : ''; ?>" 
                                                           id="currency_code" name="currency_code" 
                                                           value="<?php echo htmlspecialchars($currencySettings['code']); ?>"
                                                           placeholder="USD, EUR, GBP, etc.">
                                                    <?php if (isset($errors['currency_code'])): ?>
                                                        <div class="invalid-feedback"><?php echo $errors['currency_code']; ?></div>
                                                    <?php endif; ?>
                                                    <small class="text-muted">3-letter ISO code (USD, EUR, GBP, etc.)</small>
                                                </div>
                                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                                    <input type="text" class="form-control <?php echo isset($errors['currency_symbol']) ? 'is-invalid' : ''; ?>" 
                                                           id="currency_symbol" name="currency_symbol" 
                                                           value="<?php echo htmlspecialchars($currencySettings['symbol']); ?>"
                                                           placeholder="$, €, £, etc.">
                                                    <?php if (isset($errors['currency_symbol'])): ?>
                                                        <div class="invalid-feedback"><?php echo $errors['currency_symbol']; ?></div>
                                                    <?php endif; ?>
                                                    <small class="text-muted">Symbol to display for amounts ($, €, £, etc.)</small>
                                                </div>
                                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="currency_position" class="form-label">Symbol Position</label>
                                                    <select class="form-select" id="currency_position" name="currency_position">
                                                        <option value="before" <?php echo $currencySettings['position'] === 'before' ? 'selected' : ''; ?>>
                                                            Before amount (e.g. $100)
                                                        </option>
                                                        <option value="after" <?php echo $currencySettings['position'] === 'after' ? 'selected' : ''; ?>>
                                                            After amount (e.g. 100 €)
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-save me-2"></i>Save Currency Settings
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 