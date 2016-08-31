alter table sysmaps_hosts rename sysmaps_elements;

alter table sysmaps_elements change	shostid	selementid	int(4) DEFAULT NULL auto_increment;
alter table sysmaps_elements change	hostid	elementid	int(4) DEFAULT '0' NOT NULL;
alter table sysmaps_elements add	elementtype	int(4) DEFAULT '0' NOT NULL;
alter table sysmaps_elements add	label_location	int(1) DEFAULT NULL;

alter table sysmaps_links change	shostid1	selementid1	int(4) DEFAULT '0' NOT NULL;
alter table sysmaps_links change	shostid2	selementid2	int(4) DEFAULT '0' NOT NULL;

alter table alarms add		acknowledged	int(1) DEFAULT '0' NOT NULL;

--
-- Table structure for table 'acknowledges'
--

CREATE TABLE acknowledges (
	acknowledgeid		int(4)		NOT NULL auto_increment,
	userid			int(4)		DEFAULT '0' NOT NULL,
	alarmid			int(4)		DEFAULT '0' NOT NULL,
	clock			int(4)		DEFAULT '0' NOT NULL,
	message			varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY (acknowledgeid),
	KEY userid (userid),
	KEY alarmid (alarmid),
	KEY clock (clock)
) ENGINE=InnoDB;

alter table screens_items add		valign	int(2)	DEFAULT '0' NOT NULL;
alter table screens_items add		halign	int(2)	DEFAULT '0' NOT NULL;
alter table screens_items add		style	int(4)	DEFAULT '0' NOT NULL;

alter table rights add		key	(userid);
alter table screens_items add		url	varchar(255)	DEFAULT '' NOT NULL;

alter table actions add			scripts	blob		DEFAULT '' NOT NULL;
