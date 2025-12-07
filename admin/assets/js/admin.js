// Admin Dashboard JavaScript
class AdminDashboard {
    constructor() {
        this.isMobile = window.innerWidth <= 767;
        this.init();
        this.setupViewportHandling();
    }

    init() {
        this.setupSidebarToggle();
        this.setupUserDropdown();
        this.setupImagePreviews();
        this.setupBulkActions();
        this.setupTabs();
        this.setupConfirmations();
        this.setupFormEnhancements();
        this.setupTableResponsive();
    }

    // Enhanced mobile detection and handling
    setupViewportHandling() {
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                const wasMobile = this.isMobile;
                this.isMobile = window.innerWidth <= 767;
                
                if (wasMobile !== this.isMobile) {
                    this.handleViewportChange();
                }
            }, 250);
        });
    }

    handleViewportChange() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileSidebarOverlay');
        
        // Close mobile sidebar when switching to desktop
        if (!this.isMobile && sidebar) {
            sidebar.classList.remove('mobile-open');
            if (overlay) {
                overlay.classList.remove('visible');
            }
            document.body.classList.remove('sidebar-open');
        }
    }

    // Enhanced Sidebar toggle for mobile
    setupSidebarToggle() {
        const toggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        let overlay = document.getElementById('mobileSidebarOverlay');
        
        // Create overlay if it doesn't exist
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'mobileSidebarOverlay';
            overlay.className = 'mobile-sidebar-overlay';
            document.body.appendChild(overlay);
        }

        const toggleSidebar = () => {
            if (!sidebar) return;
            
            if (this.isMobile) {
                // Mobile behavior
                const willShow = !sidebar.classList.contains('mobile-open');
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('visible', willShow);
                document.body.classList.toggle('sidebar-open', willShow);
                
                // Prevent body scroll when sidebar is open
                if (willShow) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            } else {
                // Desktop behavior - toggle collapse
                sidebar.classList.toggle('collapsed');
            }
        };

        if (toggle) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                toggleSidebar();
            });
        }

        // Close sidebar when clicking overlay
        overlay.addEventListener('click', () => {
            if (this.isMobile && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                document.body.classList.remove('sidebar-open');
                document.body.style.overflow = '';
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (this.isMobile && 
                sidebar && 
                sidebar.classList.contains('mobile-open') &&
                !sidebar.contains(e.target) &&
                !(toggle && toggle.contains(e.target))) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                document.body.classList.remove('sidebar-open');
                document.body.style.overflow = '';
            }
        });

        // Close sidebar when pressing escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobile && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                document.body.classList.remove('sidebar-open');
                document.body.style.overflow = '';
            }
        });

        // Close sidebar when nav link is clicked on mobile
        if (this.isMobile) {
            document.addEventListener('click', (e) => {
                if (e.target.closest('.nav-link') && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('visible');
                    document.body.classList.remove('sidebar-open');
                    document.body.style.overflow = '';
                }
            });
        }
    }

    // Enhanced User dropdown functionality
    setupUserDropdown() {
        const userElements = document.querySelectorAll('.admin-user');
        
        userElements.forEach(user => {
            user.addEventListener('click', (e) => {
                e.stopPropagation();
                const dropdown = user.querySelector('.user-dropdown');
                const isVisible = dropdown.style.display === 'block';
                
                // Close all other dropdowns
                document.querySelectorAll('.user-dropdown').forEach(d => {
                    d.style.display = 'none';
                });
                
                // Toggle current dropdown
                dropdown.style.display = isVisible ? 'none' : 'block';
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.user-dropdown').forEach(dropdown => {
                dropdown.style.display = 'none';
            });
        });

        // Close dropdowns on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.user-dropdown').forEach(dropdown => {
                    dropdown.style.display = 'none';
                });
            }
        });
    }

    // Enhanced Image preview for file uploads
    setupImagePreviews() {
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Check file size (5MB limit)
                    if (file.size > 5 * 1024 * 1024) {
                        AdminDashboard.showNotification('File size must be less than 5MB', 'error');
                        this.value = '';
                        return;
                    }

                    // Check file type
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        AdminDashboard.showNotification('Please select a valid image file (JPG, PNG, GIF, WebP)', 'error');
                        this.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Remove existing preview
                        const existingPreview = input.parentNode.querySelector('.image-preview');
                        if (existingPreview) {
                            existingPreview.remove();
                        }

                        // Create new preview
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        img.alt = 'Image preview';

                        // Add to file upload area
                        const fileUpload = input.closest('.file-upload') || input.parentNode;
                        fileUpload.appendChild(img);
                    }
                    reader.onloadstart = () => {
                        // Add loading state
                        input.disabled = true;
                    }
                    reader.onloadend = () => {
                        input.disabled = false;
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    }

    // Enhanced Bulk actions functionality
    setupBulkActions() {
        const bulkForm = document.getElementById('bulkForm');
        if (!bulkForm) return;

        const selectAllCheckboxes = bulkForm.querySelectorAll('input[type="checkbox"][id*="selectAll"]');
        
        selectAllCheckboxes.forEach(selectAll => {
            selectAll.addEventListener('change', function() {
                const checkboxes = bulkForm.querySelectorAll('input[name="comment_ids[]"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                
                // Update all selectAll checkboxes
                selectAllCheckboxes.forEach(sa => {
                    sa.checked = this.checked;
                });
            });
        });

        // Update select all when individual checkboxes change
        bulkForm.addEventListener('change', function(e) {
            if (e.target.name === 'comment_ids[]') {
                const checkboxes = bulkForm.querySelectorAll('input[name="comment_ids[]"]');
                const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
                const someChecked = Array.from(checkboxes).some(cb => cb.checked);
                
                selectAllCheckboxes.forEach(selectAll => {
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = !allChecked && someChecked;
                });
            }
        });

        // Bulk form submission confirmation
        bulkForm.addEventListener('submit', function(e) {
            const selectedCount = this.querySelectorAll('input[name="comment_ids[]"]:checked').length;
            const action = this.querySelector('select[name="bulk_action"]').value;
            
            if (selectedCount === 0) {
                e.preventDefault();
                AdminDashboard.showNotification('Please select at least one item', 'error');
                return;
            }
            
            if (!action) {
                e.preventDefault();
                AdminDashboard.showNotification('Please select a bulk action', 'error');
                return;
            }
            
            if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete ${selectedCount} item(s)? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            }
        });
    }

    // Enhanced Tab functionality
    setupTabs() {
        const tabs = document.querySelectorAll('.tab');
        
        tabs.forEach(tab => {
            // Skip if tab has onclick handler (for comments filtering)
            if (tab.onclick) return;
            
            tab.addEventListener('click', function() {
                const target = this.dataset.target;
                
                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show target content
                if (target) {
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.style.display = 'none';
                    });
                    const targetElement = document.getElementById(target);
                    if (targetElement) {
                        targetElement.style.display = 'block';
                    }
                }
            });
        });

        // Handle comment filtering tabs
        const commentTabs = document.querySelectorAll('.tab[onclick*="filterComments"]');
        commentTabs.forEach(tab => {
            const originalOnClick = tab.onclick;
            tab.onclick = null;
            tab.addEventListener('click', originalOnClick);
        });
    }

    // Enhanced Confirmation for destructive actions
    setupConfirmations() {
        document.addEventListener('click', function(e) {
            const deleteLink = e.target.closest('a[href*="delete"]');
            if (deleteLink && !deleteLink.hasAttribute('data-confirmed')) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    deleteLink.setAttribute('data-confirmed', 'true');
                    window.location.href = deleteLink.href;
                }
            }
        });
    }

    // Form enhancements for better mobile experience
    setupFormEnhancements() {
        // Add loading states to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    
                    // Re-enable button after 10 seconds (safety net)
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 10000);
                }
            });
        });

        // Enhance file upload areas for touch devices
        if (this.isMobile) {
            document.querySelectorAll('.file-upload-label').forEach(label => {
                label.style.minHeight = '44px';
                label.style.display = 'flex';
                label.style.alignItems = 'center';
                label.style.justifyContent = 'center';
            });
        }
    }

    // Table responsive enhancements
    setupTableResponsive() {
        // Add responsive wrapper to tables if not present
        document.querySelectorAll('.data-table:not(.mobile-friendly)').forEach(table => {
            if (!table.closest('.table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
                table.classList.add('mobile-friendly');
            }
        });

        // Enhance table actions for mobile
        if (this.isMobile) {
            document.querySelectorAll('.actions').forEach(actions => {
                actions.style.flexWrap = 'wrap';
                actions.style.gap = '0.25rem';
            });
        }
    }

    // Utility function to show notifications
    static showNotification(message, type = 'success', duration = 5000) {
        // Remove existing notifications
        document.querySelectorAll('.admin-notification').forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = `admin-notification alert alert-${type} fade-in`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
            ${message}
            <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after duration
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);

        // Add click to dismiss
        notification.addEventListener('click', function(e) {
            if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
                this.remove();
            }
        });
    }

    // Utility function for API calls
    static async apiCall(endpoint, data = null, method = 'GET') {
        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
            };
            
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            
            const response = await fetch(endpoint, options);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            return { 
                success: false, 
                message: 'Network error: ' + error.message,
                error: error.message
            };
        }
    }

    // Utility to debounce function calls
    static debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    }

    // Utility to check if element is in viewport
    static isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    // Utility to smoothly scroll to element
    static smoothScrollTo(element, duration = 500) {
        const targetPosition = element.getBoundingClientRect().top + window.pageYOffset;
        const startPosition = window.pageYOffset;
        const distance = targetPosition - startPosition;
        let startTime = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const run = ease(timeElapsed, startPosition, distance, duration);
            window.scrollTo(0, run);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }

        function ease(t, b, c, d) {
            t /= d / 2;
            if (t < 1) return c / 2 * t * t + b;
            t--;
            return -c / 2 * (t * (t - 2) - 1) + b;
        }

        requestAnimationFrame(animation);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AdminDashboard();
    
    // Add touch detection class
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
        document.documentElement.classList.add('touch-device');
    } else {
        document.documentElement.classList.add('no-touch-device');
    }
});

// Add utility CSS for dynamic elements
const utilityStyles = `
    .admin-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .admin-notification button {
        background: none;
        border: none;
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.3s;
        padding: 0.25rem;
        border-radius: 3px;
    }
    
    .admin-notification button:hover {
        opacity: 1;
        background: rgba(0,0,0,0.1);
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { 
            opacity: 0; 
            transform: translateY(-20px) scale(0.95); 
        }
        to { 
            opacity: 1; 
            transform: translateY(0) scale(1); 
        }
    }
    
    .pulse {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    .slide-in-left {
        animation: slideInLeft 0.3s ease-out;
    }
    
    @keyframes slideInLeft {
        from { 
            opacity: 0; 
            transform: translateX(-100%); 
        }
        to { 
            opacity: 1; 
            transform: translateX(0); 
        }
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(5px);
    }
    
    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid var(--admin-accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Print styles */
    @media print {
        .no-print {
            display: none !important;
        }
        
        .admin-header,
        .sidebar,
        .btn {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0 !important;
        }
    }
`;

// Inject utility styles
if (!document.querySelector('#admin-utility-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'admin-utility-styles';
    styleSheet.textContent = utilityStyles;
    document.head.appendChild(styleSheet);
}

// Export for global access
window.AdminDashboard = AdminDashboard;