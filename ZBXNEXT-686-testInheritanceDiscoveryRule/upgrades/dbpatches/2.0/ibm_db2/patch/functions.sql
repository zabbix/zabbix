ALTER TABLE functions ALTER COLUMN functionid SET WITH DEFAULT NULL
/
REORG TABLE functions
/
ALTER TABLE functions ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE functions
/
ALTER TABLE functions ALTER COLUMN triggerid SET WITH DEFAULT NULL
/
REORG TABLE functions
/
ALTER TABLE functions DROP COLUMN lastvalue
/
REORG TABLE functions
/
DELETE FROM functions WHERE NOT itemid IN (SELECT itemid FROM items)
/
DELETE FROM functions WHERE NOT triggerid IN (SELECT triggerid FROM triggers)
/
ALTER TABLE functions ADD CONSTRAINT c_functions_1 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE
/
ALTER TABLE functions ADD CONSTRAINT c_functions_2 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE
/
