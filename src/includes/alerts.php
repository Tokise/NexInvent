<?php
function showAlert($type, $message) {
    $alertClass = '';
    switch($type) {
        case 'success':
            $alertClass = 'alert-success';
            break;
        case 'error':
            $alertClass = 'alert-danger';
            break;
        case 'warning':
            $alertClass = 'alert-warning';
            break;
        case 'info':
            $alertClass = 'alert-info';
            break;
    }
    
    echo "<div class='alert {$alertClass} alert-dismissible fade show' role='alert' 
          style='border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
          font-family: Inter, sans-serif; font-size: 0.95rem; padding: 1rem 1.25rem;'>
            <div class='d-flex align-items-center'>
                <i class='bi bi-info-circle me-2'></i>
                {$message}
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
          </div>";
}