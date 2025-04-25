<div class="card-footer">
    <div class="d-flex justify-content-between">
        <div>
            <a href="purchase_orders.php" class="btn btn-secondary">Back to Purchase Orders</a>
        </div>
        <div>
            <?php if ($po_data['status'] === 'pending'): ?>
                <button type="button" class="btn btn-success" onclick="markPOReceived(<?php echo $po_data['id']; ?>)">
                    <i class="fas fa-check"></i> Mark as Received
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function markPOReceived(poId) {
    if (confirm('Are you sure you want to mark this purchase order as received?')) {
        $.ajax({
            url: '../../ajax/mark_po_received.php',
            type: 'POST',
            data: { po_id: poId },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        toastr.success('Purchase order marked as received successfully!');
                        // Reload the page to reflect the new status
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        toastr.error(result.message || 'Failed to mark purchase order as received');
                    }
                } catch (e) {
                    toastr.error('An error occurred while processing the response');
                }
            },
            error: function() {
                toastr.error('Failed to communicate with the server');
            }
        });
    }
}
</script>
</body> 