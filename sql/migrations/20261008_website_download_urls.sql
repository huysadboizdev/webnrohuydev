-- Existing download columns were VARCHAR(50), which truncates file-host URLs.
-- Preserve every saved link while allowing long download URLs.
ALTER TABLE settings
    MODIFY COLUMN Android TEXT,
    MODIFY COLUMN Windows TEXT,
    MODIFY COLUMN IPhone TEXT;
