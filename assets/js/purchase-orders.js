function markPOReceived(poId) {
    if (!confirm('Are you sure you want to mark this purchase order as received? This will update inventory quantities.')) {
        return;
    }

    $.ajax({
        url: 'src/modules/products/ajax/mark_po_received.php',
        method: 'POST',
        data: { po_id: poId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success('Purchase order marked as received successfully');
                // Refresh the page or update the UI as needed
                location.reload();
            } else {
                toastr.error(response.error || 'An error occurred while marking the purchase order as received');
            }
        },
        error: function(xhr) {
            let errorMessage = 'An error occurred while marking the purchase order as received';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMessage = xhr.responseJSON.error;
            }
            toastr.error(errorMessage);
        }
    });
} 