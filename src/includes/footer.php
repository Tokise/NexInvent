    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Common JavaScript functions -->
    <script>
    // Show loading spinner
    function showLoading(message = 'Loading...') {
        Swal.fire({
            title: message,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }
    
    // Hide loading spinner
    function hideLoading() {
        Swal.close();
    }
    
    // Show success message
    function showSuccess(message, callback = null) {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: message,
            confirmButtonColor: '#2ecc71'
        }).then(() => {
            if (callback && typeof callback === 'function') {
                callback();
            }
        });
    }
    
    // Show error message
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message,
            confirmButtonColor: '#e74c3c'
        });
    }
    
    // Show confirmation dialog
    function showConfirm(message, callback) {
        Swal.fire({
            icon: 'question',
            title: 'Confirm',
            text: message,
            showCancelButton: true,
            confirmButtonColor: '#2ecc71',
            cancelButtonColor: '#e74c3c',
            confirmButtonText: 'Yes',
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.isConfirmed && callback && typeof callback === 'function') {
                callback();
            }
        });
    }
    
    // Format number as currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
    
    // Format date
    function formatDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(new Date(date));
    }
    
    // Handle AJAX errors
    $(document).ajaxError(function(event, jqXHR, settings, error) {
        hideLoading();
        if (jqXHR.status === 401) {
            window.location.href = '../../login/index.php';
        } else {
            showError('An error occurred: ' + (jqXHR.responseJSON?.error || error));
        }
    });
    
    // Initialize all tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>
    
    <?php 
    // Add page-specific scripts here if needed
    if (isset($pageScripts) && is_array($pageScripts)) {
        foreach ($pageScripts as $script) {
            echo '<script src="' . $script . '"></script>' . "\n";
        }
    }
    ?>
    
    <!-- Common DataTables initialization -->
    <script>
        $(document).ready(function() {
            $('.datatable').DataTable({
                "responsive": true,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)"
                }
            });
        });
    </script>
</body>
</html> 