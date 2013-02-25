ALTER TABLE history_text ALTER COLUMN id SET WITH DEFAULT NULL
/
REORG TABLE history_text
/
ALTER TABLE history_text ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE history_text
/
ALTER TABLE history_text ADD ns integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE history_text
/
