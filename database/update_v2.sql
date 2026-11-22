USE tourbook;
-- Version 2 update (run once): map coordinates, guide fee, guide ratings and tour guide accounts.
-- Safe to run more than once.

-- Map
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS latitude DECIMAL(9,6) NULL, ADD COLUMN IF NOT EXISTS longitude DECIMAL(9,6) NULL;
UPDATE destinations SET latitude=11.178400, longitude=119.393900 WHERE name='El Nido, Palawan' AND latitude IS NULL;
UPDATE destinations SET latitude=9.829700, longitude=124.139700 WHERE name='Chocolate Hills, Bohol' AND latitude IS NULL;
UPDATE destinations SET latitude=16.916700, longitude=121.058300 WHERE name='Banaue Rice Terraces' AND latitude IS NULL;
UPDATE destinations SET latitude=17.574700, longitude=120.386900 WHERE name='Vigan Heritage Village' AND latitude IS NULL;
UPDATE destinations SET latitude=11.967400, longitude=121.924800 WHERE name='Boracay Island' AND latitude IS NULL;
UPDATE destinations SET latitude=9.848200, longitude=126.045800 WHERE name='Siargao Island' AND latitude IS NULL;

-- Extra payment when a guide is requested
ALTER TABLE booking_items ADD COLUMN IF NOT EXISTS guide_fee DECIMAL(10,2) NOT NULL DEFAULT 0;

-- Guide accounts, days off and guide payments
ALTER TABLE users MODIFY role ENUM('user','admin','guide') NOT NULL DEFAULT 'user';
ALTER TABLE guides ADD COLUMN IF NOT EXISTS user_id INT NULL, ADD COLUMN IF NOT EXISTS day_rate DECIMAL(10,2) NOT NULL DEFAULT 1000;
CREATE TABLE IF NOT EXISTS guide_days_off(guide_id INT NOT NULL, off_date DATE NOT NULL, note VARCHAR(100), PRIMARY KEY(guide_id,off_date), FOREIGN KEY(guide_id) REFERENCES guides(guide_id));
CREATE TABLE IF NOT EXISTS guide_payments(payment_id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL UNIQUE, guide_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, status ENUM('pending','paid') NOT NULL DEFAULT 'pending', paid_at DATETIME NULL, FOREIGN KEY(item_id) REFERENCES booking_items(item_id), FOREIGN KEY(guide_id) REFERENCES guides(guide_id));

-- Ratings (one rating per completed tour)
CREATE TABLE IF NOT EXISTS guide_ratings(rating_id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL UNIQUE, guide_id INT NOT NULL, user_id INT NOT NULL, stars TINYINT NOT NULL, comment VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(item_id) REFERENCES booking_items(item_id), FOREIGN KEY(guide_id) REFERENCES guides(guide_id), FOREIGN KEY(user_id) REFERENCES users(user_id));

-- Tour guide logins (temporary password for all: Guide_123)
INSERT INTO users(username,first_name,last_name,email,phone,password_hash,role,verified) SELECT 'ramon.cruz','Ramon','Dela Cruz','ramon.cruz@exploreza.ph','09171234567','$2y$10$b1banGCc47paD2xCVuD/X.T78C998HyiiFTyqK5ILFRPgKt.8Tmze','guide',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM users WHERE username='ramon.cruz') AND EXISTS(SELECT 1 FROM guides WHERE full_name='Ramon Dela Cruz');
UPDATE guides SET user_id=(SELECT user_id FROM users WHERE username='ramon.cruz') WHERE full_name='Ramon Dela Cruz' AND user_id IS NULL;
INSERT INTO users(username,first_name,last_name,email,phone,password_hash,role,verified) SELECT 'maricel.santos','Maricel','Santos','maricel.santos@exploreza.ph','09181234567','$2y$10$/c3capNrzMouApSTZF13B.gog6GpYiSxJaJXl1FGoJn8bWoASGYV.','guide',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM users WHERE username='maricel.santos') AND EXISTS(SELECT 1 FROM guides WHERE full_name='Maricel Santos');
UPDATE guides SET user_id=(SELECT user_id FROM users WHERE username='maricel.santos') WHERE full_name='Maricel Santos' AND user_id IS NULL;
INSERT INTO users(username,first_name,last_name,email,phone,password_hash,role,verified) SELECT 'joel.macaraeg','Joel','Macaraeg','joel.macaraeg@exploreza.ph','09191234567','$2y$10$E.l8A5GBOdk/JQ44ll5EcepvrNJkSBJsXyB3vQfOfyq2WnZv5dZD.','guide',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM users WHERE username='joel.macaraeg') AND EXISTS(SELECT 1 FROM guides WHERE full_name='Joel Macaraeg');
UPDATE guides SET user_id=(SELECT user_id FROM users WHERE username='joel.macaraeg') WHERE full_name='Joel Macaraeg' AND user_id IS NULL;
INSERT INTO users(username,first_name,last_name,email,phone,password_hash,role,verified) SELECT 'hana.villanueva','Hana','Villanueva','hana.villanueva@exploreza.ph','09201234567','$2y$10$vsUuR7HncNovQZqFn6XtFeET6MI5rFlpiuAf5/3KEFE1efO562cEC','guide',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM users WHERE username='hana.villanueva') AND EXISTS(SELECT 1 FROM guides WHERE full_name='Hana Villanueva');
UPDATE guides SET user_id=(SELECT user_id FROM users WHERE username='hana.villanueva') WHERE full_name='Hana Villanueva' AND user_id IS NULL;
INSERT INTO users(username,first_name,last_name,email,phone,password_hash,role,verified) SELECT 'dennis.reyes','Dennis','Reyes','dennis.reyes@exploreza.ph','09211234567','$2y$10$q0WHKRl9Z/0akbv7bmMvv./y2vdbRirPPjb0KGCXqFGACrh/g6.5i','guide',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM users WHERE username='dennis.reyes') AND EXISTS(SELECT 1 FROM guides WHERE full_name='Dennis Reyes');
UPDATE guides SET user_id=(SELECT user_id FROM users WHERE username='dennis.reyes') WHERE full_name='Dennis Reyes' AND user_id IS NULL;
INSERT INTO users(username,first_name,last_name,email,phone,password_hash,role,verified) SELECT 'liza.mendoza','Liza','Mendoza','liza.mendoza@exploreza.ph','09221234567','$2y$10$4XPpnTPXKMRKirjWRIemY.NrWAp6CYZDsBzxMYm.3rvpV/UEBmGHS','guide',1 FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM users WHERE username='liza.mendoza') AND EXISTS(SELECT 1 FROM guides WHERE full_name='Liza Mendoza');
UPDATE guides SET user_id=(SELECT user_id FROM users WHERE username='liza.mendoza') WHERE full_name='Liza Mendoza' AND user_id IS NULL;
