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
ALTER TABLE sysmaps ADD expandproblem integer WITH DEFAULT '1' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD markelements integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD show_unack integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD grid_size integer DEFAULT '50' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD grid_show integer DEFAULT '1' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD grid_align integer DEFAULT '1' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_format integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_type_host integer WITH DEFAULT '2' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_type_hostgroup integer WITH DEFAULT '2' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_type_trigger integer WITH DEFAULT '2' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_type_map integer WITH DEFAULT '2' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_type_image integer WITH DEFAULT '2' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_string_host varchar(255) WITH DEFAULT '' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_string_hostgroup varchar(255) WITH DEFAULT '' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_string_trigger varchar(255) WITH DEFAULT '' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_string_map varchar(255) WITH DEFAULT '' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD label_string_image varchar(255) WITH DEFAULT '' NOT NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD iconmapid bigint NULL
/
REORG TABLE sysmaps
/
ALTER TABLE sysmaps ADD expand_macros integer WITH DEFAULT '0' NOT NULL
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
ALTER TABLE sysmaps ADD CONSTRAINT c_sysmaps_2 FOREIGN KEY (iconmapid) REFERENCES icon_map (iconmapid)
/
