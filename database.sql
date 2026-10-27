CREATE DATABASE IF NOT EXISTS more_and_more
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE more_and_more;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL,
  email VARCHAR(254) NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_username (username),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(32) NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_username (username),
  KEY idx_customer_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_category_name (name),
  UNIQUE KEY uq_category_slug (slug),
  KEY idx_category_active_sort (is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  sku VARCHAR(80) NOT NULL,
  description TEXT NOT NULL,
  specifications JSON NULL,
  price BIGINT UNSIGNED NOT NULL,
  discounted_price BIGINT UNSIGNED NULL,
  stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_slug (slug),
  UNIQUE KEY uq_product_sku (sku),
  KEY idx_products_category_publish (category_id, is_published),
  KEY idx_products_price (price),
  KEY idx_products_stock (stock_quantity),
  FULLTEXT KEY ft_products_search (name, description, sku),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(180) NOT NULL DEFAULT '',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_product_images_order (product_id, is_primary, sort_order),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NULL,
  order_number VARCHAR(32) NOT NULL,
  tracking_hash CHAR(64) NOT NULL,
  customer_name VARCHAR(160) NOT NULL,
  customer_phone VARCHAR(32) NOT NULL,
  customer_email VARCHAR(254) NULL,
  delivery_address VARCHAR(500) NOT NULL,
  city VARCHAR(100) NOT NULL,
  district VARCHAR(100) NOT NULL,
  postal_code VARCHAR(20) NOT NULL DEFAULT '',
  order_notes TEXT NULL,
  subtotal BIGINT UNSIGNED NOT NULL,
  discount_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
  delivery_charge BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total BIGINT UNSIGNED NOT NULL,
  payment_method VARCHAR(24) NOT NULL DEFAULT 'cod',
  payment_status VARCHAR(24) NOT NULL DEFAULT 'unpaid',
  status ENUM('Pending','Confirmed','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  admin_notes TEXT NULL,
  cancellation_reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_number (order_number),
  KEY idx_orders_phone_created (customer_phone, created_at),
  KEY idx_orders_email_created (customer_email, created_at),
  KEY idx_orders_status_created (status, created_at),
  KEY idx_orders_payment_status (payment_status),
  KEY idx_orders_customer_created (customer_id, created_at),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(180) NOT NULL,
  sku VARCHAR(80) NOT NULL,
  unit_price BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  KEY idx_order_items_product (product_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(24) NULL,
  new_status VARCHAR(24) NOT NULL,
  change_note VARCHAR(500) NULL,
  changed_by_admin_id BIGINT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_history (order_id, changed_at),
  CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_order_history_admin FOREIGN KEY (changed_by_admin_id) REFERENCES admin_users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_login_attempts_ip_time (ip_hash, attempted_at),
  KEY idx_login_attempts_time (attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_customer_login_ip_time (ip_hash, attempted_at),
  KEY idx_customer_login_time (attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_tracking_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tracking_attempts_ip_time (ip_hash, attempted_at),
  KEY idx_tracking_attempts_time (attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(254) NOT NULL,
  phone VARCHAR(32) NULL,
  subject VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_created (created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name, slug, sort_order) VALUES
('Home Accessories', 'home-accessories', 1),
('Electrical Appliances and Machines', 'electrical-appliances-machines', 2),
('Kitchen Accessories', 'kitchen-accessories', 3),
('Electronics and Gadgets', 'electronics-gadgets', 4),
('Mobile Accessories', 'mobile-accessories', 5),
('Lighting and Electrical Items', 'lighting-electrical-items', 6),
('Cleaning Equipment', 'cleaning-equipment', 7),
('Home and Living', 'home-and-living', 8),
('Beauty and Personal Care', 'beauty-personal-care', 9),
('Fashion Accessories', 'fashion-accessories', 10),
('Tools and Hardware', 'tools-hardware', 11),
('Gifts and Lifestyle', 'gifts-lifestyle', 12),
('Other Products', 'other-products', 13);

INSERT IGNORE INTO store_settings (setting_key, setting_value) VALUES
('site_name', 'More & More'),
('contact_phone', ''),
('whatsapp_number', ''),
('contact_email', ''),
('store_address', 'Sri Lanka'),
('currency', 'LKR'),
('hero_image', ''),
('hero_title', 'Good things for everyday living'),
('hero_subtitle', 'Thoughtful finds for home, work and everything in between.'),
('promo_message', 'Thoughtful finds for a more beautiful everyday'),
('social_instagram', ''),
('social_facebook', ''),
('delivery_enabled', '1'),
('cod_enabled', '1'),
('delivery_default_charge', '500'),
('delivery_free_threshold', '0'),
('delivery_service_areas', 'Islandwide'),
('policy_privacy', 'We use your details only to process and deliver your order.'),
('policy_terms', 'Orders are subject to product availability and confirmation by our team.'),
('policy_returns', 'Contact us within 7 days of delivery about an unused item in its original packaging.');
