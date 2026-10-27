-- ============================================================
-- FOOD FESTIVAL FIESTA — Database schema (version 2) for a FRESH install
-- WARNING: this file drops and recreates every table. If you already have
-- data you want to keep, use database/upgrade_v2.sql instead.
-- Import in phpMyAdmin (XAMPP/WAMP) before running the app.
-- ============================================================

CREATE DATABASE IF NOT EXISTS food_festival_fiesta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE food_festival_fiesta;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS vendors;
DROP TABLE IF EXISTS visitors;
DROP TABLE IF EXISTS stalls;
DROP TABLE IF EXISTS admins;

-- ---------- Stalls ----------
-- seed_rating / seed_reviews hold the stall's results from previous editions;
-- rating / reviews_count are always recalculated as: previous edition + verified reviews.
CREATE TABLE stalls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stall_num INT NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    cuisine VARCHAR(60) NOT NULL,
    description TEXT,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    seed_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
    seed_reviews INT NOT NULL DEFAULT 0,
    rating DECIMAL(3,2) NOT NULL DEFAULT 0,
    reviews_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stalls_status (status)
) ENGINE=InnoDB;

-- ---------- Menu items (one stall -> many items) ----------
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stall_id INT NOT NULL,
    item_name VARCHAR(120) NOT NULL,
    FOREIGN KEY (stall_id) REFERENCES stalls(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Vendors (one stall -> one vendor account) ----------
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stall_id INT NOT NULL,
    contact_name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    username VARCHAR(30) NULL,
    password_hash VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vendor_username (username),
    UNIQUE KEY uq_vendor_stall (stall_id),
    FOREIGN KEY (stall_id) REFERENCES stalls(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Visitors (each registration = one pass) ----------
CREATE TABLE visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    visit_date DATE,
    guests INT NOT NULL DEFAULT 1,
    pass_id VARCHAR(30) NOT NULL UNIQUE,
    checked_in TINYINT(1) NOT NULL DEFAULT 0,
    checked_in_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Feedback: one review per pass per stall ----------
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stall_id INT NOT NULL,
    visitor_id INT NULL,
    visitor_name VARCHAR(120) NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_one_review (stall_id, visitor_id),
    FOREIGN KEY (stall_id) REFERENCES stalls(id) ON DELETE CASCADE,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Organizer accounts ----------
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ---------- Login attempts (brute-force protection) ----------
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('admin','vendor') NOT NULL,
    username VARCHAR(60) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts (scope, attempted_at)
) ENGINE=InnoDB;

-- ---------- Audit log (who did what, when, from where) ----------
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type VARCHAR(20) NOT NULL,
    actor_name VARCHAR(80) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details VARCHAR(255) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- Seed data (so the site is not empty on first run)
-- Organizer login:  organizer / organizer2026   (change it before going live)
-- Demo vendor login: ranjit / vendor2026        (stall #7)
-- Demo visitor passes for testing reviews: FEST-DEMO0001, FEST-DEMO0002
-- ============================================================
INSERT INTO admins (username, password_hash) VALUES ('organizer', '$2y$10$jWuGtidi3/VoHwbOdB2cu.yokWQDe3sVE3HAX57r7iwSK0v2.G6YO');

INSERT INTO stalls (stall_num, name, cuisine, description, status, seed_rating, seed_reviews) VALUES
(7,  'Tandoori Trails',    'North Indian', 'Classic tandoor-fired favourites.', 'approved', 4.6, 18),
(12, 'Wok This Way',       'Indo-Chinese', 'Wok-tossed Indo-Chinese classics.', 'approved', 4.3, 24),
(3,  'Chaat Ka Adda',      'Street Food',  'Tangy, spicy street-side chaat.',   'approved', 4.8, 30),
(19, 'Cafe Waffle Waffle', 'Desserts',     'Waffles, shakes and brownies.',     'approved', 4.5, 20),
(22, 'Punjab Da Tadka',    'Punjabi',      'Home-style Punjabi comfort food.',  'approved', 4.7, 26),
(15, 'Sushi Junction',     'Japanese',     'Rolls, ramen and miso soup.',       'approved', 4.2, 13);

INSERT INTO menu_items (stall_id, item_name) VALUES
(1, 'Tandoori Chaap'), (1, 'Amritsari Kulcha'), (1, 'Lassi'),
(2, 'Hakka Noodles'), (2, 'Chilli Manchurian'), (2, 'Spring Rolls'),
(3, 'Pani Puri'), (3, 'Bhel Puri'), (3, 'Aloo Tikki'),
(4, 'Belgian Waffle'), (4, 'Oreo Shake'), (4, 'Molten Brownie'),
(5, 'Sarson Da Saag'), (5, 'Makki Roti'), (5, 'Lassi'),
(6, 'California Roll'), (6, 'Veg Ramen'), (6, 'Miso Soup');

INSERT INTO vendors (stall_id, contact_name, phone, username, password_hash) VALUES
(1, 'Ranjit Singh',    '9876500001', 'ranjit', '$2y$10$4.8/gPj2t50CGwrRqgc0ROUyMs3cT8oKqimHAmYiyKHs2rb45IcC6'),
(2, 'Aman Kapoor',     '9876500002', NULL, NULL),
(3, 'Simran Kaur',     '9876500003', NULL, NULL),
(4, 'Neha Sharma',     '9876500004', NULL, NULL),
(5, 'Gurpreet Singh',  '9876500005', NULL, NULL),
(6, 'Karan Mehta',     '9876500006', NULL, NULL);

INSERT INTO visitors (name, phone, visit_date, guests, pass_id) VALUES
('Demo Visitor One', '9876543210', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 'FEST-DEMO0001'),
('Demo Visitor Two', '9876543211', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1, 'FEST-DEMO0002');

INSERT INTO feedback (stall_id, visitor_id, visitor_name, rating, comment) VALUES
(3, NULL, 'Simran K.', 5, 'Best pani puri in the whole festival, tangy water was perfect.'),
(5, NULL, 'Raghav S.', 5, 'Tasted like home. The saag was outstanding.'),
(6, NULL, 'Meher A.', 4, 'Good rolls but the queue moved slowly.');

-- Bring rating / reviews_count in line with baseline + reviews above
UPDATE stalls s
LEFT JOIN (SELECT stall_id, COUNT(*) AS c, SUM(rating) AS t FROM feedback GROUP BY stall_id) f ON f.stall_id = s.id
SET s.reviews_count = s.seed_reviews + COALESCE(f.c, 0),
    s.rating = CASE WHEN s.seed_reviews + COALESCE(f.c, 0) = 0 THEN 0
                    ELSE ROUND((s.seed_rating * s.seed_reviews + COALESCE(f.t, 0)) / (s.seed_reviews + COALESCE(f.c, 0)), 2) END;
