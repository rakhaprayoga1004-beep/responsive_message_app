/**
 * Main JavaScript File - Fixed Version
 * File: assets/js/main.js
 */

// Global configuration
const CONFIG = window.APP_CONFIG || {
    BASE_URL: '/',
    ASSET_URL: '/assets/',
    MODULE_URL: '/modules/',
    APP_NAME: 'Aplikasi Pesan Responsif',
    CSRF_TOKEN: '',
    USER_ID: 0,
    USER_TYPE: ''
};

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeComponents();
    initializeEventListeners();
});

/**
 * Initialize all components
 */
function initializeComponents() {
    // Initialize active navigation
    initializeActiveNav();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize popovers
    initializePopovers();
    
    // Initialize form validations
    initializeFormValidations();
    
    // Initialize toast notifications
    initializeToast();
    
    // Initialize auto-dismiss alerts
    initializeAutoDismissAlerts();
    
    // Initialize password toggle
    initializePasswordToggle();
    
    // Initialize date pickers
    initializeDatePickers();
}

/**
 * Initialize active navigation highlighting - FIXED VERSION
 */
function initializeActiveNav() {
    try {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        
        if (!navLinks.length) return;
        
        navLinks.forEach(function(link) {
            const linkHref = link.getAttribute('href');
            
            if (!linkHref) return;
            
            // Normalize URLs for comparison
            const currentPathNormalized = currentPath.replace(/\/$/, '');
            const linkPathNormalized = linkHref.replace(/\/$/, '');
            
            // Check if this link should be active
            let isActive = false;
            
            if (linkPathNormalized === '' && currentPathNormalized === '') {
                // Both are root
                isActive = true;
            } else if (linkPathNormalized !== '' && currentPathNormalized.includes(linkPathNormalized)) {
                // Current path contains link path
                isActive = true;
            }
            
            // Update active state
            if (isActive) {
                link.classList.add('active');
                link.setAttribute('aria-current', 'page');
            } else {
                link.classList.remove('active');
                link.removeAttribute('aria-current');
            }
        });
    } catch (error) {
        console.warn('Error initializing active navigation:', error);
    }
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    try {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    } catch (error) {
        console.warn('Error initializing tooltips:', error);
    }
}

/**
 * Initialize Bootstrap popovers
 */
function initializePopovers() {
    try {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    } catch (error) {
        console.warn('Error initializing popovers:', error);
    }
}

/**
 * Initialize form validations
 */
function initializeFormValidations() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Initialize toast notifications
 */
function initializeToast() {
    try {
        const toastElList = [].slice.call(document.querySelectorAll('.toast'));
        toastElList.map(function (toastEl) {
            return new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 5000
            });
        });
        
        // Show any toast with data-show="true"
        const autoShowToasts = document.querySelectorAll('.toast[data-show="true"]');
        autoShowToasts.forEach(function(toastEl) {
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        });
    } catch (error) {
        console.warn('Error initializing toast:', error);
    }
}

/**
 * Initialize auto-dismiss alerts
 */
function initializeAutoDismissAlerts() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert.auto-dismiss');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        });
    }, 5000);
    
    // Add click to dismiss for Bootstrap alerts
    const alerts = document.querySelectorAll('.alert-dismissible .btn-close');
    alerts.forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 500);
            }
        });
    });
    
    // Legacy method for Bootstrap 5 alerts
    setTimeout(function() {
        const legacyAlerts = document.querySelectorAll('.alert:not(.alert-permanent):not(.auto-dismiss)');
        legacyAlerts.forEach(function(alert) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                try {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                } catch (e) {
                    // Fallback to manual hide
                    alert.style.display = 'none';
                }
            }
        });
    }, 5000);
    
    // Add click to dismiss for legacy alerts
    const legacyAlerts = document.querySelectorAll('.alert-dismissible:not(.auto-dismiss)');
    legacyAlerts.forEach(function(alert) {
        alert.addEventListener('click', function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                try {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                } catch (e) {
                    // Fallback to manual hide
                    alert.style.display = 'none';
                }
            }
        });
    });
}

/**
 * Initialize password visibility toggle
 */
function initializePasswordToggle() {
    // Handle buttons with data-toggle="password"
    const toggleButtons = document.querySelectorAll('.btn[data-toggle="password"]');
    
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            
            if (passwordInput) {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle icon
                const icon = this.querySelector('i');
                if (icon) {
                    if (type === 'text') {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            }
        });
    });
    
    // Handle buttons with class password-toggle
    const passwordToggleButtons = document.querySelectorAll('.password-toggle');
    
    passwordToggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            
            if (passwordInput) {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle icon
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            }
        });
    });
    
    // Handle specific ID #togglePassword
    const eyeButtons = document.querySelectorAll('#togglePassword');
    eyeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            }
        });
    });
}

/**
 * Initialize date pickers
 */
function initializeDatePickers() {
    // Flatpickr initialization if available
    if (typeof flatpickr !== 'undefined') {
        try {
            const dateInputs = document.querySelectorAll('.date-picker');
            dateInputs.forEach(function(input) {
                flatpickr(input, {
                    dateFormat: 'Y-m-d',
                    locale: 'id',
                    allowInput: true
                });
            });
            
            const datetimeInputs = document.querySelectorAll('.datetime-picker');
            datetimeInputs.forEach(function(input) {
                flatpickr(input, {
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i',
                    locale: 'id',
                    allowInput: true
                });
            });
        } catch (error) {
            console.warn('Error initializing date pickers:', error);
        }
    }
}

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Add any global event listeners here
}

/**
 * Show toast notification
 * @param {string} title - Toast title
 * @param {string} message - Toast message
 * @param {string} type - Toast type (success, error, warning, info)
 */
function showToast(title, message, type = 'info') {
    // Create toast container if it doesn't exist
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Initialize and show toast
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove toast after hide
        toast.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    } else {
        // Fallback: show for 3 seconds then remove
        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3000);
    }
}

/**
 * Create toast container if it doesn't exist
 */
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

/**
 * Show confirmation dialog
 * @param {string} title - Dialog title
 * @param {string} message - Dialog message
 * @param {function} confirmCallback - Function to call on confirm
 * @param {function} cancelCallback - Function to call on cancel
 */
function showConfirmation(title, message, confirmCallback, cancelCallback = null) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya',
            cancelButtonText: 'Tidak'
        }).then((result) => {
            if (result.isConfirmed && confirmCallback) {
                confirmCallback();
            } else if (cancelCallback) {
                cancelCallback();
            }
        });
    } else {
        // Fallback to native confirm
        if (confirm(message)) {
            if (confirmCallback) confirmCallback();
        } else if (cancelCallback) {
            cancelCallback();
        }
    }
}

/**
 * Format date to Indonesian format
 * @param {string} dateString - Date string to format
 * @returns {string} Formatted date
 */
function formatDateID(dateString) {
    try {
        const date = new Date(dateString);
        const options = { 
            weekday: 'long',
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        return date.toLocaleDateString('id-ID', options);
    } catch (error) {
        return dateString;
    }
}

/**
 * Format datetime to Indonesian format
 * @param {string} datetimeString - Datetime string to format
 * @returns {string} Formatted datetime
 */
function formatDateTimeID(datetimeString) {
    try {
        const date = new Date(datetimeString);
        const options = { 
            weekday: 'long',
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return date.toLocaleDateString('id-ID', options);
    } catch (error) {
        return datetimeString;
    }
}

/**
 * Debounce function for performance optimization
 * @param {function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {function} Debounced function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Get CSRF token from meta tag
 * @returns {string} CSRF token
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * Make AJAX request with CSRF token
 * @param {object} options - AJAX options
 */
function ajaxRequest(options) {
    const defaultOptions = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken()
        }
    };
    
    const mergedOptions = { ...defaultOptions, ...options };
    
    return fetch(mergedOptions.url, mergedOptions)
        .then(response => response.json())
        .catch(error => {
            console.error('AJAX request failed:', error);
            throw error;
        });
}

/**
 * Get full URL for a path
 */
function getFullUrl(path) {
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('//')) {
        return path;
    }
    
    if (path.startsWith('/')) {
        return window.location.origin + path;
    }
    
    return CONFIG.BASE_URL + path;
}

// Make functions available globally
window.showToast = showToast;
window.showConfirmation = showConfirmation;
window.formatDateID = formatDateID;
window.formatDateTimeID = formatDateTimeID;
window.debounce = debounce;
window.getCsrfToken = getCsrfToken;
window.ajaxRequest = ajaxRequest;
window.getFullUrl = getFullUrl;
window.CONFIG = CONFIG;

// Export functions for global use (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        showToast,
        showConfirmation,
        formatDateID,
        formatDateTimeID,
        debounce,
        getCsrfToken,
        ajaxRequest,
        getFullUrl,
        CONFIG
    };
}