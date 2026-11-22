USE tourbook;
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
