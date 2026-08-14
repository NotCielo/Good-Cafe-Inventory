-- Good Cafe Inventory System
-- Import this file in phpMyAdmin (create a database first, e.g. `good_cafe`, then Import)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Users (2 built-in roles: admin, staff)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed accounts. Password for BOTH is: goodcafe0125
-- (change this after first login by updating password_hash with PHP's password_hash())
INSERT INTO users (username, password_hash, full_name, role) VALUES
('goodcafeAdmin', '$2b$10$4R1F4sKZidR4I2DcpP8eYu68Iqa0a3QnrE5oGkNIlihE/hIg.dSUq', 'Admin', 'admin'),
('goodcafeStaff', '$2b$10$4R1F4sKZidR4I2DcpP8eYu68Iqa0a3QnrE5oGkNIlihE/hIg.dSUq', 'Staff', 'staff');

-- ------------------------------------------------------------
-- Categories (2-level: top-level "department" + sub-category)
-- parent_id NULL  => top-level (e.g. Kitchen, Station, Others)
-- parent_id set   => sub-category (e.g. Rice Meals under Kitchen)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NULL,
  name VARCHAR(100) NOT NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (parent_id, name) VALUES
(NULL, 'Kitchen'),
(NULL, 'Station');

INSERT INTO categories (parent_id, name) VALUES
(1, 'Rice Meals'), (1, 'Pasta'), (1, 'Snacks'),
(2, 'Coffee'), (2, 'Milk'), (2, 'Syrup'), (2, 'Pastry'),
(2, 'Sauce'), (2, 'Cups & Packaging');

-- ------------------------------------------------------------
-- Items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  reorder_level DECIMAL(10,2) NOT NULL DEFAULT 0,
  quantity_needed DECIMAL(10,2) NULL,
  buy_location VARCHAR(200) NULL,
  supplier_contact VARCHAR(150) NULL,
  photo_path VARCHAR(255) NULL,
  flagged_for_purchase TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Stock logs (every +/- adjustment made by staff or admin)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  staff_name VARCHAR(100) NOT NULL,
  change_amount DECIMAL(10,2) NOT NULL,
  resulting_quantity DECIMAL(10,2) NOT NULL,
  logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Extra buy items: things to purchase that are NOT tracked in inventory
-- (e.g. a one-off item you don't want as a stocked inventory item)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS extra_buy_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  quantity_needed DECIMAL(10,2) NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  buy_location VARCHAR(200) NULL,
  supplier_contact VARCHAR(150) NULL,
  is_bought TINYINT(1) NOT NULL DEFAULT 0,
  added_by VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Purchase log: record-only history of "Log Purchase" actions,
-- for BOTH inventory items and extra (non-inventory) buy items.
-- Does not change items.quantity — that only happens via Update Stock.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NULL,
  extra_item_id INT NULL,
  item_name VARCHAR(150) NOT NULL,
  quantity_needed DECIMAL(10,2) NULL,
  logged_by VARCHAR(100) NOT NULL,
  logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
  FOREIGN KEY (extra_item_id) REFERENCES extra_buy_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
