CREATE DATABASE IF NOT EXISTS tourbook CHARACTER SET utf8mb4;
USE tourbook;
-- 3NF: every non-key column depends only on its table's key.
CREATE TABLE users(user_id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(30) UNIQUE NOT NULL, first_name VARCHAR(60), last_name VARCHAR(60), email VARCHAR(120) UNIQUE NOT NULL, phone VARCHAR(15), password_hash VARCHAR(255) NOT NULL, role ENUM('user','admin') DEFAULT 'user', verified TINYINT DEFAULT 0, verify_code CHAR(6), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE destinations(destination_id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, region VARCHAR(60) NOT NULL, description TEXT, entrance_fee DECIMAL(10,2) DEFAULT 0, cover VARCHAR(30) DEFAULT '#1f6f78,#0b1d2e', photo VARCHAR(150) NULL, photo_credit VARCHAR(255) NULL);
CREATE TABLE packages(package_id INT AUTO_INCREMENT PRIMARY KEY, destination_id INT NOT NULL, title VARCHAR(120) NOT NULL, itinerary TEXT, duration_days INT DEFAULT 1, price DECIMAL(10,2) NOT NULL, inclusions TEXT, exclusions TEXT, max_participants INT DEFAULT 10, FOREIGN KEY(destination_id) REFERENCES destinations(destination_id));
CREATE TABLE guides(guide_id INT AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(100) NOT NULL, specialization VARCHAR(100), languages VARCHAR(100), phone VARCHAR(15));
CREATE TABLE bookings(booking_id INT AUTO_INCREMENT PRIMARY KEY, reference_no VARCHAR(20) UNIQUE NOT NULL, user_id INT NOT NULL, total_amount DECIMAL(12,2) NOT NULL, status ENUM('pending','approved','rejected','cancelled','completed') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(user_id));
-- one transaction (booking) -> many items (one-to-many)
CREATE TABLE booking_items(item_id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, package_id INT NOT NULL, travel_date DATE NOT NULL, participants INT NOT NULL, unit_price DECIMAL(10,2) NOT NULL, want_guide TINYINT DEFAULT 0, guide_id INT NULL, special_requests VARCHAR(255), FOREIGN KEY(booking_id) REFERENCES bookings(booking_id), FOREIGN KEY(package_id) REFERENCES packages(package_id), FOREIGN KEY(guide_id) REFERENCES guides(guide_id));
CREATE TABLE payments(payment_id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT UNIQUE NOT NULL, method ENUM('cash','gcash','bank','card') NOT NULL, reference_no VARCHAR(40), id_file VARCHAR(150), amount DECIMAL(12,2), status ENUM('pending','verified','rejected') DEFAULT 'pending', FOREIGN KEY(booking_id) REFERENCES bookings(booking_id));
CREATE TABLE notifications(notif_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message VARCHAR(255), is_read TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(user_id));
INSERT INTO destinations(name,region,description,entrance_fee,cover) VALUES
('El Nido, Palawan','MIMAROPA','Limestone cliffs, hidden lagoons and white-sand coves in the Bacuit Archipelago.',200,'#1f8a8a,#0b2a3a'),
('Chocolate Hills, Bohol','Central Visayas','More than a thousand grassy domes, plus tarsiers and the Loboc River.',50,'#6b8e4e,#1d3a2a'),
('Banaue Rice Terraces','Cordillera','2,000-year-old terraces carved into the mountains of Ifugao.',100,'#8a9a3b,#2b3a1c'),
('Vigan Heritage Village','Ilocos','A Spanish-colonial town of cobblestone streets and kalesa rides.',0,'#a8743a,#3a2312'),
('Boracay Island','Western Visayas','Four kilometres of powder-white sand at White Beach.',150,'#2fa4c7,#0b2a4a'),
('Siargao Island','Caraga','Surf at Cloud 9, sink holes at Sugba Lagoon and island hopping.',100,'#3fb59a,#0e3b3a');
INSERT INTO packages(destination_id,title,itinerary,duration_days,price,inclusions,exclusions,max_participants) VALUES
(1,'El Nido Island Hopping Tour A','Small Lagoon, Big Lagoon, Secret Lagoon, Shimizu Island lunch.',1,1800,'Boat, lunch, snorkel gear','Environmental fee, tips',12),
(1,'El Nido 3D2N Escape','Tours A and C, Nacpan Beach, sunset at Las Cabanas.',3,9500,'Hotel, tours, transfers','Airfare, dinners',8),
(2,'Bohol Countryside Day Tour','Chocolate Hills, tarsier sanctuary, Loboc River cruise.',1,2200,'Van, buffet lunch, cruise','Entrance fees',10),
(3,'Banaue and Batad Trek','Viewpoints, Batad amphitheatre trek, Tappiya Falls.',2,5400,'Van, homestay, trekking guide','Meals, tips',8),
(4,'Vigan Heritage Walk','Calle Crisologo, pottery, kalesa ride, empanada tasting.',1,1500,'Kalesa, tastings','Lunch',15),
(5,'Boracay Island Hopping','Crystal Cove, Puka Beach, snorkeling, sunset sail.',1,2600,'Boat, lunch, gear','Island fee',14),
(5,'Boracay 4D3N Getaway','Beachfront stay, water sports, sunset paraw sailing.',4,14500,'Hotel, breakfast, transfers','Airfare, activities',6),
(6,'Siargao Island Hopping','Naked, Daku and Guyam islands with a seafood lunch.',1,2300,'Boat, lunch','Island fee',12);
INSERT INTO guides(full_name,specialization,languages,phone) VALUES
('Ramon Dela Cruz','Island and marine tours','Filipino, English','09171234567'),
('Maricel Santos','Heritage and culture','Filipino, English, Spanish','09181234567'),
('Joel Macaraeg','Mountain trekking','Filipino, English, Ilocano','09191234567'),
('Hana Villanueva','Nature and wildlife','Filipino, English, Cebuano','09201234567');
-- The admin account (admin / Admin_123) is created automatically by config.php on first run.

-- Physical data queries (examples)
-- SELECT p.title, d.name, p.price FROM packages p JOIN destinations d ON d.destination_id=p.destination_id;
-- SELECT b.reference_no, u.first_name, b.total_amount, b.status FROM bookings b JOIN users u USING(user_id);
-- SELECT bi.*, p.title FROM booking_items bi JOIN packages p USING(package_id) WHERE bi.booking_id=1;
-- SELECT * FROM guides WHERE guide_id NOT IN (SELECT guide_id FROM booking_items WHERE guide_id IS NOT NULL AND travel_date='2026-12-01');
-- SELECT SUM(amount) FROM payments WHERE status='verified';

-- Guides per destination
-- Each guide can cover many destinations; each destination has many guides (3NF link table)
CREATE TABLE IF NOT EXISTS guide_destinations(guide_id INT NOT NULL, destination_id INT NOT NULL, PRIMARY KEY(guide_id,destination_id), FOREIGN KEY(guide_id) REFERENCES guides(guide_id), FOREIGN KEY(destination_id) REFERENCES destinations(destination_id));
INSERT INTO guides(full_name,specialization,languages,phone) SELECT 'Dennis Reyes','Beach and water sports','Filipino, English, Hiligaynon','09211234567' WHERE NOT EXISTS(SELECT 1 FROM guides WHERE full_name='Dennis Reyes');
INSERT INTO guides(full_name,specialization,languages,phone) SELECT 'Liza Mendoza','Mountain and culture','Filipino, English, Ifugao','09221234567' WHERE NOT EXISTS(SELECT 1 FROM guides WHERE full_name='Liza Mendoza');
INSERT IGNORE INTO guide_destinations(guide_id,destination_id) SELECT g.guide_id,d.destination_id FROM guides g JOIN destinations d WHERE
 (g.full_name='Ramon Dela Cruz' AND d.name IN('El Nido, Palawan','Boracay Island','Siargao Island'))
 OR (g.full_name='Maricel Santos' AND d.name IN('Vigan Heritage Village','Chocolate Hills, Bohol'))
 OR (g.full_name='Joel Macaraeg' AND d.name IN('Banaue Rice Terraces','Vigan Heritage Village'))
 OR (g.full_name='Hana Villanueva' AND d.name IN('Chocolate Hills, Bohol','El Nido, Palawan','Siargao Island'))
 OR (g.full_name='Dennis Reyes' AND d.name IN('Boracay Island','Siargao Island'))
 OR (g.full_name='Liza Mendoza' AND d.name IN('Banaue Rice Terraces','El Nido, Palawan'));
-- SELECT g.full_name, d.name FROM guide_destinations gd JOIN guides g USING(guide_id) JOIN destinations d USING(destination_id);

-- Guide accounts, days off, guide payments
-- Tour guide accounts, days off and guide payments (run once on an existing database)
ALTER TABLE users MODIFY role ENUM('user','admin','guide') NOT NULL DEFAULT 'user';
ALTER TABLE guides ADD COLUMN IF NOT EXISTS user_id INT NULL, ADD COLUMN IF NOT EXISTS day_rate DECIMAL(10,2) NOT NULL DEFAULT 1000;
CREATE TABLE IF NOT EXISTS guide_days_off(guide_id INT NOT NULL, off_date DATE NOT NULL, note VARCHAR(100), PRIMARY KEY(guide_id,off_date), FOREIGN KEY(guide_id) REFERENCES guides(guide_id));
CREATE TABLE IF NOT EXISTS guide_payments(payment_id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL UNIQUE, guide_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, status ENUM('pending','paid') NOT NULL DEFAULT 'pending', paid_at DATETIME NULL, FOREIGN KEY(item_id) REFERENCES booking_items(item_id), FOREIGN KEY(guide_id) REFERENCES guides(guide_id));
-- SELECT g.full_name, u.username FROM guides g LEFT JOIN users u ON u.user_id=g.user_id;

-- Version 2: map, guide fee, ratings, guide accounts
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

-- Version 3: birthdate (18+ check)
-- Version 3 update (run once): birthdate for the 18+ age check. Safe to run more than once.
ALTER TABLE users ADD COLUMN IF NOT EXISTS birthdate DATE NULL;
