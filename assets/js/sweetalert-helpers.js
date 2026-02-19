// SweetAlert Helper Functions
// Common alert functions for consistent UI

// Success alert
function showSuccess(message, title = 'Success') {
    return Swal.fire({
        icon: 'success',
        title: title,
        text: message,
        confirmButtonColor: '#28a745',
        timer: 3000,
        timerProgressBar: true
    });
}

// Error alert
function showError(message, title = 'Error') {
    return Swal.fire({
        icon: 'error',
        title: title,
        text: message,
        confirmButtonColor: '#dc3545'
    });
}

// Warning alert
function showWarning(message, title = 'Warning') {
    return Swal.fire({
        icon: 'warning',
        title: title,
        text: message,
        confirmButtonColor: '#ffc107'
    });
}

// Info alert
function showInfo(message, title = 'Information') {
    return Swal.fire({
        icon: 'info',
        title: title,
        text: message,
        confirmButtonColor: '#17a2b8'
    });
}

// Confirm dialog
function showConfirm(message, title = 'Confirm', confirmText = 'Yes', cancelText = 'Cancel') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
    });
}

// Delete confirmation
function confirmDelete(itemName = 'this item') {
    return Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete ${itemName}. This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    });
}

// Loading alert
function showLoading(message = 'Please wait...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

// Close loading
function closeLoading() {
    Swal.close();
}
