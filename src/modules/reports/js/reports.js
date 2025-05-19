function generateReport() {
    const reportType = document.getElementById('report_type').value;
    const format = document.getElementById('format').value;
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    // Show loading state
    const generateBtn = document.getElementById('generate_btn');
    const originalBtnText = generateBtn.innerHTML;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    generateBtn.disabled = true;
    
    // Disable all inputs during generation
    const inputs = document.querySelectorAll('select, input');
    inputs.forEach(input => input.disabled = true);
    
    // Show loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
    document.body.appendChild(loadingOverlay);
    
    const formData = new FormData();
    formData.append('report_type', reportType);
    formData.append('format', format);
    formData.append('start_date', startDate);
    formData.append('end_date', endDate);
    
    fetch('ajax/generate_report.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const contentType = response.headers.get('content-type');
        if (!response.ok) {
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Unknown error');
            } else {
                throw new Error('Network response was not ok');
            }
        }
        if (contentType && contentType.includes('application/pdf')) {
            return response.blob();
        } else {
            throw new Error('Unexpected response type');
        }
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${reportType}_report.${format}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
        
        // Show success message
        showNotification('Report generated successfully!', 'success');
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(error.message, 'error');
    })
    .finally(() => {
        // Reset button state
        generateBtn.innerHTML = originalBtnText;
        generateBtn.disabled = false;
        
        // Re-enable inputs
        inputs.forEach(input => input.disabled = false);
        
        // Remove loading overlay
        loadingOverlay.remove();
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add responsive design improvements
function initializeResponsiveDesign() {
    const reportForm = document.getElementById('report_form');
    const dateInputs = document.querySelectorAll('.date-input');
    
    // Add responsive classes based on screen size
    function updateResponsiveClasses() {
        const isMobile = window.innerWidth < 768;
        reportForm.classList.toggle('mobile-view', isMobile);
        dateInputs.forEach(input => {
            input.parentElement.classList.toggle('full-width', isMobile);
        });
    }
    
    // Initial call
    updateResponsiveClasses();
    
    // Update on resize
    window.addEventListener('resize', updateResponsiveClasses);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    initializeResponsiveDesign();
    
    // Add date range validation
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    startDate.addEventListener('change', () => {
        endDate.min = startDate.value;
        if (endDate.value && endDate.value < startDate.value) {
            endDate.value = startDate.value;
        }
    });
    
    endDate.addEventListener('change', () => {
        startDate.max = endDate.value;
        if (startDate.value && startDate.value > endDate.value) {
            startDate.value = endDate.value;
        }
    });
}); 