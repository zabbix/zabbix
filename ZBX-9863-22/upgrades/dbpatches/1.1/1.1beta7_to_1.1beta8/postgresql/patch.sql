ALTER TABLE sysmaps_hosts RENAME sysmaps_elements;

ALTER TABLE sysmaps_elements RENAME COLUMN	shostid	TO	selementid;
ALTER TABLE sysmaps_elements RENAME COLUMN	hostid	TO	elementid;
ALTER TABLE sysmaps_elements ADD	elementtype	int4 DEFAULT '0' NOT NULL;
ALTER TABLE sysmaps_elements ADD	label_location	int2 DEFAULT NULL;

ALTER TABLE sysmaps_links RENAME COLUMN	shostid1	TO	selementid1;
ALTER TABLE sysmaps_links RENAME COLUMN	shostid2	TO	selementid2;

ALTER TABLE alarms ADD		acknowledged	int2 DEFAULT '0' NOT NULL;

--
-- Table structure for table 'acknowledges'
--

CREATE TABLE acknowledges (
	acknowledgeid		serial,
	userid			int4		DEFAULT '0' NOT NULL,
	alarmid			int4		DEFAULT '0' NOT NULL,
	clock			int4		DEFAULT '0' NOT NULL,
	message			varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY (acknowledgeid),
	FOREIGN KEY (alarmid) REFERENCES alarms,
	FOREIGN KEY (userid) REFERENCES users
);

CREATE INDEX acknowledges_alarmid ON acknowledgeid (alarmid);

ALTER TABLE screens_items ADD		valign	int2	DEFAULT '0' NOT NULL;
ALTER TABLE screens_items ADD		halign	int2	DEFAULT '0' NOT NULL;
ALTER TABLE screens_items ADD		style	int4	DEFAULT '0' NOT NULL;
ALTER TABLE screens_items ADD		url	varchar(255)	DEFAULT '' NOT NULL;

CREATE INDEX rights_userid on rights (userid);

ALTER TABLE actions ADD			scripts	text		DEFAULT '' NOT NULL;
