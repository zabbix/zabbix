ALTER TABLE sysmaps ALTER COLUMN sysmapid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ALTER COLUMN width SET DEFAULT '600'
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ALTER COLUMN height SET DEFAULT '400'
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ALTER COLUMN backgroundid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ALTER COLUMN backgroundid DROP NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ALTER COLUMN label_type SET DEFAULT '2'
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ALTER COLUMN label_location SET DEFAULT '3'
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD expandproblem INTEGER DEFAULT '1' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD markelements INTEGER DEFAULT '0' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD show_unack INTEGER DEFAULT '0' NOT NULL
/
REORG TABLE sysmaps
/
UPDATE sysmaps SET backgroundid=NULL WHERE backgroundid=0
/
UPDATE sysmaps SET show_unack=1 WHERE highlight>7 AND highlight<16
/
UPDATE sysmaps SET show_unack=2 WHERE highlight>23
/
UPDATE sysmaps SET highlight=(highlight-16) WHERE highlight>15
/
UPDATE sysmaps SET highlight=(highlight-8) WHERE highlight>7
/
UPDATE sysmaps SET markelements=1 WHERE highlight>3  AND highlight<8
/
UPDATE sysmaps SET highlight=(highlight-4) WHERE highlight>3
/
UPDATE sysmaps SET expandproblem=0 WHERE highlight>1 AND highlight<4
/
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1
/
ALTER TABLE sysmaps ADD CONSTRAINT c_sysmaps_1 FOREIGN KEY (backgroundid) REFERENCES images (imageid)
/
REORG TABLE sysmaps
/
