ALTER TABLE sysmaps MODIFY sysmapid bigint unsigned NOT NULL,
			MODIFY width INTEGER DEFAULT '600' NOT NULL,
			MODIFY height INTEGER DEFAULT '400' NOT NULL,
			MODIFY backgroundid BIGINT unsigned NULL,
			MODIFY label_type INTEGER DEFAULT '2' NOT NULL,
			MODIFY label_location INTEGER DEFAULT '3' NOT NULL,
			ADD expandproblem INTEGER DEFAULT '1' NOT NULL,
			ADD markelements INTEGER DEFAULT '0' NOT NULL,
			ADD show_unack INTEGER DEFAULT '0' NOT NULL,
			ADD label_format INTEGER DEFAULT '0' NOT NULL,
			ADD label_type_host INTEGER DEFAULT '2' NOT NULL,
			ADD label_type_hostgroup INTEGER DEFAULT '2' NOT NULL,
			ADD label_type_trigger INTEGER DEFAULT '2' NOT NULL,
			ADD label_type_map INTEGER DEFAULT '2' NOT NULL,
			ADD label_type_image INTEGER DEFAULT '2' NOT NULL,
			ADD label_string_host varchar(255) DEFAULT '' NOT NULL,
			ADD label_string_hostgroup varchar(255) DEFAULT '' NOT NULL,
			ADD label_string_trigger varchar(255) DEFAULT '' NOT NULL,
			ADD label_string_map varchar(255) DEFAULT '' NOT NULL,
			ADD label_string_image varchar(255) DEFAULT '' NOT NULL;
UPDATE sysmaps SET backgroundid=NULL WHERE backgroundid=0;
UPDATE sysmaps SET show_unack=1 WHERE highlight>7 AND highlight<16;
UPDATE sysmaps SET show_unack=2 WHERE highlight>23;
UPDATE sysmaps SET highlight=(highlight-16) WHERE highlight>15;
UPDATE sysmaps SET highlight=(highlight-8) WHERE highlight>7;
UPDATE sysmaps SET markelements=1 WHERE highlight>3  AND highlight<8;
UPDATE sysmaps SET highlight=(highlight-4) WHERE highlight>3;
UPDATE sysmaps SET expandproblem=0 WHERE highlight>1 AND highlight<4;
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1;
ALTER TABLE sysmaps ADD CONSTRAINT c_sysmaps_1 FOREIGN KEY (backgroundid) REFERENCES images (imageid);
