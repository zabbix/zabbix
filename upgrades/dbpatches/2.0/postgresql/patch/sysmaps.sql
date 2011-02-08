ALTER TABLE ONLY sysmaps ALTER sysmapid DROP DEFAULT,
			 ALTER width SET DEFAULT '600',
			 ALTER height SET DEFAULT '400',
			 ALTER backgroundid DROP DEFAULT,
			 ALTER backgroundid DROP NOT NULL,
			 ALTER label_type SET DEFAULT '2',
			 ALTER label_location SET DEFAULT '3',
			 ADD expandproblem INTEGER DEFAULT '1' NOT NULL,
			 ADD markelements INTEGER DEFAULT '0' NOT NULL,
			 ADD show_unack INTEGER DEFAULT '0' NOT NULL,
			ADD grid_size integer DEFAULT '50' NOT NULL,
			ADD grid_show integer DEFAULT '1' NOT NULL,
			ADD grid_align integer DEFAULT '1' NOT NULL;
UPDATE sysmaps SET backgroundid=NULL WHERE backgroundid=0;
UPDATE sysmaps SET show_unack=1 WHERE highlight>7 AND highlight<16;
UPDATE sysmaps SET show_unack=2 WHERE highlight>23;
UPDATE sysmaps SET highlight=(highlight-16) WHERE highlight>15;
UPDATE sysmaps SET highlight=(highlight-8) WHERE highlight>7;
UPDATE sysmaps SET markelements=1 WHERE highlight>3  AND highlight<8;
UPDATE sysmaps SET highlight=(highlight-4) WHERE highlight>3;
UPDATE sysmaps SET expandproblem=0 WHERE highlight>1 AND highlight<4;
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1;
ALTER TABLE ONLY sysmaps ADD CONSTRAINT c_sysmaps_1 FOREIGN KEY (backgroundid) REFERENCES images (imageid);
