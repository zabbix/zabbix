ALTER TABLE history_uint ALTER COLUMN value TYPE numeric(20);
ALTER TABLE history_uint ALTER COLUMN value SET DEFAULT '0';
ALTER TABLE history_uint ALTER COLUMN value SET NOT NULL;
