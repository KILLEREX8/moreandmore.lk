USE more_and_more;

ALTER TABLE admin_users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

UPDATE admin_users
SET must_change_password = 1;
