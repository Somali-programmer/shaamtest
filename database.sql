-- =============================================
-- Shaam-Show Website Database
-- Complete Admin & Frontend Database Structure
-- =============================================

-- Create database
CREATE DATABASE IF NOT EXISTS if0_40546630_youtuber;
USE if0_40546630_youtuber;

-- =============================================
-- ADMIN USERS TABLE
-- =============================================
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('super_admin', 'admin', 'editor') DEFAULT 'admin',
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- BLOG POSTS TABLE
-- =============================================
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(300) UNIQUE NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    featured_image VARCHAR(500),
    category_id INT,
    tags JSON,
    reading_time INT DEFAULT 5,
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    status ENUM('published', 'draft', 'archived') DEFAULT 'draft',
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    author_id INT NOT NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_author (author_id),
    INDEX idx_published (published_at),
    FULLTEXT idx_search (title, content, excerpt)
);

-- =============================================
-- POST CATEGORIES TABLE
-- =============================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(150) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    post_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id),
    INDEX idx_slug (slug)
);

-- =============================================
-- VIDEOS TABLE (YouTube Integration)
-- =============================================
CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(300) UNIQUE NOT NULL,
    youtube_url VARCHAR(500) NOT NULL,
    youtube_id VARCHAR(20) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(500),
    duration VARCHAR(20),
    category VARCHAR(100),
    tags JSON,
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('published', 'draft') DEFAULT 'published',
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_youtube_id (youtube_id),
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    INDEX idx_published (published_at)
);

-- =============================================
-- GALLERY TABLE
-- =============================================
CREATE TABLE gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    description TEXT,
    category VARCHAR(100),
    tags JSON,
    file_size INT,
    file_type VARCHAR(50),
    dimensions VARCHAR(20),
    is_featured BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_featured (is_featured),
    INDEX idx_order (display_order)
);

-- =============================================
-- COMMENTS TABLE
-- =============================================
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NULL,
    video_id INT NULL,
    parent_id INT DEFAULT NULL,
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(150),
    author_website VARCHAR(200),
    author_ip VARCHAR(45),
    content TEXT NOT NULL,
    status ENUM('approved', 'pending', 'spam', 'trash') DEFAULT 'pending',
    likes INT DEFAULT 0,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_post (post_id),
    INDEX idx_video (video_id),
    INDEX idx_parent (parent_id),
    INDEX idx_status (status),
    INDEX idx_email (author_email),
    INDEX idx_created (created_at),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- =============================================
-- LIKES TABLE
-- Track likes for posts and videos
-- =============================================
CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NULL,
    video_id INT NULL,
    user_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post (post_id),
    INDEX idx_video (video_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
);

-- =============================================
-- SUBSCRIBERS TABLE (Newsletter)
-- =============================================
CREATE TABLE subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) UNIQUE NOT NULL,
    name VARCHAR(100),
    status ENUM('active', 'inactive', 'unsubscribed') DEFAULT 'active',
    subscription_source VARCHAR(100) DEFAULT 'website',
    confirmation_token VARCHAR(100),
    is_confirmed BOOLEAN DEFAULT FALSE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL,
    last_notified TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_subscribed (subscribed_at)
);

-- =============================================
-- CONTACT FORM SUBMISSIONS
-- =============================================
CREATE TABLE contact_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('new', 'read', 'replied', 'spam') DEFAULT 'new',
    admin_notes TEXT,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_created (created_at)
);

-- =============================================
-- WEBSITE SETTINGS TABLE
-- =============================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json', 'text') DEFAULT 'string',
    setting_group VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group)
);

-- =============================================
-- USER SESSIONS TABLE (For admin security)
-- =============================================
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(100) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (session_token),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- =============================================
-- ACTIVITY LOG TABLE (Admin actions tracking)
-- =============================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    table_name VARCHAR(50),
    record_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- =============================================
-- FILE UPLOADS TABLE (Track all uploaded files)
-- =============================================
CREATE TABLE file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    mime_type VARCHAR(100),
    upload_type ENUM('image', 'video', 'document', 'other') DEFAULT 'image',
    uploaded_by INT NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_in_table VARCHAR(50),
    used_in_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_type (upload_type),
    INDEX idx_used (is_used),
    INDEX idx_created (created_at),
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- =============================================
-- INSERT DEFAULT DATA
-- =============================================

INSERT INTO admin_users (username, password, email, full_name, role, is_active) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@shaam-show.com', 'Website Administrator', 'super_admin', TRUE);

-- Insert default categories
INSERT INTO categories (name, slug, description) VALUES 
('Technology', 'technology', 'Posts about technology and innovation'),
('Lifestyle', 'lifestyle', 'Lifestyle and personal development content'),
('Entertainment', 'entertainment', 'Entertainment and media discussions'),
('Education', 'education', 'Educational content and tutorials'),
('News', 'news', 'Latest news and updates');

INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, description, is_public) VALUES 
('site_title', 'Shaam-Show', 'string', 'general', 'Website title', TRUE),
('site_description', 'Professional podcast and content creation platform', 'string', 'general', 'Website description', TRUE),
('site_keywords', 'podcast, content, youtube, blog, media', 'string', 'general', 'Website keywords', TRUE),
('admin_email', 'admin@shaam-show.com', 'string', 'general', 'Administrator email', FALSE),
('posts_per_page', '10', 'number', 'content', 'Number of posts per page', TRUE),
('comments_enabled', 'true', 'boolean', 'content', 'Enable comments system', TRUE),
('newsletter_enabled', 'true', 'boolean', 'newsletter', 'Enable newsletter subscription', TRUE),
('maintenance_mode', 'false', 'boolean', 'general', 'Maintenance mode status', FALSE),
('social_facebook', 'https://facebook.com/shaam-show', 'string', 'social', 'Facebook page URL', TRUE),
('social_youtube', 'https://youtube.com/shaam-show', 'string', 'social', 'YouTube channel URL', TRUE),
('social_twitter', 'https://twitter.com/shaam-show', 'string', 'social', 'Twitter profile URL', TRUE),
('social_instagram', 'https://instagram.com/shaam-show', 'string', 'social', 'Instagram profile URL', TRUE),
('theme_primary_color', '#FF6B35', 'string', 'appearance', 'Primary theme color', TRUE),
('theme_secondary_color', '#FF4757', 'string', 'appearance', 'Secondary theme color', TRUE),
('contact_email', 'contact@shaam-show.com', 'string', 'contact', 'Contact form email', TRUE);

INSERT INTO posts (title, slug, content, excerpt, category_id, reading_time, status, author_id, published_at) VALUES 
('Welcome to Shaam-Show - Your Content Journey Begins Here', 'welcome-to-shaam-show', '<p>Welcome to your new Shaam-Show website! This is your first blog post. You can edit or delete this post and start creating amazing content for your audience.</p><p>Shaam-Show is designed to help content creators like you share your voice with the world. Whether you are a podcaster, YouTuber, or blogger, this platform provides everything you need to manage your content professionally.</p><h3>Getting Started</h3><p>Here are some things you can do with your new website:</p><ul><li>Create and manage blog posts</li><li>Embed YouTube videos</li><li>Upload and manage photo galleries</li><li>Moderate user comments</li><li>Track your website statistics</li></ul><p>Start by exploring the admin dashboard and customizing your website settings. Remember to change your admin password and update your website information.</p><p>Happy content creating!</p>', 'Welcome to your new Shaam-Show website! Learn how to get started with content creation and management.', 1, 3, 'published', 1, NOW());

INSERT INTO videos (title, slug, youtube_url, youtube_id, description, category, status) VALUES 
('Introduction to Shaam-Show Platform', 'introduction-to-shaam-show-platform', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', 'Learn how to use the Shaam-Show platform to manage your content and grow your audience.', 'Tutorial', 'published');

-- Insert sample gallery image data (you'll need to upload actual images)
INSERT INTO gallery (title, image_path, description, category, is_featured) VALUES 
('Podcast Studio Setup', 'gallery_studio_1.jpg', 'Professional podcast recording studio setup', 'Behind the Scenes', TRUE),
('Content Creation Workshop', 'gallery_workshop_1.jpg', 'Hosting a content creation workshop', 'Events', FALSE);

INSERT INTO comments (post_id, author_name, author_email, content, status, is_admin) VALUES 
(1, 'Website Admin', 'admin@shaam-show.com', 'This is a sample comment from the administrator. You can approve, delete, or reply to comments in the admin panel.', 'approved', TRUE);

-- =============================================
-- CREATE DATABASE USER (Optional - for security)
-- =============================================
-- CREATE USER 'shaam_user'@'localhost' IDENTIFIED BY 'secure_password_123';
-- GRANT ALL PRIVILEGES ON youtuber_website.* TO 'shaam_user'@'localhost';
-- FLUSH PRIVILEGES;

-- =============================================
-- DATABASE OPTIMIZATION
-- =============================================
OPTIMIZE TABLE admin_users, posts, categories, videos, gallery, comments, subscribers, contact_submissions, settings, user_sessions, activity_logs, file_uploads;

-- Display success message
SELECT 'Database created successfully!' as message;