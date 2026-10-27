USE more_and_more;

ALTER TABLE admin_users
  ADD COLUMN username VARCHAR(80) NULL AFTER name;

UPDATE admin_users
SET username = CONCAT('admin', id)
WHERE username IS NULL;

ALTER TABLE admin_users
  MODIFY COLUMN username VARCHAR(80) NOT NULL,
  MODIFY COLUMN email VARCHAR(254) NULL,
  ADD UNIQUE KEY uq_admin_username (username);

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

ALTER TABLE orders
  ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER id,
  ADD KEY idx_orders_customer_created (customer_id, created_at),
  ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS customer_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_customer_login_ip_time (ip_hash, attempted_at),
  KEY idx_customer_login_time (attempted_at)
) ENGINE=InnoDB;
