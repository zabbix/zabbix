ALTER TABLE httpstepitem ALTER COLUMN httpstepitemid SET WITH DEFAULT NULL
/
REORG TABLE httpstepitem
/
ALTER TABLE httpstepitem ALTER COLUMN httpstepid SET WITH DEFAULT NULL
/
REORG TABLE httpstepitem
/
ALTER TABLE httpstepitem ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE httpstepitem
/
DELETE FROM httpstepitem WHERE NOT httpstepid IN (SELECT httpstepid FROM httpstep)
/
DELETE FROM httpstepitem WHERE NOT itemid IN (SELECT itemid FROM items)
/
ALTER TABLE httpstepitem ADD CONSTRAINT c_httpstepitem_1 FOREIGN KEY (httpstepid) REFERENCES httpstep (httpstepid) ON DELETE CASCADE
/
ALTER TABLE httpstepitem ADD CONSTRAINT c_httpstepitem_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE
/
