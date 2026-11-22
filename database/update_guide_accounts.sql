USE tourbook;
-- Tour guide accounts, days off and guide payments (run once on an existing database)
ALTER TABLE users MODIFY role ENUM('user','admin','guide') NOT NULL DEFAULT 'user';
ALTER TABLE guides ADD COLUMN IF NOT EXISTS user_id INT NULL, ADD COLUMN IF NOT EXISTS day_rate DECIMAL(10,2) NOT NULL DEFAULT 1000;
CREATE TABLE IF NOT EXISTS guide_days_off(guide_id INT NOT NULL, off_date DATE NOT NULL, note VARCHAR(100), PRIMARY KEY(guide_id,off_date), FOREIGN KEY(guide_id) REFERENCES guides(guide_id));
CREATE TABLE IF NOT EXISTS guide_payments(payment_id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL UNIQUE, guide_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, status ENUM('pending','paid') NOT NULL DEFAULT 'pending', paid_at DATETIME NULL, FOREIGN KEY(item_id) REFERENCES booking_items(item_id), FOREIGN KEY(guide_id) REFERENCES guides(guide_id));
-- SELECT g.full_name, u.username FROM guides g LEFT JOIN users u ON u.user_id=g.user_id;
