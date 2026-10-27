-- ============================================================
-- FOOD FESTIVAL FIESTA — Upgrade from version 1 to version 2
-- Run this ONCE in phpMyAdmin (Import tab) if you already have the v1 database
-- and want to KEEP your existing data. Do not run it twice.
-- Adds: stall approval status, vendor accounts, gate check-in,
--       verified reviews, login rate limiting and an audit log.
-- ============================================================
USE food_festival_fiesta;

-- ---------- Stalls: approval status + rating baseline ----------
ALTER TABLE stalls
    ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER description,
    ADD COLUMN seed_rating  DECIMAL(3,2) NOT NULL DEFAULT 0,
    ADD COLUMN seed_reviews INT NOT NULL DEFAULT 0,
    ADD INDEX idx_stalls_status (status);

-- Everything that already exists was live in v1, so keep it approved
UPDATE stalls SET status = 'approved';

-- Split each stall's current rating into "previous editions" (baseline) + reviews already in the feedback table
UPDATE stalls s
LEFT JOIN (SELECT stall_id, COUNT(*) AS c, SUM(rating) AS t FROM feedback GROUP BY stall_id) f ON f.stall_id = s.id
SET s.seed_reviews = GREATEST(s.reviews_count - COALESCE(f.c, 0), 0),
    s.seed_rating  = CASE WHEN s.reviews_count - COALESCE(f.c, 0) > 0
                          THEN LEAST(5, GREATEST(0, (s.rating * s.reviews_count - COALESCE(f.t, 0)) / (s.reviews_count - COALESCE(f.c, 0))))
                          ELSE 0 END;

-- ---------- Vendors: login accounts ----------
ALTER TABLE vendors
    ADD COLUMN username VARCHAR(30) NULL,
    ADD COLUMN password_hash VARCHAR(255) NULL,
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD UNIQUE KEY uq_vendor_username (username),
    ADD UNIQUE KEY uq_vendor_stall (stall_id);

-- Demo vendor login for the first seeded stall: username ranjit / password vendor2026
UPDATE vendors SET username = 'ranjit', password_hash = '$2y$10$4.8/gPj2t50CGwrRqgc0ROUyMs3cT8oKqimHAmYiyKHs2rb45IcC6'
WHERE stall_id = (SELECT id FROM stalls WHERE stall_num = 7 LIMIT 1) AND username IS NULL;

-- ---------- Visitors: gate check-in ----------
ALTER TABLE visitors
    ADD COLUMN checked_in TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN checked_in_at DATETIME NULL;

INSERT IGNORE INTO visitors (name, phone, visit_date, guests, pass_id) VALUES
('Demo Visitor One', '9876543210', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2, 'FEST-DEMO0001'),
('Demo Visitor Two', '9876543211', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1, 'FEST-DEMO0002');

-- ---------- Feedback: one review per pass per stall ----------
ALTER TABLE feedback
    ADD COLUMN visitor_id INT NULL AFTER stall_id,
    ADD CONSTRAINT fk_feedback_visitor FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE SET NULL,
    ADD UNIQUE KEY uq_one_review (stall_id, visitor_id);

-- ---------- New tables ----------
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('admin','vendor') NOT NULL,
    username VARCHAR(60) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts (scope, attempted_at)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type VARCHAR(20) NOT NULL,
    actor_name VARCHAR(80) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details VARCHAR(255) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at)
);

-- ---------- Recalculate every stall's rating with the new formula ----------
UPDATE stalls s
LEFT JOIN (SELECT stall_id, COUNT(*) AS c, SUM(rating) AS t FROM feedback GROUP BY stall_id) f ON f.stall_id = s.id
SET s.reviews_count = s.seed_reviews + COALESCE(f.c, 0),
    s.rating = CASE WHEN s.seed_reviews + COALESCE(f.c, 0) = 0 THEN 0
                    ELSE ROUND((s.seed_rating * s.seed_reviews + COALESCE(f.t, 0)) / (s.seed_reviews + COALESCE(f.c, 0)), 2) END;
