            </div><!-- /.content-area -->
        </main><!-- /.main-content -->
    </div><!-- /.admin-container -->

    <!-- JavaScript -->
    <script src="assets/js/admin.js"></script>
    
    <?php if (isset($additional_scripts)): ?>
        <?php echo $additional_scripts; ?>
    <?php endif; ?>
    
    <!-- Global JavaScript enhancements -->
    <script>
        // Enhanced error handling
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
        });

        // Enhanced offline detection
        window.addEventListener('online', function() {
            AdminDashboard.showNotification('Connection restored', 'success', 3000);
        });

        window.addEventListener('offline', function() {
            AdminDashboard.showNotification('You are currently offline', 'error', 5000);
        });

        // Enhanced form recovery
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-save form data
            const forms = document.querySelectorAll('form[method="post"]');
            forms.forEach(form => {
                const formId = form.id || 'form_' + Math.random().toString(36).substr(2, 9);
                
                // Restore saved data
                const savedData = localStorage.getItem('form_auto_save_' + formId);
                if (savedData) {
                    try {
                        const data = JSON.parse(savedData);
                        Object.keys(data).forEach(key => {
                            const element = form.querySelector(`[name="${key}"]`);
                            if (element && (element.type !== 'password')) {
                                element.value = data[key];
                            }
                        });
                    } catch (e) {
                        console.warn('Could not restore form data:', e);
                    }
                }

                // Auto-save on input
                const saveFormData = AdminDashboard.debounce(() => {
                    const formData = {};
                    const inputs = form.querySelectorAll('input, textarea, select');
                    inputs.forEach(input => {
                        if (input.name && input.type !== 'password') {
                            formData[input.name] = input.value;
                        }
                    });
                    localStorage.setItem('form_auto_save_' + formId, JSON.stringify(formData));
                }, 1000);

                form.addEventListener('input', saveFormData);
                
                // Clear saved data on successful submit
                form.addEventListener('submit', function() {
                    setTimeout(() => {
                        localStorage.removeItem('form_auto_save_' + formId);
                    }, 1000);
                });
            });

            // Enhanced image lazy loading
            const images = document.querySelectorAll('img[data-src]');
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        imageObserver.unobserve(img);
                    }
                });
            });

            images.forEach(img => imageObserver.observe(img));

            // Enhanced touch feedback
            if ('ontouchstart' in window) {
                document.addEventListener('touchstart', function() {}, { passive: true });
            }

            // Service Worker Registration (if needed)
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/admin-sw.js')
                    .then(registration => {
                        console.log('SW registered: ', registration);
                    })
                    .catch(registrationError => {
                        console.log('SW registration failed: ', registrationError);
                    });
            }
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            // Report load time to analytics
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            console.log('Page load time:', loadTime + 'ms');
            
            // Hide loading indicator
            const loadingIndicator = document.getElementById('globalLoading');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        });

        // Enhanced print functionality
        function printPage() {
            window.print();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save forms
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const form = document.querySelector('form');
                if (form) {
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            }
            
            // Escape key to close modals/dropdowns
            if (e.key === 'Escape') {
                document.querySelectorAll('.user-dropdown').forEach(d => d.style.display = 'none');
            }
        });
    </script>

    <!-- Screen reader only styles -->
    <style>
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        
        .sr-only-focusable:active,
        .sr-only-focusable:focus {
            position: static;
            width: auto;
            height: auto;
            overflow: visible;
            clip: auto;
            white-space: normal;
        }
    </style>
</body>
</html>