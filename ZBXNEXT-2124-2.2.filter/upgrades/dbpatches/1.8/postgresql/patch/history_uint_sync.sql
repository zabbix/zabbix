ALTER TABLE history_uint_sync ALTER COLUMN value TYPE numeric(20);
ALTER TABLE history_uint_sync ALTER COLUMN value SET DEFAULT '0';
ALTER TABLE history_uint_sync ALTER COLUMN value SET NOT NULL;
