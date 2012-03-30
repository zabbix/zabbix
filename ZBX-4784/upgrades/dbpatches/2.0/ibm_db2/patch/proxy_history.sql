ALTER TABLE proxy_history ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE proxy_history
/
ALTER TABLE proxy_history ADD ns integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE proxy_history
/
ALTER TABLE proxy_history ADD status integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE proxy_history
/
