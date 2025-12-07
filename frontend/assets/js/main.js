// Modern JavaScript for Static Frontend
class PodcastWebsite {
    constructor() {
        this.init();
    }

    init() {
        this.setupThemeToggle();
        this.setupLightbox();
        this.setupSmoothScroll();
        this.setupVideoModals();
        this.setupLikeButtons();
        this.setupMobileMenu();
    }

    // Dark/Light Mode Toggle
    setupThemeToggle() {
        // If a dedicated ThemeManager is present, let it handle toggling
        if (window.themeManager) return;

        const toggle = document.getElementById('themeToggle');
        const icon = toggle?.querySelector('i');

        // Check for saved theme or prefer-color-scheme
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
            document.body.classList.add('dark-mode');
            if (icon) icon.className = 'fas fa-sun';
        }

        toggle?.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');

            if (icon) {
                icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
    }

    // Lightbox for Gallery
    setupLightbox() {
        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightbox-img');
        const closeBtn = document.querySelector('.lightbox-close');
        const galleryItems = document.querySelectorAll('.gallery-item');

        galleryItems.forEach(item => {
            item.addEventListener('click', () => {
                const imgSrc = item.querySelector('img').src;
                lightboxImg.src = imgSrc;
                lightbox.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        });

        closeBtn?.addEventListener('click', () => {
            lightbox.style.display = 'none';
            document.body.style.overflow = 'auto';
        });

        lightbox?.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                lightbox.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }

    // Smooth Scrolling for Navigation
    setupSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Video Modal Functionality
    setupVideoModals() {
        // Use event delegation so dynamically added video cards will work
        document.addEventListener('click', (e) => {
            const playBtn = e.target.closest('.play-button');
            if (!playBtn) return;

            const card = playBtn.closest('.video-card');
            if (!card) return;

            const videoId = card.dataset.videoId;
            if (videoId) {
                this.openVideoModal(videoId);
            }
        });
    }

    openVideoModal(videoId) {
        // Create modal element with animation classes
        const modal = document.createElement('div');
        modal.className = 'video-modal video-modal--enter';

        modal.innerHTML = `
            <div class="video-modal__inner" style="position: relative; width: 90%; max-width: 800px;">
                <button class="video-close" aria-label="Close video">×</button>
                <div style="position: relative; padding-bottom: 56.25%; height: 0;">
                    <iframe 
                        src="https://www.youtube.com/embed/${videoId}?autoplay=1" 
                        class="video-modal__iframe"
                        frameborder="0" 
                        allow="autoplay; encrypted-media" 
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
        `;

        // Inject minimal CSS for animations if not already present
        if (!document.getElementById('video-modal-styles')) {
            const css = document.createElement('style');
            css.id = 'video-modal-styles';
            css.textContent = `
                .video-modal { position: fixed; inset: 0; display:flex; align-items:center; justify-content:center; background: rgba(0,0,0,0.85); z-index:3000; opacity:0; transition: opacity 220ms ease; }
                .video-modal--enter { opacity: 0; }
                .video-modal--enter.video-modal--visible { opacity: 1; }
                .video-modal--leave { opacity: 0; }
                .video-modal__inner { transform: translateY(8px); transition: transform 220ms ease; }
                .video-modal--visible .video-modal__inner { transform: translateY(0); }
                .video-close { position: absolute; top: -40px; right: 0; background: none; border: none; color: white; font-size: 2rem; cursor: pointer; }
                .video-modal__iframe { position: absolute; top:0; left:0; width:100%; height:100%; }
            `;
            document.head.appendChild(css);
        }

        document.body.appendChild(modal);
        // Force reflow then add visible class to trigger transition
        requestAnimationFrame(() => {
            modal.classList.add('video-modal--visible');
        });
        document.body.style.overflow = 'hidden';

        // Track the play (try to send DB id if available)
        try {
            // Look up db id from any matching card on page
            const card = document.querySelector(`.video-card[data-video-id="${videoId}"]`);
            const dbId = card ? card.dataset.videoDbId : null;
            this.trackVideoPlay(dbId, videoId);
        } catch (e) {
            console.error('Tracking error', e);
        }

        // Close modal functionality with animation
        const closeFn = () => {
            modal.classList.remove('video-modal--visible');
            modal.classList.add('video-modal--leave');
            // wait for transition then remove
            modal.addEventListener('transitionend', function onEnd() {
                modal.removeEventListener('transitionend', onEnd);
                if (modal.parentNode) modal.parentNode.removeChild(modal);
                document.body.style.overflow = 'auto';
            });
        };

        const closeBtn = modal.querySelector('.video-close');
        closeBtn.addEventListener('click', closeFn);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeFn();
            }
        });
    }

    // Send lightweight analytics to server for video plays
    async trackVideoPlay(dbId, youtubeId) {
        const payload = { video_db_id: dbId || null, youtube_id: youtubeId || null, url: window.location.pathname };
        try {
            await fetch('../api/track-video-play.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        } catch (e) {
            // If network call fails, fall back to console logging (analytics best-effort)
            console.log('Video play tracked (fallback):', payload);
        }
    }

    // Like Button Functionality
    setupLikeButtons() {
        // If a dedicated LikeSystem exists, let it handle like buttons
        if (window.likeSystem) return;

        // Use event delegation so dynamically loaded content works (fallback)
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.like-btn');
            if (!btn) return;

            // prevent double clicking
            if (btn.classList.contains('liked')) return;

            const postId = btn.dataset.postId ? parseInt(btn.dataset.postId) : null;
            const videoId = btn.dataset.videoId ? parseInt(btn.dataset.videoId) : null;
            const likeCountEl = btn.querySelector('.like-count');

            try {
                const currentlyLiked = btn.classList.contains('liked');
                const payload = { post_id: postId || null, video_id: videoId || null, action: currentlyLiked ? 'unlike' : 'like' };
                const res = await fetch('../api/like.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!res.ok) throw new Error('Network response was not ok');
                const json = await res.json();

                if (json && (json.status === 'ok' || json.status === 'unliked' || json.status === 'exists')) {
                    const newCount = json.likes ?? null;
                    if (newCount !== null && likeCountEl) {
                        likeCountEl.textContent = newCount;
                    }
                    if (json.status === 'ok') {
                        btn.classList.add('liked');
                        showNotification('Liked', 'success');
                    } else if (json.status === 'unliked') {
                        btn.classList.remove('liked');
                        showNotification('Unliked', 'success');
                    } else {
                        showNotification('Already liked', 'success');
                    }
                } else if (json && json.error) {
                    showNotification('Error: ' + json.error, 'error');
                }
            } catch (err) {
                console.error('Like API error', err);
                showNotification('Unable to register like', 'error');
            }
        });
    }

    // Mobile Menu Toggle
    setupMobileMenu() {
        if (window.innerWidth <= 768) {
            const navContainer = document.querySelector('.nav-container');
            const navMenu = document.querySelector('.nav-menu');
            
            // Create mobile menu button
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.style.cssText = `
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
            `;

            navContainer.appendChild(mobileMenuBtn);

            mobileMenuBtn.addEventListener('click', () => {
                const isVisible = navMenu.style.display === 'flex';
                navMenu.style.display = isVisible ? 'none' : 'flex';
                navMenu.style.flexDirection = 'column';
                navMenu.style.position = 'absolute';
                navMenu.style.top = '100%';
                navMenu.style.left = '0';
                navMenu.style.right = '0';
                navMenu.style.background = 'linear-gradient(135deg, var(--primary-orange), var(--orange-dark))';
                navMenu.style.padding = '1rem';
            });

            // Handle window resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    navMenu.style.display = 'flex';
                    navMenu.style.flexDirection = 'row';
                    navMenu.style.position = 'static';
                    navMenu.style.background = 'none';
                    navMenu.style.padding = '0';
                }
            });
        }
    }
}

// Initialize the website when DOM is loaded
// ===== FIXED DARK MODE SCRIPT =====
class ThemeManager {
    constructor() {
        this.init();
    }

    init() {
        this.loadTheme();
        this.setupThemeToggle();
        this.detectSystemTheme();
    }

    // Load theme from localStorage or system preference
    loadTheme() {
        const savedTheme = (localStorage.getItem('theme') || '').toLowerCase();
        const html = document.documentElement;
        console.debug('ThemeManager.loadTheme -> savedTheme:', savedTheme);

        if (savedTheme === 'dark' || savedTheme === 'light') {
            this.setTheme(savedTheme);
        } else {
            // Use system preference
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.setTheme(systemPrefersDark ? 'dark' : 'light');
        }
    }

    // Set theme with proper body class
    setTheme(theme) {
        const body = document.body;
        const html = document.documentElement;
        console.debug('ThemeManager.setTheme ->', theme);

        if (theme === 'dark') {
            body.classList.add('dark-mode');
            body.classList.remove('light-mode');
            html.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        } else {
            body.classList.remove('dark-mode');
            body.classList.add('light-mode');
            html.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
        }

        this.updateToggleIcon(theme);

        // Update ARIA attributes for accessibility and assist debugging
        // synchronize any existing toggles (updateToggleIcon also sets ARIA)
        try { this.updateToggleIcon(theme); } catch (e) { /* ignore */ }

        // Update temporary debug indicator on page (if present)
        try {
            const dbg = document.getElementById('theme-debug-indicator');
            if (dbg) dbg.textContent = `theme: ${theme}`;
        } catch (e) { /* ignore */ }
    }

    // Update toggle button icon
    updateToggleIcon(theme) {
        // Update icon for all toggles on the page (supports multiple toggles)
        const toggles = document.querySelectorAll('.theme-toggle');
        toggles.forEach(toggleBtn => {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            // Keep ARIA state in sync for each toggle
            try {
                toggleBtn.setAttribute('aria-checked', theme === 'dark' ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
            } catch (e) { /* ignore */ }
        });
    }

    // Setup theme toggle button
    setupThemeToggle() {
        // Attach listener to all elements with the theme-toggle class (supports multiple toggles)
        const toggles = document.querySelectorAll('.theme-toggle');
        if (!toggles || toggles.length === 0) return;

        toggles.forEach(t => {
            // Avoid adding duplicate listeners
            if (t.__themeToggleInit) return;
            t.__themeToggleInit = true;

            t.addEventListener('click', (ev) => {
                ev.preventDefault();
                const isDark = document.body.classList.contains('dark-mode');
                const newTheme = isDark ? 'light' : 'dark';
                console.debug('Theme toggle clicked -> switching to', newTheme);
                this.setTheme(newTheme);
            });
        });

        // Delegated handler as a fallback (catches toggles added later)
        if (!this._themeDelegateInit) {
            this._themeDelegateInit = true;
            document.addEventListener('click', (ev) => {
                const t = ev.target.closest ? ev.target.closest('.theme-toggle') : null;
                if (!t) return;
                ev.preventDefault();
                const isDark = document.body.classList.contains('dark-mode');
                const newTheme = isDark ? 'light' : 'dark';
                console.debug('Delegated theme toggle -> switching to', newTheme);
                this.setTheme(newTheme);
            });
        }

        // Add a temporary visible debug indicator to help troubleshooting
        if (!document.getElementById('theme-debug-indicator')) {
            try {
                const dbg = document.createElement('div');
                dbg.id = 'theme-debug-indicator';
                dbg.style.cssText = 'position:fixed;bottom:10px;left:10px;padding:6px 10px;background:rgba(0,0,0,0.6);color:#fff;border-radius:6px;font-size:12px;z-index:99999;';
                dbg.textContent = `theme: ${document.documentElement.getAttribute('data-theme') || (document.body.classList.contains('dark-mode') ? 'dark' : 'light')}`;
                document.body.appendChild(dbg);
            } catch (e) { /* ignore DOM exceptions */ }
        }
    }

    // Detect system theme changes
    detectSystemTheme() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = (e) => {
            const savedTheme = localStorage.getItem('theme');
            // Only auto-change if user hasn't manually set theme
            if (!savedTheme) {
                const matches = e.matches !== undefined ? e.matches : mediaQuery.matches;
                this.setTheme(matches ? 'dark' : 'light');
            }
        };

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', handler);
        } else if (typeof mediaQuery.addListener === 'function') {
            // Older Safari and some browsers
            mediaQuery.addListener(handler);
        }
    }
}

// ===== FIXED LIKE SYSTEM =====
class LikeSystem {
    constructor() {
        this.init();
    }

    init() {
        this.setupLikeButtons();
        this.loadLikeCounts();
    }

    setupLikeButtons() {
        document.addEventListener('click', (e) => {
            const likeBtn = e.target.closest('.like-btn');
            if (likeBtn) {
                e.preventDefault();
                this.handleLike(likeBtn);
            }
        });
    }

    async handleLike(button) {
        const postId = button.dataset.postId || null;
        const videoId = button.dataset.videoId || null;
        const likeCount = button.querySelector('.like-count');
        const likeIcon = button.querySelector('i');
        
        // Update UI immediately for better UX
        const isLiked = button.classList.contains('liked');
        const newLiked = !isLiked;
        
        button.classList.toggle('liked', newLiked);
        if (likeIcon) {
            likeIcon.className = newLiked ? 'fas fa-heart' : 'far fa-heart';
        }
        
        try {
            const response = await fetch('../api/like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    post_id: postId,
                    video_id: videoId,
                    action: newLiked ? 'like' : 'unlike'
                })
            });

            const result = await response.json();
            
            if (result.success) {
                if (likeCount) {
                    likeCount.textContent = result.count;
                }
                this.showNotification(newLiked ? 'Liked!' : 'Unliked', 'success');
            } else {
                // Revert UI if API fails
                button.classList.toggle('liked', isLiked);
                if (likeIcon) {
                    likeIcon.className = isLiked ? 'fas fa-heart' : 'far fa-heart';
                }
                this.showNotification('Error: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            // Revert UI on network error
            button.classList.toggle('liked', isLiked);
            if (likeIcon) {
                likeIcon.className = isLiked ? 'fas fa-heart' : 'far fa-heart';
            }
            this.showNotification('Network error. Please try again.', 'error');
        }
    }

    async loadLikeCounts() {
        const likeButtons = document.querySelectorAll('.like-btn');
        
        for (const button of likeButtons) {
            const postId = button.dataset.postId || null;
            const videoId = button.dataset.videoId || null;
            const likeCount = button.querySelector('.like-count');
            
            if (likeCount && (postId || videoId)) {
                try {
                    const url = `../api/like.php?${postId ? 'post_id=' + postId : 'video_id=' + videoId}`;
                    const response = await fetch(url);
                    const result = await response.json();
                    
                    if (result.success) {
                        likeCount.textContent = result.count;
                        
                        // Set initial liked state
                        if (result.liked) {
                            button.classList.add('liked');
                            const likeIcon = button.querySelector('i');
                            if (likeIcon) {
                                likeIcon.className = 'fas fa-heart';
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error loading likes:', error);
                    likeCount.textContent = '0';
                }
            }
        }
    }

    showNotification(message, type) {
        // Simple notification
        const notification = document.createElement('div');
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background: ${type === 'success' ? '#4CAF50' : '#f44336'};
            color: white;
            border-radius: 5px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}


document.addEventListener('DOMContentLoaded', () => {
    // Initialize ThemeManager first so PodcastWebsite defers to it
    try {
        window.themeManager = new ThemeManager();
        console.info('ThemeManager initialized -> data-theme:', document.documentElement.getAttribute('data-theme'), 'body.class:', document.body.className);
    } catch (e) {
        // If ThemeManager isn't available for any reason, fall back to existing logic
        console.warn('ThemeManager not initialized:', e);
    }

    // Initialize LikeSystem so PodcastWebsite defers like handling
    try {
        window.likeSystem = new LikeSystem();
    } catch (e) {
        console.warn('LikeSystem not initialized:', e);
    }

    new PodcastWebsite();
});

// Utility function to load JSON data
async function loadJSON(url) {
    try {
        const response = await fetch(url);
        return await response.json();
    } catch (error) {
        console.error('Error loading data:', error);
        return null;
    }
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 2rem;
        background: ${type === 'success' ? '#4CAF50' : '#f44336'};
        color: white;
        border-radius: 5px;
        z-index: 4000;
        animation: slideIn 0.3s ease;
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .liked {
        color: #FF4757 !important;
    }
    
    .liked i {
        color: #FF4757 !important;
    }
`;
document.head.appendChild(style);