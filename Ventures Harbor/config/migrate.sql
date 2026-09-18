-- ============================================================
-- VENTURES HARBOR — SAFE MIGRATION SCRIPT
-- ============================================================
-- Run this ONLY if you already have a working venture_harbor
-- database from before the Group Chat feature and founder_user_id
-- column were added, and you do NOT want to lose your existing
-- data (users, ventures, members, etc).
--
-- This script is safe to run multiple times — it checks for each
-- change before applying it.
--
-- Runs against whichever database is already selected, so it works on any
-- install regardless of the database's name (local `venture_harbor`, the live
-- `u127049976_venture`, etc). Every check below resolves the target with
-- DATABASE() — do NOT add a `USE <name>;` line here: it hardcodes the local
-- name and makes the script fail on the live server with
-- "#1044 Access denied ... to database 'venture_harbor'".
--
-- Usage:
--   CLI         mysql -u root --default-character-set=utf8mb4 <dbname> < config/migrate.sql
--   phpMyAdmin  select the database first, then Import this file
-- ============================================================

-- 1. Add founder_user_id column to ventures (if missing)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'founder_user_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN founder_user_id INT DEFAULT NULL AFTER id',
  'SELECT "founder_user_id already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Backfill founder_user_id for existing rows by matching founder_name
--    to a user with the same name (best-effort; only fills rows that are
--    still NULL, so it won't overwrite anything you've already set).
UPDATE ventures v
JOIN users u ON u.name = v.founder_name
SET v.founder_user_id = u.id
WHERE v.founder_user_id IS NULL;

-- 3. Add the foreign key constraint (if missing)
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND CONSTRAINT_NAME = 'ventures_ibfk_founder'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE ventures ADD CONSTRAINT ventures_ibfk_founder FOREIGN KEY (founder_user_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT "founder_user_id FK already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Create the Group Chat table (if missing)
CREATE TABLE IF NOT EXISTS venture_chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venture_id INT NOT NULL,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Add Razorpay-ready columns to transactions (if missing)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'payment_gateway'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE transactions ADD COLUMN payment_gateway VARCHAR(20) DEFAULT ''manual'', ADD COLUMN gateway_order_id VARCHAR(100) DEFAULT NULL, ADD COLUMN gateway_payment_id VARCHAR(100) DEFAULT NULL',
  'SELECT "transactions payment gateway columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Create the settings table (if missing) with sensible defaults
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_name', 'Ventures Harbor'),
('support_email', 'support@venturesharbor.com'),
('commitment_fee_percent', '0.5'),
('kyc_required_to_join', '1'),
('kyc_required_to_create', '1'),
('razorpay_enabled', '0'),
('razorpay_key_id', '');

-- 7. Ensure there is a dedicated platform admin account not tied to any
--    venture. If your existing admin account (e.g. Rahul Kumar) already
--    owns/joined ventures, demote it back to role='user' and create this
--    separate admin instead — an admin should not personally own ventures.
--    Uncomment and adjust the line below if needed:
--    UPDATE users SET role = 'user' WHERE email = 'rahul.kumar@example.com';
INSERT IGNORE INTO users (id, name, email, phone, password_hash, avatar, role, kyc_status, city, occupation, bio, age)
VALUES (9, 'Platform Admin', 'admin@venturesharbor.in', '+91 90000 00000', '$2y$10$GlK0/NvmuSc9vFxaOTzx7eTtUxAGRCu.GfxUFHBYDGxY.HCP.FuZm', 'PA', 'admin', 'verified', 'Mumbai', 'Platform Administrator', 'Manages the Ventures Harbor platform.', NULL);

-- 8. Add CA booking management columns (if missing)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ca_bookings' AND COLUMN_NAME = 'ca_name'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ca_bookings ADD COLUMN ca_name VARCHAR(100) DEFAULT NULL, ADD COLUMN admin_notes TEXT DEFAULT NULL',
  'SELECT "ca_bookings management columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 9. Add avatar_url column to users (if missing) for real profile photo uploads
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_url'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL AFTER avatar',
  'SELECT "avatar_url already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 10. Add image support to Group Chat (if missing)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_chat_messages' AND COLUMN_NAME = 'image_path'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE venture_chat_messages ADD COLUMN image_path VARCHAR(255) DEFAULT NULL, MODIFY COLUMN message TEXT DEFAULT NULL',
  'SELECT "image_path already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 11. Create legal_templates table (if missing) so admins can manage downloadable
--     agreement templates from the Admin Panel instead of hardcoded files.
CREATE TABLE IF NOT EXISTS legal_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO legal_templates (id, title, description, file_name, file_path) VALUES
(1, 'Standard Partnership Deed (India)', 'Detailed draft covering capital contribution, equity split, and exit guidelines.', 'Partnership-Deed-Template.html', 'assets/templates/partnership-deed-template.html'),
(2, 'Mutual Non-Disclosure Agreement (NDA)', 'Protect business ideas and intellectual property before meetings.', 'NDA-Template.html', 'assets/templates/nda-template.html'),
(3, 'Founder Agreement Template', 'Pre-incorporation guidelines, role distributions, and vesting clauses.', 'Founder-Agreement-Template.html', 'assets/templates/founder-agreement-template.html');

-- 12. Feature 2/3/4/5: new venture-level columns — preferred meeting venue,
--     founder type (individual/brand, displayed as "Individual"/"Company" —
--     commitment fee is a flat 0.5% for both), required skills, application
--     deadline, and the timestamp that opens the 24-hour post-meetup quit window.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'preferred_meeting_venue'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures
     ADD COLUMN preferred_meeting_venue VARCHAR(255) DEFAULT NULL AFTER location,
     ADD COLUMN founder_type VARCHAR(20) NOT NULL DEFAULT ''individual'' AFTER founder_bio,
     ADD COLUMN required_skills TEXT DEFAULT NULL AFTER requirements,
     ADD COLUMN application_deadline DATE DEFAULT NULL AFTER timeline,
     ADD COLUMN quit_window_opened_at TIMESTAMP NULL DEFAULT NULL AFTER application_deadline',
  'SELECT "ventures Feature 2/3/4/5 columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 13. Feature 1: meetup completion tracking (physical-meetup-gated quit window)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'meetups' AND COLUMN_NAME = 'completed_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE meetups ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER status',
  'SELECT "meetups.completed_at already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 14. Feature 5: skills column on users (comma-separated free-text tags)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'skills'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN skills VARCHAR(500) DEFAULT NULL AFTER bio',
  'SELECT "users.skills already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 15. Feature 4: applications table for the active-partner selection process
CREATE TABLE IF NOT EXISTS venture_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venture_id INT NOT NULL,
  user_id INT NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'active',
  invested_amount INT NOT NULL,
  message TEXT DEFAULT NULL,
  skill_match_note VARCHAR(255) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'selected', 'rejected', 'confirmed'
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  decided_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY unique_application (venture_id, user_id),
  FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Feature 6: monthly financial reports + itemized expenses
CREATE TABLE IF NOT EXISTS venture_financial_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venture_id INT NOT NULL,
  report_month CHAR(7) NOT NULL, -- 'YYYY-MM'
  total_revenue INT NOT NULL DEFAULT 0,
  total_expenses INT NOT NULL DEFAULT 0,
  profit_loss INT NOT NULL DEFAULT 0,
  cash_flow_summary TEXT DEFAULT NULL,
  balance_sheet_summary TEXT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_report (venture_id, report_month),
  FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS venture_financial_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  category VARCHAR(100) NOT NULL,
  amount INT NOT NULL,
  FOREIGN KEY (report_id) REFERENCES venture_financial_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. (Legacy) Wallet feature columns. The wallet system has since been
--     removed — only the commitment fee is ever charged online, and exits
--     now create a 'refund_request' transaction that admins process
--     manually, instead of crediting an in-app wallet balance. These
--     columns are kept (unused) for backward compatibility with existing
--     installs; principal_amount/fee_amount/payout_note/admin_notes are
--     reused by the refund-request flow (see step 20).
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'wallet_balance'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN wallet_balance INT NOT NULL DEFAULT 0 AFTER kyc_status',
  'SELECT "users.wallet_balance already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'principal_amount'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE transactions
     ADD COLUMN venture_id INT DEFAULT NULL AFTER user_id,
     ADD COLUMN principal_amount INT NOT NULL DEFAULT 0 AFTER amount,
     ADD COLUMN fee_amount INT NOT NULL DEFAULT 0 AFTER principal_amount,
     ADD COLUMN payout_note TEXT DEFAULT NULL,
     ADD COLUMN admin_notes TEXT DEFAULT NULL',
  'SELECT "transactions wallet columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND CONSTRAINT_NAME = 'transactions_ibfk_venture'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE transactions ADD CONSTRAINT transactions_ibfk_venture FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE SET NULL',
  'SELECT "transactions venture_id FK already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 18. Founder-uploaded venture documents (business plan PDFs etc.), shown
--     in the venture detail Documents section for anyone reviewing it.
CREATE TABLE IF NOT EXISTS venture_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venture_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Public "Contact Us" message queue, reviewable from the Admin Panel.
CREATE TABLE IF NOT EXISTS contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  subject VARCHAR(150) DEFAULT NULL,
  message TEXT NOT NULL,
  user_id INT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  admin_notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Founder-credibility fields (replaces KYC): social profile links and a
--     free-text past-experience blurb so other users can evaluate a
--     founder's/member's background instead of relying on a KYC badge.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'linkedin_url'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users
     ADD COLUMN linkedin_url VARCHAR(255) DEFAULT NULL AFTER skills,
     ADD COLUMN twitter_url VARCHAR(255) DEFAULT NULL AFTER linkedin_url,
     ADD COLUMN instagram_url VARCHAR(255) DEFAULT NULL AFTER twitter_url,
     ADD COLUMN website_url VARCHAR(255) DEFAULT NULL AFTER instagram_url,
     ADD COLUMN past_experience TEXT DEFAULT NULL AFTER website_url',
  'SELECT "users credibility columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 21. Cover/banner images: one for ventures (founder-uploaded, shown behind
--     the venture-detail hero) and one for user profiles (LinkedIn-style
--     banner behind the avatar).
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'cover_image'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN cover_image VARCHAR(255) DEFAULT NULL AFTER icon_color',
  'SELECT "ventures.cover_image already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'cover_url'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN cover_url VARCHAR(255) DEFAULT NULL AFTER avatar_url',
  'SELECT "users.cover_url already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 22: Track who scheduled each meetup, so only that user can cancel it
-- (everyone else on the meetup only gets Accept/Decline).
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'meetups' AND COLUMN_NAME = 'created_by'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE meetups
     ADD COLUMN created_by INT DEFAULT NULL AFTER completed_at,
     ADD CONSTRAINT meetups_ibfk_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT "meetups.created_by already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Best-effort backfill for existing installs: whoever is recorded as
-- 'accepted' and is also the venture's founder is far and away the most
-- likely person to have scheduled the meetup (the create action always
-- auto-accepts its creator). Only fills rows still NULL.
UPDATE meetups m
JOIN ventures v ON v.id = m.venture_id
SET m.created_by = v.founder_user_id
WHERE m.created_by IS NULL AND v.founder_user_id IS NOT NULL;

-- 23. Venture logo/avatar: small square founder-uploaded image shown instead
--     of the emoji icon badge (WhatsApp-DP style), independent of cover_image.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'logo_url'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL AFTER cover_image',
  'SELECT "ventures.logo_url already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 24. Account deactivation: closing an account soft-deletes it (the row and
--     all its history are kept for records/audit) instead of hard-deleting.
--     A deactivated user cannot log in until an admin reactivates them.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_deactivated'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN is_deactivated TINYINT(1) NOT NULL DEFAULT 0 AFTER role',
  'SELECT "users.is_deactivated already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 25. Partner count is no longer capped — min_investment governs who can join,
--     so an unset/zero max_members now simply means "unlimited". Existing rows
--     keep their old value harmlessly; nothing enforces it anymore.

-- 26. Active-partner applications now carry a résumé (required at apply time)
--     instead of a proposed investment amount. The founder reviews the résumé
--     before selecting; payment happens only after selection.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_applications' AND COLUMN_NAME = 'resume_path'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE venture_applications ADD COLUMN resume_path VARCHAR(255) DEFAULT NULL AFTER message',
  'SELECT "venture_applications.resume_path already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_applications' AND COLUMN_NAME = 'resume_name'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE venture_applications ADD COLUMN resume_name VARCHAR(255) DEFAULT NULL AFTER resume_path',
  'SELECT "venture_applications.resume_name already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- invested_amount is now optional (active applicants don't propose an amount).
ALTER TABLE venture_applications MODIFY COLUMN invested_amount INT NOT NULL DEFAULT 0;

-- 27. Founders can describe how the raised capital will be spent. Optional,
--     free text, sits directly under Full Description on the create form.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'use_of_funds'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN use_of_funds TEXT DEFAULT NULL AFTER full_description',
  'SELECT "ventures.use_of_funds already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 28. Refund requests are now split into two distinct flows, and the payout
--     destination is captured as structured bank fields instead of one free-text
--     blob. refund_type tells the admin panel which flow a row came from:
--       'exit'             — quit a venture inside the 24h post-meetup window
--       'account_deletion' — closed the account and claimed fees back
--     An 'exit' row with status 'waived' is an "exit without refund" — the member
--     left accepting that the commitment fee is forfeited, so nothing is payable.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'refund_type'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE transactions ADD COLUMN refund_type VARCHAR(30) DEFAULT NULL AFTER status',
  'SELECT "transactions.refund_type already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'bank_account_name'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE transactions ADD COLUMN bank_account_name VARCHAR(120) DEFAULT NULL AFTER gateway_payment_id',
  'SELECT "transactions.bank_account_name already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'bank_account_number'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE transactions ADD COLUMN bank_account_number VARCHAR(40) DEFAULT NULL AFTER bank_account_name',
  'SELECT "transactions.bank_account_number already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'bank_ifsc'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE transactions ADD COLUMN bank_ifsc VARCHAR(20) DEFAULT NULL AFTER bank_account_number',
  'SELECT "transactions.bank_ifsc already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'bank_name'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE transactions ADD COLUMN bank_name VARCHAR(120) DEFAULT NULL AFTER bank_ifsc',
  'SELECT "transactions.bank_name already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Existing refund rows all pre-date the split and were created by the venture
-- exit flow, so backfill them rather than leaving the admin panel with blanks.
UPDATE transactions SET refund_type = 'exit'
WHERE type = 'refund_request' AND refund_type IS NULL;

-- Wishlist / "Interested" bookmarks on venture cards. CREATE TABLE IF NOT
-- EXISTS is already idempotent, so this needs no information_schema guard.
CREATE TABLE IF NOT EXISTS venture_wishlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  venture_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_venture (user_id, venture_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Public Q&A on the venture detail page. See config/setup.sql for why the
-- founder's reply lives on the question row rather than in a thread table.
CREATE TABLE IF NOT EXISTS venture_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venture_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  question TEXT NOT NULL,
  answer TEXT DEFAULT NULL,
  answered_by INT DEFAULT NULL,
  answered_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (answered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Investment & Exit terms on ventures. These replace the old "Expected Revenue
-- Timeline" field in the creation form (the `timeline` column itself is left
-- alone so existing rows keep their data). Every column is nullable: ventures
-- created before this block simply have no exit terms recorded, and the detail
-- page hides whatever the founder didn't fill in.

-- Equity Distribution leads the block: it used to be a decorative dropdown on
-- the Partner Settings step that was never saved, and now sits at the top of
-- Investment & Exit Details alongside the rest of the terms partners read.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'equity_distribution'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN equity_distribution VARCHAR(20) DEFAULT NULL AFTER application_deadline',
  'SELECT "ventures.equity_distribution already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'lockin_period'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN lockin_period VARCHAR(60) DEFAULT NULL AFTER application_deadline',
  'SELECT "ventures.lockin_period already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'expected_exit_timeline'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN expected_exit_timeline VARCHAR(120) DEFAULT NULL AFTER lockin_period',
  'SELECT "ventures.expected_exit_timeline already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'exit_options'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN exit_options TEXT DEFAULT NULL AFTER expected_exit_timeline',
  'SELECT "ventures.exit_options already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'early_exit_allowed'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN early_exit_allowed TINYINT(1) DEFAULT NULL AFTER exit_options',
  'SELECT "ventures.early_exit_allowed already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'early_exit_notice_period'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN early_exit_notice_period VARCHAR(120) DEFAULT NULL AFTER early_exit_allowed',
  'SELECT "ventures.early_exit_notice_period already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'early_exit_conditions'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN early_exit_conditions TEXT DEFAULT NULL AFTER early_exit_notice_period',
  'SELECT "ventures.early_exit_conditions already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'ownership_transfer_allowed'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN ownership_transfer_allowed TINYINT(1) DEFAULT NULL AFTER early_exit_conditions',
  'SELECT "ventures.ownership_transfer_allowed already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'ownership_transfer_approval'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN ownership_transfer_approval VARCHAR(30) DEFAULT NULL AFTER ownership_transfer_allowed',
  'SELECT "ventures.ownership_transfer_approval already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'profit_distribution_frequency'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN profit_distribution_frequency VARCHAR(30) DEFAULT NULL AFTER ownership_transfer_approval',
  'SELECT "ventures.profit_distribution_frequency already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'exit_valuation_method'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN exit_valuation_method VARCHAR(40) DEFAULT NULL AFTER profit_distribution_frequency',
  'SELECT "ventures.exit_valuation_method already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'exit_valuation_notes'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures ADD COLUMN exit_valuation_notes TEXT DEFAULT NULL AFTER exit_valuation_method',
  'SELECT "ventures.exit_valuation_notes already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- Venture media gallery. Replaces the single ventures.cover_image banner with
-- an ordered set of images, uploaded video clips and YouTube/Vimeo embeds, so a
-- founder can show the business from several angles.
--
-- ventures.cover_image is deliberately KEPT, not dropped: the browse grid, the
-- homepage grid and VH.card.coverInner() all need exactly one representative
-- image, and the API keeps the column pointing at whichever gallery item sorts
-- first. That makes the gallery purely additive — nothing that renders a card
-- had to change, and an install that never adds media behaves exactly as before.
-- ============================================================

CREATE TABLE IF NOT EXISTS venture_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venture_id INT NOT NULL,
  -- 'image' and 'video' are files under uploads/venture_media; 'embed' is a
  -- YouTube/Vimeo URL and stores nothing on disk.
  media_type ENUM('image','video','embed') NOT NULL DEFAULT 'image',
  file_path VARCHAR(255) DEFAULT NULL,
  embed_url VARCHAR(500) DEFAULT NULL,
  -- Poster frame. There is no ffmpeg on the host, so an uploaded video cannot
  -- have a frame extracted server-side; for embeds this is the provider's
  -- thumbnail URL, and for uploads it stays NULL and the UI shows a play tile.
  thumbnail_path VARCHAR(500) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  uploaded_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_venture_sort (venture_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Force venture_media onto the same collation as `ventures`.
--
-- The CREATE above names a character set but no collation, so each server picks
-- its own default for utf8mb4. That is fine until two tables disagree: MariaDB
-- 10.4 (local XAMPP) defaults to utf8mb4_general_ci, while MariaDB 11.4+ (the
-- live host) defaults to utf8mb4_uca1400_ai_ci. On an install created under the
-- older default, a freshly created venture_media therefore ends up on a
-- different collation from the long-standing `ventures` table, and the backfill
-- below dies with:
--   #1267 Illegal mix of collations ... for operation '='
-- Reading the target collation off `ventures` rather than hardcoding one keeps
-- this correct on both, and repairs installs where the table was already
-- created with the wrong collation. Idempotent: it is a no-op once they match.
SET @ventures_collation = (
  SELECT TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures'
);
SET @media_collation = (
  SELECT TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_media'
);
SET @sql = IF(
  @ventures_collation IS NOT NULL
    AND @media_collation IS NOT NULL
    AND @media_collation <> @ventures_collation
    AND @ventures_collation LIKE 'utf8mb4%',
  CONCAT('ALTER TABLE venture_media CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @ventures_collation),
  'SELECT "venture_media collation already matches ventures, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Carry every existing cover image into the gallery as its first item, so
-- ventures that already had a banner keep it and simply gain the ability to add
-- more. Guarded by NOT EXISTS so re-running the migration cannot duplicate it.
--
-- Both sides of the path comparison are normalised to one explicit collation.
-- The ALTER above should already have aligned the two tables, but this keeps
-- the statement correct even where it couldn't (e.g. a legacy install whose
-- `ventures` table is not utf8mb4 at all), instead of failing the whole
-- migration and leaving every step after this one unapplied. utf8mb4_bin is
-- the right choice for a file path: byte-exact, case-sensitive.
INSERT INTO venture_media (venture_id, media_type, file_path, sort_order, uploaded_by)
SELECT v.id, 'image', v.cover_image, 0, v.founder_user_id
FROM ventures v
WHERE v.cover_image IS NOT NULL
  AND v.cover_image <> ''
  AND NOT EXISTS (
    SELECT 1 FROM venture_media m
    WHERE m.venture_id = v.id
      AND CONVERT(m.file_path USING utf8mb4) COLLATE utf8mb4_bin
        = CONVERT(v.cover_image USING utf8mb4) COLLATE utf8mb4_bin
  );

-- 29. Venture lifecycle: listing expiry, a one-time extension, cancellation,
--     and the automatic refunds a cancellation triggers.
--
--     Until now `days_left` was a plain integer captured at creation and never
--     decremented — nothing anywhere reduced it, so a listing's "15 Days Left"
--     badge said 15 forever and no listing could ever actually end. The real
--     deadline now lives in `listing_ends_at`; `days_left` is kept as a derived
--     display value (recomputed by the sweep in config/venture-lifecycle.php)
--     so every existing card, sort and detail view keeps working unchanged.
--
--     Statuses gained by this step, alongside the existing 'active'/'suspended':
--       'expired'   — the listing period ended without full funding and the
--                     founder still has to choose: extend once, or cancel.
--       'cancelled' — terminal. Refunds for every paying partner are opened
--                     automatically and tracked in the Admin Panel.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'listing_ends_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE ventures
     ADD COLUMN listing_ends_at DATETIME DEFAULT NULL AFTER days_left,
     ADD COLUMN extension_count TINYINT NOT NULL DEFAULT 0 AFTER listing_ends_at,
     ADD COLUMN extended_at TIMESTAMP NULL DEFAULT NULL AFTER extension_count,
     ADD COLUMN expired_at TIMESTAMP NULL DEFAULT NULL AFTER extended_at,
     ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER expired_at,
     ADD COLUMN cancelled_by VARCHAR(20) DEFAULT NULL AFTER cancelled_at,
     ADD COLUMN cancel_reason VARCHAR(255) DEFAULT NULL AFTER cancelled_by',
  'SELECT "ventures lifecycle columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill the deadline for listings that pre-date this column.
--
-- Live listings are dated forward from NOW(), deliberately NOT from created_at.
-- Because days_left never counted down, an existing row's value is not "days
-- since creation minus elapsed" — it is simply the number the listing has been
-- advertising all along. Dating from created_at would therefore retro-expire a
-- large share of every existing install's catalogue the moment this migration
-- runs (a venture created 30 days ago showing "15 Days Left" would jump
-- straight to expired). Dating from now honours what partners were actually
-- told and starts the real clock from the upgrade.
UPDATE ventures
SET listing_ends_at = DATE_ADD(NOW(), INTERVAL GREATEST(COALESCE(days_left, 0), 0) DAY)
WHERE listing_ends_at IS NULL AND status = 'active';

-- Anything not currently live (suspended rows) is dated from creation instead —
-- it is not on display, so there is no advertised countdown to honour.
UPDATE ventures
SET listing_ends_at = DATE_ADD(created_at, INTERVAL GREATEST(COALESCE(days_left, 0), 0) DAY)
WHERE listing_ends_at IS NULL;

-- The sweep's hot query is "active listings whose deadline has passed".
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND INDEX_NAME = 'idx_lifecycle_sweep'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE ventures ADD INDEX idx_lifecycle_sweep (status, listing_ends_at)',
  'SELECT "ventures.idx_lifecycle_sweep already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Lifecycle tunables, editable in the settings table without a code change.
--   venture_decision_grace_days — how long an 'expired' listing waits for the
--     founder to extend or cancel before the platform cancels it for them.
--     Without this an ignored listing would sit in 'expired' forever, holding
--     its partners' commitment fees hostage with no route to a refund. Doubles
--     as the reminder count: the founder is nagged once a day for exactly this
--     many days (see step 39).
--   venture_max_extension_days — upper bound on the single extension a founder
--     may grant themselves.
--   venture_lifecycle_last_sweep — bookkeeping written by the sweep itself so
--     it runs at most once every few minutes instead of on every page load.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('venture_decision_grace_days', '2'),
('venture_max_extension_days', '30');

-- refund_type gains a third flow, 'venture_cancelled': opened automatically for
-- every partner who paid a commitment fee into a venture that was then
-- cancelled. Unlike the other two flows nobody filled in a form, so the bank
-- columns start NULL and the partner supplies them afterwards from their
-- Payment Statement. No schema change is needed — refund_type is already
-- VARCHAR(30) and the bank columns are already nullable — but the value is
-- documented here because the Admin Panel filters on it.

-- Applications left open on a cancelled venture are closed with the status
-- 'cancelled' rather than 'rejected': nobody was turned down, and the applicant
-- never paid, so there is nothing to refund. Also needs no schema change —
-- venture_applications.status is VARCHAR(20).

-- 30. PayU payment gateway.
--
--     PayU is a redirect gateway: the user leaves the site to pay and PayU
--     POSTs the result back. Nothing may be granted before that reply is
--     verified, so the intent to pay has to survive the round trip somewhere —
--     that is this table.
--
--     It is deliberately separate from `transactions` rather than a 'pending'
--     row in it. `transactions` means money actually moved: it drives the
--     user's Payment Statement, the admin earnings figures and the refund
--     queue. Most payment attempts that reach a gateway are never completed
--     (abandoned at the bank page, card declined, browser closed), and putting
--     those in `transactions` would inflate every one of those numbers with
--     charges that never happened. A `transactions` row is written only once
--     PayU confirms the payment.
CREATE TABLE IF NOT EXISTS payment_intents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  -- Our own reference, sent to PayU as `txnid` and echoed back in the reply.
  -- This is what identifies the payer on the callback, because the PHP session
  -- cookie is not sent on PayU's cross-site POST.
  txn_id VARCHAR(50) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  venture_id INT NOT NULL,
  -- Set when this payment confirms a selected active-partner application,
  -- NULL for a silent partner joining directly.
  application_id INT DEFAULT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'silent',
  -- The pledged investment (recorded only, settled offline) and the commitment
  -- fee, which is the sole amount actually charged. Both are stored at
  -- initiate time so the callback recomputes nothing from the posted reply.
  invested_amount INT NOT NULL DEFAULT 0,
  fee_amount INT NOT NULL DEFAULT 0,
  amount INT NOT NULL DEFAULT 0,
  -- created → completed | failed | cancelled. The created→completed move is a
  -- guarded UPDATE, which is what makes a duplicated callback (user refreshes
  -- the return page, PayU retries) harmless.
  status VARCHAR(20) NOT NULL DEFAULT 'created',
  gateway VARCHAR(20) NOT NULL DEFAULT 'payu',
  gateway_mode VARCHAR(10) NOT NULL DEFAULT 'test',
  -- PayU's own ids/status from the reply, kept for reconciliation against the
  -- PayU dashboard when a payment is disputed.
  gateway_payment_id VARCHAR(100) DEFAULT NULL,
  gateway_status VARCHAR(50) DEFAULT NULL,
  error_message VARCHAR(255) DEFAULT NULL,
  -- The full verified reply, so a payment can be audited later without asking
  -- PayU. Never contains the salt.
  raw_response TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (venture_id) REFERENCES ventures(id) ON DELETE CASCADE,
  INDEX idx_intent_lookup (status, created_at),
  INDEX idx_intent_user (user_id, venture_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Same collation alignment as venture_media above, and for the same reason:
-- payment_intents.txn_id is compared against transactions.txn_id, and a table
-- created under MariaDB 11's utf8mb4_uca1400_ai_ci default cannot be compared
-- with the older utf8mb4_general_ci tables without erroring.
SET @txn_collation = (
  SELECT TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions'
);
SET @intent_collation = (
  SELECT TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_intents'
);
SET @sql = IF(
  @txn_collation IS NOT NULL
    AND @intent_collation IS NOT NULL
    AND @intent_collation <> @txn_collation
    AND @txn_collation LIKE 'utf8mb4%',
  CONCAT('ALTER TABLE payment_intents CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @txn_collation),
  'SELECT "payment_intents collation already matches transactions, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Gateway toggles. Only non-secret values live here: api/admin_actions.php
-- returns every settings row to the browser, so the PayU Salt is deliberately
-- absent and stays in config/payu.php on the server.
--   payu_enabled — 0 keeps the existing simulated demo payment flow
--   payu_mode    — 'test' (test.payu.in) or 'live' (secure.payu.in)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('payu_enabled', '0'),
('payu_mode', 'test');

-- 31. Listing-duration limits.
--
-- The client's rule: a venture gets one full run of up to 30 days, and if it
-- isn't funded by then, one shorter second chance of up to 10 days. Step 29
-- seeded venture_max_extension_days at 30, which made the extension as long as
-- the original listing — so this lowers it and adds the matching ceiling for a
-- first listing.
--
-- (The extension ceiling was briefly 15 before the client settled on 10; the
-- UPDATE below is written as a > comparison precisely so an install that
-- already picked up the interim value is corrected on the next run.)
--
-- INSERT IGNORE only creates the row when it's missing, so the UPDATE below is
-- what actually moves an existing install off the old value. Both are safe to
-- re-run. config/venture-lifecycle.php clamps whatever is here to its own hard
-- limits (VH_LISTING_MAX_DAYS / VH_EXTENSION_MAX_DAYS), so a hand-edited row
-- can lower these but never raise them.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('venture_max_listing_days', '30'),
('venture_max_extension_days', '10');

UPDATE settings SET setting_value = '10'
 WHERE setting_key = 'venture_max_extension_days'
   AND CAST(setting_value AS UNSIGNED) > 10;

UPDATE settings SET setting_value = '30'
 WHERE setting_key = 'venture_max_listing_days'
   AND CAST(setting_value AS UNSIGNED) > 30;

-- 32. Founders pay a listing fee.
--
-- Listing used to be free. A founder now pays the same flat 0.5% on their own
-- contribution that every partner pays on theirs, so creating a venture costs
-- something and a listing carries a signal of intent.
--
-- Two consequences for the schema:
--   * payment_intents.purpose — the gateway now collects two different things
--     (a membership and a listing fee) and the callback must know which one it
--     is completing. Existing rows are all memberships, hence the default.
--   * ventures.status gains 'pending_payment' — no column change needed, it is
--     already a varchar. A venture sits there, invisible to Browse, until its
--     fee clears. Nothing else in the app treats that value as live: the
--     lifecycle sweep only looks at 'active'/'expired', and the public listing
--     whitelists the four public statuses.
SET @has_purpose = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_intents' AND COLUMN_NAME = 'purpose'
);
SET @sql = IF(@has_purpose = 0,
  'ALTER TABLE payment_intents ADD COLUMN purpose VARCHAR(20) NOT NULL DEFAULT ''membership'' AFTER amount',
  'SELECT "payment_intents.purpose already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 33. Expected ROI on a listing.
--
-- Sits alongside equity_distribution in the Investment & Exit block: how the
-- ownership is split, and what return a partner should expect from it. Free
-- text rather than a dropdown because real answers are ranges and conditions
-- ("18-22% annually from year 2"), and bucketing them would push founders into
-- picking a number they don't actually mean.
SET @has_roi = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'expected_roi'
);
SET @sql = IF(@has_roi = 0,
  'ALTER TABLE ventures ADD COLUMN expected_roi VARCHAR(120) NULL AFTER equity_distribution',
  'SELECT "ventures.expected_roi already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 34. Sample (showcase) listings.
--
-- A brand-new site has nothing on it, and a founder arriving at an empty Browse
-- page has no idea what a finished listing is supposed to look like. An admin
-- can publish a small number of worked examples — same creation form, same card,
-- same detail page — pinned to the top of every listing so they are the first
-- thing a visitor sees.
--
-- One flag carries the whole behaviour, because a sample differs from a real
-- venture in four ways that all follow from "nobody's money is involved":
--   * it is free to publish (no 0.5% listing fee, no pending_payment hold),
--   * it cannot be joined or applied to, so no fee is ever charged against it,
--   * it never expires — listing_ends_at stays NULL, which the lifecycle sweep
--     already skips, and the card shows the sample chip instead of a countdown,
--   * it sorts first, ahead of the brand/individual tiering.
--
-- TINYINT(1) NOT NULL DEFAULT 0 means every existing venture is a real one, which
-- is correct: this is the flag's first appearance.
SET @has_showcase = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'is_showcase'
);
SET @sql = IF(@has_showcase = 0,
  'ALTER TABLE ventures ADD COLUMN is_showcase TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
  'SELECT "ventures.is_showcase already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Browse sorts on this before anything else and the admin panel counts it on
-- every page load, so it earns an index even though the table is small.
SET @has_showcase_idx = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND INDEX_NAME = 'idx_ventures_showcase'
);
SET @sql = IF(@has_showcase_idx = 0,
  'ALTER TABLE ventures ADD INDEX idx_ventures_showcase (is_showcase, status)',
  'SELECT "idx_ventures_showcase already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 35. Silent-partner capital cap.
--
-- Until now a venture had a single capital pool: silent partners could fill it
-- to 100%, and the silent role closed only when raised_capital reached
-- target_capital. That left a founder unable to reserve any part of their raise
-- for active partners — the people who actually join and run the business. By
-- the time a promising operator applied, the money could already be gone.
--
-- This column is the founder's answer to "how much of the target may silent
-- (capital-only) partners bring?". The remainder is the active reserve:
--
--   target 30L, silent cap 27L  ->  silent partners fill up to 27L, then the
--                                   silent role locks and the last 3L is only
--                                   reachable by joining as an active partner.
--
-- The founder's own contribution sits outside both buckets (it is neither a
-- silent nor an active partner's money), so the cap is measured against
-- target - founder_contribution. See vh_capital_buckets() in config/membership.php,
-- which is the single place that arithmetic lives.
--
-- NULL is deliberately "no cap", not "cap of zero", and is what every existing
-- row gets: those listings were created under the old single-pool rule and must
-- keep behaving exactly as before — silent partners may fill the whole target.
-- Writing target_capital into the column instead would look equivalent today but
-- would freeze the cap at the target as it stood at migration time, so a later
-- edit raising the target would silently leave a cap behind.
SET @has_silent_cap = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'silent_capital_limit'
);
SET @sql = IF(@has_silent_cap = 0,
  'ALTER TABLE ventures ADD COLUMN silent_capital_limit INT NULL DEFAULT NULL AFTER min_investment',
  'SELECT "ventures.silent_capital_limit already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The two buckets are derived from venture_members (role + invested_amount)
-- rather than stored as counters, so they can never drift out of step with the
-- memberships they describe. Every card and every join check reads that split,
-- which makes (venture_id, role) the hot path.
SET @has_member_role_idx = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_members' AND INDEX_NAME = 'idx_members_venture_role'
);
SET @sql = IF(@has_member_role_idx = 0,
  'ALTER TABLE venture_members ADD INDEX idx_members_venture_role (venture_id, role)',
  'SELECT "idx_members_venture_role already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 36. Expected ROI as a filterable number.
--
-- Step 33 stored ROI as free text on purpose: real answers are ranges with
-- conditions ("18-22% annually from year 2"), and a single dropdown would have
-- pushed founders into a number they didn't mean. That reasoning still holds —
-- so rather than replacing the text, this splits the two jobs. The range
-- becomes two numbers (filterable, and short enough for the card), and the
-- original column survives as the conditions note beside them. No existing
-- prose is destroyed.
SET @has_roi_min = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'expected_roi_min'
);
SET @sql = IF(@has_roi_min = 0,
  'ALTER TABLE ventures ADD COLUMN expected_roi_min DECIMAL(5,2) NULL AFTER expected_roi',
  'SELECT "ventures.expected_roi_min already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_roi_max = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'expected_roi_max'
);
SET @sql = IF(@has_roi_max = 0,
  'ALTER TABLE ventures ADD COLUMN expected_roi_max DECIMAL(5,2) NULL AFTER expected_roi_min',
  'SELECT "ventures.expected_roi_max already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Browse filters ventures by ROI band, and a band is an overlap test against
-- both columns at once.
SET @has_roi_idx = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND INDEX_NAME = 'idx_ventures_roi'
);
SET @sql = IF(@has_roi_idx = 0,
  'ALTER TABLE ventures ADD INDEX idx_ventures_roi (expected_roi_min, expected_roi_max)',
  'SELECT "idx_ventures_roi already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 37. Individual vs Company accounts.
--
-- The founder already chose Individual/Company per venture; asking again per
-- listing let one person present the same identity both ways. The choice moves
-- to the account, and ventures.founder_type is derived from it.
--
-- Defaults to 'individual' deliberately. api/ventures.php sorts brand-owned
-- listings above individual ones on Browse, so inferring 'company' from a past
-- venture would silently promote existing users into the better-ranked tier
-- without them ever asking for it. They confirm on their next profile save.
--
-- A company profile reuses the columns it already has — name, avatar_url, bio,
-- website_url, city — so only the genuinely company-only fields are new. The
-- individual-only columns (age, occupation, past_experience) simply go unread
-- for a company, which means switching type later loses no data.
SET @has_account_type = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_type'
);
SET @sql = IF(@has_account_type = 0,
  'ALTER TABLE users ADD COLUMN account_type VARCHAR(20) NOT NULL DEFAULT ''individual'' AFTER role',
  'SELECT "users.account_type already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Set once the user has actively confirmed which kind of account this is, so
-- the default above can be told apart from a real choice. Until it is set, the
-- profile page asks.
SET @has_account_type_set = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_type_confirmed'
);
SET @sql = IF(@has_account_type_set = 0,
  'ALTER TABLE users ADD COLUMN account_type_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER account_type',
  'SELECT "users.account_type_confirmed already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_company_reg = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'company_registration_no'
);
SET @sql = IF(@has_company_reg = 0,
  'ALTER TABLE users ADD COLUMN company_registration_no VARCHAR(60) NULL AFTER past_experience',
  'SELECT "users.company_registration_no already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_company_year = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'company_founded_year'
);
SET @sql = IF(@has_company_year = 0,
  'ALTER TABLE users ADD COLUMN company_founded_year INT NULL AFTER company_registration_no',
  'SELECT "users.company_founded_year already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_company_size = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'company_size'
);
SET @sql = IF(@has_company_size = 0,
  'ALTER TABLE users ADD COLUMN company_size VARCHAR(40) NULL AFTER company_founded_year',
  'SELECT "users.company_size already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 38. Illustrative capital split for sample listings.
--
-- The silent/active bars read raised_silent / raised_active, which are derived
-- from venture_members and never stored — that is what stops them drifting from
-- the memberships they describe. A sample listing has no members and can never
-- gain any (join refuses it), so its bars would sit at 0% forever on the one
-- kind of listing whose whole job is to demonstrate.
--
-- These two columns are the single exception, and they are safe precisely
-- because there are no memberships for them to contradict. Read ONLY when
-- is_showcase = 1; every real venture still derives its split from rows.
SET @has_sc_silent = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'showcase_raised_silent'
);
SET @sql = IF(@has_sc_silent = 0,
  'ALTER TABLE ventures ADD COLUMN showcase_raised_silent INT NULL AFTER is_showcase',
  'SELECT "ventures.showcase_raised_silent already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_sc_active = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'showcase_raised_active'
);
SET @sql = IF(@has_sc_active = 0,
  'ALTER TABLE ventures ADD COLUMN showcase_raised_active INT NULL AFTER showcase_raised_silent',
  'SELECT "ventures.showcase_raised_active already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 39. Daily reminders during the expired-listing decision window.
--
-- A listing that runs out of time without being fully funded goes to 'expired'
-- and waits for the founder to extend it or cancel it. The founder was told
-- once, at the moment it expired, and then heard nothing until the platform
-- cancelled the venture for them — which is exactly the complaint: by the time
-- the deadline passed they had long since forgotten the listing existed.
--
-- So the window now nags. One reminder per day, capped at
-- venture_decision_grace_days, then the automatic cancellation (and its
-- refunds) proceeds as before.
--
-- Two columns rather than counting notification rows: the reminder also goes
-- out by email, the counter has to survive a user clearing their notifications,
-- and "24 hours since the last one" is a cheap indexed comparison instead of a
-- correlated subquery on every sweep.
SET @has_reminders_sent = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'expiry_reminders_sent'
);
SET @sql = IF(@has_reminders_sent = 0,
  'ALTER TABLE ventures ADD COLUMN expiry_reminders_sent TINYINT NOT NULL DEFAULT 0 AFTER expired_at',
  'SELECT "ventures.expiry_reminders_sent already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_last_reminder = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'last_expiry_reminder_at'
);
SET @sql = IF(@has_last_reminder = 0,
  'ALTER TABLE ventures ADD COLUMN last_expiry_reminder_at TIMESTAMP NULL DEFAULT NULL AFTER expiry_reminders_sent',
  'SELECT "ventures.last_expiry_reminder_at already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The decision window drops from 7 days to 2 (client's rule: "maximum 2 days
-- for update details"). Guarded on the old default so an install whose admin
-- deliberately set some other number keeps it, and so re-running is a no-op.
UPDATE settings SET setting_value = '2'
WHERE setting_key = 'venture_decision_grace_days' AND setting_value = '7';


-- 40. What sector a company account operates in.
--
-- A company profile carried registration number, founded year and size, but
-- nothing saying what the business actually does — the one thing a partner
-- weighing it wants first. The individual-only Skills field sat in that slot
-- instead, reading as a personal CV entry on a company page.
--
-- Deliberately its own column rather than reusing ventures.industry: this is
-- the company's own sector, which need not match any single venture it lists.
SET @has_company_industry = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'company_industry'
);
SET @sql = IF(@has_company_industry = 0,
  'ALTER TABLE users ADD COLUMN company_industry VARCHAR(80) NULL AFTER company_size',
  'SELECT "users.company_industry already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 41. Equity and salary per role.
--
-- equity_distribution (step 20) only ever said HOW ownership is split
-- ('capital_based' | 'equal_split' | 'negotiated'), never the actual numbers, so
-- a partner could read the whole listing and still not know what percentage
-- their money buys. The client's table asks for the figures themselves, one row
-- per role.
--
-- The amount each row is measured against already exists and is NOT duplicated
-- here: the founder's row is ventures.founder_contribution, and both partner
-- rows are ventures.min_investment ("Min. Investment per Member" on the form).
-- Storing those a second time would let the table and the capital fields
-- disagree.
--
-- Silent partners get no salary column on purpose — they are capital-only and
-- take no operational role, which is the whole distinction from an active
-- partner. A salary column for them would invite a figure that contradicts it.
--
-- All five stay NULL-able: every existing listing predates them, and a founder
-- who has not agreed equity yet must be able to leave the table blank rather
-- than invent numbers. Salary is a monthly figure in whole rupees.
SET @has_founder_equity = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'founder_equity_percent'
);
SET @sql = IF(@has_founder_equity = 0,
  'ALTER TABLE ventures
     ADD COLUMN founder_equity_percent DECIMAL(5,2) NULL AFTER equity_distribution,
     ADD COLUMN founder_monthly_salary INT NULL AFTER founder_equity_percent,
     ADD COLUMN active_equity_percent DECIMAL(5,2) NULL AFTER founder_monthly_salary,
     ADD COLUMN active_monthly_salary INT NULL AFTER active_equity_percent,
     ADD COLUMN silent_equity_percent DECIMAL(5,2) NULL AFTER active_monthly_salary',
  'SELECT "ventures equity/salary columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 42. Individual and company identity kept apart.
--
-- Step 37 had a company profile reuse the columns it already had — name became
-- the company name, bio the description, website_url the company site. That
-- loses nothing when a user only ever picks one type, but the profile shows
-- ONE input relabelled per type, so a user who filled in a company name, saved,
-- then switched to Individual found their own name overwritten. The client's
-- report: "when I change in one and save changes it changes in both sections."
--
-- These six columns remember a value PER TYPE. users.name / bio / website_url
-- stay the live display values that the rest of the app reads — every card,
-- navbar, member list and venture founder_name keeps working untouched — and
-- saving mirrors the active type's value into them. Switching type swaps the
-- remembered value back in instead of showing the other type's.
SET @has_individual_name = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'individual_name'
);
SET @sql = IF(@has_individual_name = 0,
  'ALTER TABLE users
     ADD COLUMN individual_name VARCHAR(120) NULL AFTER account_type_confirmed,
     ADD COLUMN company_name VARCHAR(120) NULL AFTER individual_name,
     ADD COLUMN individual_bio TEXT NULL AFTER company_name,
     ADD COLUMN company_bio TEXT NULL AFTER individual_bio,
     ADD COLUMN individual_website_url VARCHAR(255) NULL AFTER company_bio,
     ADD COLUMN company_website_url VARCHAR(255) NULL AFTER individual_website_url',
  'SELECT "users per-type identity columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Seed what each user already has into the side matching their current type,
-- so nobody's existing name/bio/website is stranded. Guarded on NULL so a
-- re-run never overwrites a value the user has since edited.
UPDATE users
   SET individual_name = COALESCE(individual_name, name),
       individual_bio = COALESCE(individual_bio, bio),
       individual_website_url = COALESCE(individual_website_url, website_url)
 WHERE COALESCE(account_type, 'individual') <> 'company';

UPDATE users
   SET company_name = COALESCE(company_name, name),
       company_bio = COALESCE(company_bio, bio),
       company_website_url = COALESCE(company_website_url, website_url)
 WHERE account_type = 'company';

-- 43. A monthly report carries its own PDF.
--
-- The report form used to collect expenses as free text ("Rent: 20000", one per
-- line) and DERIVE total_expenses by summing those lines, which is why the Total
-- Expenses box was readonly and looked broken — there was no way to type into it.
-- The client asked for the reverse: type the total directly, drop the line-item
-- box, and attach the real statement as a PDF instead.
--
-- venture_financial_expenses is deliberately left in place. Reports published
-- before this change still have rows there and still render their breakdown;
-- re-saving such a report clears them, so the two shapes never mix on one row.
SET @has_report_pdf = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_financial_reports' AND COLUMN_NAME = 'pdf_path'
);
SET @sql = IF(@has_report_pdf = 0,
  'ALTER TABLE venture_financial_reports
     ADD COLUMN pdf_path VARCHAR(255) NULL AFTER balance_sheet_summary,
     ADD COLUMN pdf_name VARCHAR(255) NULL AFTER pdf_path',
  'SELECT "venture_financial_reports PDF columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 44. Equity split into the money and the work that earned it.
--
-- Step 41 gave each role ONE equity percentage. The client's ask: a founder or
-- active partner earns equity two ways — capital they put in, and operations
-- (sweat) they put in — and the listing should state both plus the total:
--   "50% Equity — 30% Investment + 20% Operations Equity"
--
-- The existing *_equity_percent columns keep their meaning as the INVESTMENT
-- portion, and total = investment + operations. That is why these new columns
-- are nullable with no backfill: a listing saved before this change has NULL
-- operations, so its total is exactly the number it already showed. Nobody's
-- published terms shift under them.
--
-- Silent partners deliberately get no operations column. Capital-only is what
-- defines the role — a silent partner doing operations would be an active one.
SET @has_ops_equity = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'founder_ops_equity_percent'
);
SET @sql = IF(@has_ops_equity = 0,
  'ALTER TABLE ventures
     ADD COLUMN founder_ops_equity_percent DECIMAL(5,2) NULL AFTER founder_equity_percent,
     ADD COLUMN active_ops_equity_percent DECIMAL(5,2) NULL AFTER active_equity_percent',
  'SELECT "ventures operations-equity columns already exist, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 45. Gallery links that cannot be embedded.
--
-- The gallery took YouTube and Vimeo *video* links and framed them in a player.
-- The client pasted a YouTube channel link and an Instagram account link and
-- found them "not supported" — correctly, because neither can be framed: both
-- sites refuse it and the iframe renders an empty box.
--
-- 'link' is a fourth media type for exactly those: stored with its URL in
-- embed_url and opened in a new tab rather than embedded. Instagram *posts* and
-- *reels* still embed properly and stay media_type='embed'.
SET @has_link_type = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_media'
    AND COLUMN_NAME = 'media_type' AND COLUMN_TYPE LIKE '%link%'
);
SET @sql = IF(@has_link_type = 0,
  "ALTER TABLE venture_media MODIFY COLUMN media_type ENUM('image','video','embed','link') NOT NULL DEFAULT 'image'",
  'SELECT "venture_media link type already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 46. Likes and dislikes on Q&A.
--
-- One row per person per target, so a vote can be changed or taken back and can
-- never be double-counted — the PRIMARY KEY is what enforces that, not app code.
-- `target` separates the question from the founder's answer: the client asked
-- for both to be votable, and they are two different things to agree with.
--
-- Note the explicit collation. A new table created on the live server (MariaDB
-- 11.4) would otherwise inherit utf8mb4_uca1400_ai_ci and could not be joined
-- against the long-standing tables, aborting the migration mid-file — see the
-- venture_media and payment_intents steps for the same guard.
CREATE TABLE IF NOT EXISTS venture_question_votes (
  question_id INT NOT NULL,
  user_id     INT NOT NULL,
  target      ENUM('question','answer') NOT NULL,
  vote        TINYINT NOT NULL,            -- 1 = like, -1 = dislike
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id, user_id, target),
  KEY idx_vqv_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @vqv_collation = (
  SELECT TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_questions'
);
SET @sql = CONCAT('ALTER TABLE venture_question_votes CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @vqv_collation);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 47. A deleted account releases its email address.
--
-- Deleting an account is a soft delete (`is_deactivated`, step 15): nothing is
-- erased, because transactions, refund_request rows an admin still has to pay
-- out, and any venture the person founded all reference the user id. The side
-- effect was that the email stayed spent forever — signing up again answered
-- "an account with this email already exists" and logging in answered "this
-- account has been deleted", so there was no way back onto the platform at all.
--
-- The address is now released, but only at the moment somebody actually claims
-- it: registering on a deactivated row's email moves that address here and
-- inserts a brand-new, empty user for it. Archiving lazily rather than at
-- deletion time keeps the real address on the record for as long as possible —
-- an admin paying out that person's pending refund needs somewhere to write to,
-- and the admin panel reads previous_email in preference to the placeholder.
--
-- NULL means "still holds its own address", which is every existing row and
-- every live account.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'previous_email'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN previous_email VARCHAR(150) DEFAULT NULL AFTER email',
  'SELECT "users.previous_email already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 48. Q&A becomes a thread: replies from anyone, one like per person, no dislike.
--
-- The client asked for the Q&A tab to read like an Instagram comment thread:
-- anyone may ask, only the founder may answer, anyone may *reply* under either
-- of those two, and every one of the three can be liked. Disliking is gone —
-- see the note on venture_question_votes below for what happens to the old rows.
--
-- Replies are deliberately FLAT, not a tree. `parent_target` says which of the
-- two anchors a reply hangs under ('question' or 'answer') and that is the only
-- nesting there is; replying to a reply puts an @name in the body, exactly as
-- Instagram does it. A self-referencing parent_id would let a thread nest
-- without limit, which no width of column on this page can render.
CREATE TABLE IF NOT EXISTS venture_question_replies (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  question_id   INT NOT NULL,
  parent_target ENUM('question','answer') NOT NULL,
  user_id       INT DEFAULT NULL,
  body          TEXT NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (question_id) REFERENCES venture_questions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_vqr_question (question_id, parent_target, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One likes table for all three kinds of thing, because the heart behaves
-- identically on each and a second implementation is how two of them end up
-- counting differently. `target_id` is the question id for 'question'/'answer'
-- (both live on that one row) and the reply id for 'reply'.
--
-- No dislike column: the PRIMARY KEY *is* the rule. A row means "this person
-- likes this thing", its absence means they don't, so a like cannot be double
-- counted and un-liking is a DELETE. There is nothing to store a -1 in, which
-- is what makes "no dislikes" true of the data and not just of the UI.
--
-- target_id carries no foreign key — it points at two different tables — so the
-- endpoint deletes the likes alongside whatever they belonged to.
CREATE TABLE IF NOT EXISTS venture_qna_likes (
  target_type ENUM('question','answer','reply') NOT NULL,
  target_id   INT NOT NULL,
  user_id     INT NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (target_type, target_id, user_id),
  KEY idx_vql_target (target_type, target_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Explicit collation on both, matched to the table they are joined against.
-- DEFAULT CHARSET=utf8mb4 alone inherits the *server's* collation, which is
-- utf8mb4_general_ci on local XAMPP (MariaDB 10.4) and utf8mb4_uca1400_ai_ci on
-- the live host (MariaDB 11.4) — a table created there could not be joined
-- against the long-standing ones (#1267), aborting this file mid-run and
-- silently leaving every later step unapplied. Same guard as venture_media,
-- payment_intents and step 46.
SET @qna_collation = (
  SELECT TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_questions'
);
SET @sql = CONCAT('ALTER TABLE venture_question_replies CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @qna_collation);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE venture_qna_likes CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @qna_collation);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Carry the existing likes over. Dislikes are dropped on purpose: the client
-- asked for the option to go, and a dislike has no meaning in the new model.
--
-- venture_question_votes is deliberately left in place rather than dropped. It
-- still holds those dislikes, and this file must never be the reason a site
-- loses data it can no longer reconstruct; nothing reads the table any more.
-- INSERT IGNORE against the PRIMARY KEY is what makes re-running this a no-op.
INSERT IGNORE INTO venture_qna_likes (target_type, target_id, user_id, created_at)
SELECT v.target, v.question_id, v.user_id, v.created_at
FROM venture_question_votes v
JOIN venture_questions q ON q.id = v.question_id
JOIN users u ON u.id = v.user_id
WHERE v.vote = 1;

-- 49. Asset class — the coarse axis the marketplace filters on.
--
-- The client repositioned the platform from "ventures" to a fractional-ownership
-- marketplace for real-world assets, and asked for filter pills reading
-- Real Estate / Businesses / IP & Royalties / Franchise / Infrastructure.
--
-- Those could not be industries. `category` is DERIVED from `industry` by a map
-- in api/ventures.php and only ever holds the handful of slugs that map produces,
-- so a "Real Estate" pill filtering on it would have matched nothing and the two
-- pills the client cares about most would have shown an empty grid. `industry`
-- itself is the wrong home too: it has 24 fine-grained values a founder picks
-- from, and collapsing it would take choices away from every existing listing.
--
-- So asset_class is its own, deliberately coarse dimension sitting alongside
-- both. The sidebar INDUSTRY dropdown keeps its full list and keeps its meaning;
-- the pills get a field that actually answers "what kind of thing is this?".
SET @has_asset_class = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'asset_class'
);
SET @sql = IF(@has_asset_class = 0,
  'ALTER TABLE ventures
     ADD COLUMN asset_class VARCHAR(40) NULL AFTER category,
     ADD INDEX idx_ventures_asset_class (asset_class)',
  'SELECT "ventures.asset_class already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Back-fill from what each listing already says about itself. Guarded on IS NULL
-- rather than rewriting every row, so re-running this never overwrites a class a
-- founder (or an admin) has since chosen by hand — the same reason the ops-equity
-- step above backfills nothing it did not create.
--
-- 'businesses' is the fallback on purpose: every listing that predates this
-- column IS a business, which is the only thing the platform accepted until now.
--
-- Only two classes can be read off existing data. 'franchise' deliberately is
-- NOT back-filled from category='franchise': that slug is the DEFAULT the map
-- falls back to for every unlisted industry, so trusting it would file most of
-- the site under Franchise. There is no Franchise industry to read instead, so
-- pre-existing listings become 'businesses' and a founder re-classes their own.
UPDATE ventures SET asset_class = 'real_estate'
  WHERE asset_class IS NULL AND (industry = 'Real Estate' OR category = 'real_estate');
UPDATE ventures SET asset_class = 'infrastructure'
  WHERE asset_class IS NULL AND (industry = 'Infrastructure' OR category = 'infra');
UPDATE ventures SET asset_class = 'businesses'
  WHERE asset_class IS NULL;

-- 50. A listing can sit in more than one Asset class, and name its own.
--
-- Step 49 gave each listing ONE class. The client's ask: a listing that is
-- genuinely cross-category (land being developed as infrastructure, say) should
-- appear under every pill it belongs to, and a founder should be able to name a
-- class the five canonical ones do not cover.
--
-- The column stays ONE column and simply holds a comma-separated list. A second
-- column (or a join table) would be a second place the truth lives, and the two
-- would drift the first time one write path forgot the other -- the same reason
-- raised_silent/raised_active are derived rather than stored. Every row written
-- by step 49 is already a valid one-item list, so nothing needs back-filling and
-- old code reading a single value still reads a single value.
--
-- Filtering is FIND_IN_SET(?, asset_class), which is why the list must never
-- carry spaces around its commas.
SET @asset_class_len = (
  SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'asset_class'
);
SET @sql = IF(@asset_class_len IS NOT NULL AND @asset_class_len < 255,
  'ALTER TABLE ventures MODIFY COLUMN asset_class VARCHAR(255) NULL',
  'SELECT "ventures.asset_class is already wide enough, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 51. Requirements and skills, stated per partner side.
--
-- The form asked for one set of "Partner Requirements" and one list of required
-- skills, which the client split in two: what he wants from a silent partner and
-- what he wants from an active one are different questions, and answering them in
-- one box made both vague.
--
-- `requirements` and `required_skills` become the ACTIVE side's, which is what
-- they already were in practice -- the form placed them beside the active
-- application deadline and prompted for "2 years business experience, 5hrs/week".
-- The two new columns hold the silent side's.
--
-- The one case where that reading is wrong is a silent-only listing: it has no
-- active role, so whatever its founder typed can only have been meant for silent
-- partners. Those rows are moved across rather than relabelled, which is what
-- makes `requirements` unambiguous afterwards. The WHERE clause is self-limiting
-- (a moved row has requirements NULL and silent_requirements set), so re-running
-- the migration moves nothing a second time.
--
-- Every 'both' listing keeps its text as active requirements and starts with no
-- silent ones -- the honest default, since nobody has been asked the silent
-- question yet, and the founder can answer it by editing.
SET @has_silent_req = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'silent_requirements'
);
SET @sql = IF(@has_silent_req = 0,
  'ALTER TABLE ventures ADD COLUMN silent_requirements TEXT NULL DEFAULT NULL AFTER requirements',
  'SELECT "ventures.silent_requirements already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_silent_skills = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures' AND COLUMN_NAME = 'silent_required_skills'
);
SET @sql = IF(@has_silent_skills = 0,
  'ALTER TABLE ventures ADD COLUMN silent_required_skills TEXT NULL DEFAULT NULL AFTER required_skills',
  'SELECT "ventures.silent_required_skills already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE ventures
   SET silent_requirements = requirements,
       requirements        = NULL
 WHERE partner_types = 'silent'
   AND requirements IS NOT NULL AND requirements <> ''
   AND (silent_requirements IS NULL OR silent_requirements = '');

-- 52. Waitlist and vacated slots — joining a full Asset that still has time left.
--
-- Once an Asset is fully funded but its listing has not expired, the Co-Own button
-- becomes Join Waitlist. When a partner later exits, the seat they vacate becomes a
-- first-class row in `venture_slots` and every waitlisted person is notified at once
-- with the exit reason; the first to CLAIM it gets a short exclusive window to pay.
--
-- Why the slot is its own table rather than a flag on the venture: an Asset can have
-- several partners leave, so several seats can stand open at the same time, each with
-- its own role, amount and claimant. And why "claim" rather than "first to pay wins":
-- PayU is a redirect gateway, so N people could all be mid-payment for one seat and
-- N-1 of them would have been charged for nothing — every refund here is a manual
-- admin payout. The claim is the lock; the payment then happens behind it.
--
-- Note the explicit collation on both tables. A new table created on the live server
-- (MariaDB 11.4) would otherwise inherit utf8mb4_uca1400_ai_ci and could not be joined
-- against the long-standing tables, aborting the migration mid-file — see the
-- venture_media and payment_intents steps for the same guard.
CREATE TABLE IF NOT EXISTS venture_waitlist (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  venture_id INT NOT NULL,
  user_id    INT NOT NULL,
  status     VARCHAR(20) NOT NULL DEFAULT 'waiting',   -- waiting | joined | left
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_waitlist_person (venture_id, user_id),
  KEY idx_waitlist_venture (venture_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS venture_slots (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  venture_id          INT NOT NULL,
  vacated_by_user_id  INT NULL,
  vacated_by_name     VARCHAR(150) NULL,
  role                VARCHAR(20) NOT NULL DEFAULT 'silent',   -- the seat's role, inherited
  invested_amount     INT NOT NULL DEFAULT 0,                  -- the seat's pledge, inherited
  fee_amount          INT NOT NULL DEFAULT 0,                  -- 0.5% of the above, recomputed on claim
  exit_reason         TEXT NULL,
  status              VARCHAR(20) NOT NULL DEFAULT 'open',     -- open | claiming | filled | withdrawn
  claimed_by_user_id  INT NULL,
  claim_expires_at    TIMESTAMP NULL DEFAULT NULL,
  filled_by_user_id   INT NULL,
  filled_at           TIMESTAMP NULL DEFAULT NULL,
  notified_count      INT NOT NULL DEFAULT 0,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_slots_venture (venture_id, status),
  KEY idx_slots_claim (status, claim_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @vw_collation = (
  SELECT TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventures'
);
SET @sql = CONCAT('ALTER TABLE venture_waitlist CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @vw_collation);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE venture_slots CONVERT TO CHARACTER SET utf8mb4 COLLATE ', @vw_collation);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The 24-hour refund window, per member as well as per venture.
--
-- `ventures.quit_window_opened_at` (step 12) opens once, when the founder marks a
-- meetup completed, and covers everyone who was a member at the time. Somebody who
-- takes a vacated seat afterwards arrives at an Asset whose window has already closed
-- — and they were admitted deliberately WITHOUT a meeting, so they can never attend
-- the thing that opens it. They would have no refund route at all but forfeiture.
--
-- So a member may carry their own window. `vh_member_quit_window()` reads the member's
-- value first and falls back to the venture's, which leaves every existing member
-- behaving exactly as before (their column is NULL) and gives a waitlist joiner 24
-- hours from the moment they join instead of from a meeting they were never at.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_members' AND COLUMN_NAME = 'quit_window_opened_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE venture_members
     ADD COLUMN quit_window_opened_at TIMESTAMP NULL DEFAULT NULL,
     ADD COLUMN joined_via_slot_id INT NULL DEFAULT NULL',
  'SELECT "venture_members.quit_window_opened_at already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The gateway route for a claimed seat. payment_intents carries the slot so the
-- callback — which has no session and knows only the txnid — can fill the right
-- seat rather than granting a plain membership. NULL for every other payment.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_intents' AND COLUMN_NAME = 'slot_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE payment_intents ADD COLUMN slot_id INT NULL DEFAULT NULL',
  'SELECT "payment_intents.slot_id already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 53. A partner's equity is frozen at the terms they joined on.
--
-- Nothing about a member's equity was stored anywhere: `venture_members` held only
-- `invested_amount` and `role`, and every percentage lives on the `ventures` row the
-- founder can edit at any time. So a partner's share was not a record of what they
-- agreed to — it was recomputed live from whatever the listing said at that moment.
-- Lowering `min_investment` from 50,000 to 25,000 silently moved somebody who had
-- paid 50,000 from 6% to 12%, because VH.equity divides the pledge by the CURRENT
-- minimum. The founder needs to keep editing those fields to attract new partners
-- (fewer days left, drop the ticket size), so the fix is not to lock the listing —
-- it is to give each member their own copy of the terms they bought on.
--
-- These five columns are exactly the inputs VH.equity.forPledge() reads, so the
-- snapshot is run through the SAME calculator rather than a second implementation.
-- NULL on all of them means "no snapshot" and falls back to the venture's live
-- values, which is the pre-existing behaviour — the same shape as
-- quit_window_opened_at in step 52.
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venture_members' AND COLUMN_NAME = 'equity_min_investment'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE venture_members
     ADD COLUMN equity_min_investment INT NULL DEFAULT NULL,
     ADD COLUMN equity_active_percent DECIMAL(5,2) NULL DEFAULT NULL,
     ADD COLUMN equity_active_ops_percent DECIMAL(5,2) NULL DEFAULT NULL,
     ADD COLUMN equity_silent_percent DECIMAL(5,2) NULL DEFAULT NULL,
     ADD COLUMN equity_partner_types VARCHAR(20) NULL DEFAULT NULL,
     ADD COLUMN equity_agreed_at TIMESTAMP NULL DEFAULT NULL',
  'SELECT "venture_members.equity_min_investment already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Back-fill every existing member from their venture's CURRENT terms.
--
-- Deliberately a back-fill rather than leaving them NULL. NULL would be the more
-- conservative default, but it would also leave every member who joined before today
-- exposed to exactly the drift this step exists to stop. Today's listing values are
-- the only record of their terms that survives — the historical ones were never
-- written down — so freezing them here is the closest honest approximation, and it
-- is strictly better than letting them keep moving. Guarded on NULL so re-running
-- the migration never overwrites a snapshot taken at a real join.
UPDATE venture_members vm
  JOIN ventures v ON v.id = vm.venture_id
   SET vm.equity_min_investment     = v.min_investment,
       vm.equity_active_percent     = v.active_equity_percent,
       vm.equity_active_ops_percent = v.active_ops_equity_percent,
       vm.equity_silent_percent     = v.silent_equity_percent,
       vm.equity_partner_types      = v.partner_types,
       vm.equity_agreed_at          = vm.joined_at
 WHERE vm.equity_min_investment IS NULL;


-- ---------------------------------------------------------------------------
-- 54. An active-partner applicant leaves a WhatsApp number.
--
-- "Here add a section for WhatsApp number ... if founder need more information then
-- contact you through it" — Neelkanth, 5 Sep 2026.
--
-- An application is a one-way document today: the founder reads a resume and a short
-- message and then has to Select or Reject on that alone, with no way to ask a single
-- follow-up question. The platform has no messaging until somebody is a member (the
-- group chat is members-only), so the founder's only options were to decide blind or
-- to select somebody purely to be able to talk to them.
--
-- It lives on venture_applications, NOT on users.phone. Three reasons: users.phone is
-- the account's own contact detail and is not necessarily a WhatsApp number; publishing
-- it to a founder because somebody applied would be a disclosure the applicant never
-- agreed to; and this number is given for ONE application, so it belongs with that row
-- and disappears with it.
--
-- Stored as typed but normalised to digits by the API (see vh_normalize_whatsapp) so a
-- wa.me link can be built from it. VARCHAR(20) holds a country code plus a 10-digit
-- number with room to spare.
-- ---------------------------------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'venture_applications'
     AND COLUMN_NAME = 'whatsapp_number'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE venture_applications
     ADD COLUMN whatsapp_number VARCHAR(20) NULL DEFAULT NULL',
  'SELECT "venture_applications.whatsapp_number already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Deliberately NOT back-filled. Every application filed before today was submitted
-- without one, and inventing a number from users.phone would hand the founder a
-- contact detail the applicant never offered for this purpose. NULL means "this
-- applicant did not give one", which the founder's card states plainly.


-- ---------------------------------------------------------------------------
-- 55. Exit route renamed: "Internal Marketplace Transfer" -> "External Ownership
--     Transfer" (client, 5 Sep 2026).
--
-- `ventures.exit_options` is a comma-separated list of the LABELS themselves, not of
-- ids, so renaming the checkbox alone would strand every listing that had chosen it:
-- the stored string would no longer match any box and the edit form would silently drop
-- the founder's own answer on the next save. The rewrite below is what keeps them.
--
-- REPLACE() is a no-op on a row that does not contain the phrase, so this is safe to
-- re-run, and the WHERE keeps it from touching rows it has nothing to say about.
-- ---------------------------------------------------------------------------
UPDATE ventures
   SET exit_options = REPLACE(exit_options, 'Internal Marketplace Transfer', 'External Ownership Transfer')
 WHERE exit_options LIKE '%Internal Marketplace Transfer%';


-- ---------------------------------------------------------------------------
-- 56. An ACTIVE vacated seat is applied for and chosen, not claimed first-come.
--
-- "If any person exit as active partner, founder select one of person in waitlist
-- and that person pay commitment fees and join seat" — Neelkanth, 6 Sep 2026.
--
-- Until now a seat was taken by whoever clicked Claim first, whatever its role. That
-- is right for a silent seat, which is capital and nothing else, and wrong for an
-- active one: an operator arrived in the business without the founder ever choosing
-- them, bypassing the resume, the skill match and the Select/Reject that every other
-- active partner goes through. This is the sign-off that had been open since the
-- waitlist shipped, and the answer is that the founder chooses.
--
-- ONE NULLABLE COLUMN, and no new table. venture_applications already holds exactly
-- what a seat candidate has to supply — role, message, resume_path, resume_name,
-- whatsapp_number, skill_match_note, status, decided_at — and the founder already has
-- a screen that reads it, a decide_application action that selects or rejects, and a
-- skill-match score computed against the listing. A second table would have meant a
-- second copy of all of that, and the two would drift the first time one was changed.
--
-- slot_id IS NULL  -> an ordinary application to join the listing (everything so far).
-- slot_id IS NOT NULL -> an application for one specific vacated seat.
--
-- Nothing is back-filled: every existing row is an ordinary application and NULL says
-- so. The index is on (slot_id, status) because the only new question asked of this
-- table is "who is still pending on this seat", which the founder's screen, the
-- selection guard and the reopen action all ask.
-- ---------------------------------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'venture_applications'
     AND COLUMN_NAME = 'slot_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE venture_applications
     ADD COLUMN slot_id INT NULL DEFAULT NULL',
  'SELECT "venture_applications.slot_id already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'venture_applications'
     AND INDEX_NAME = 'idx_app_slot'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE venture_applications ADD INDEX idx_app_slot (slot_id, status)',
  'SELECT "idx_app_slot already exists, skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration complete.' AS result;
