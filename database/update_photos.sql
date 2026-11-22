USE tourbook;
-- Adds photo support to destinations (run once on an existing database)
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS photo VARCHAR(150) NULL, ADD COLUMN IF NOT EXISTS photo_credit VARCHAR(255) NULL;
