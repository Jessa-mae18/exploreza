USE tourbook;
-- Version 3 update (run once): birthdate for the 18+ age check. Safe to run more than once.
ALTER TABLE users ADD COLUMN IF NOT EXISTS birthdate DATE NULL;
