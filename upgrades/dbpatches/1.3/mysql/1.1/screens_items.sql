CREATE TABLE screens_items (
	screenitemid	int(4)		NOT NULL auto_increment,
	screenid	int(4)		DEFAULT '0' NOT NULL,
	resourcetype	int(4)		DEFAULT '0' NOT NULL,
	resourceid	int(4)		DEFAULT '0' NOT NULL,
	width		int(4)		DEFAULT '320' NOT NULL,
	height		int(4)		DEFAULT '200' NOT NULL,
	x		int(4)		DEFAULT '0' NOT NULL,
	y		int(4)		DEFAULT '0' NOT NULL,
	colspan		int(4)		DEFAULT '0' NOT NULL,
	rowspan		int(4)		DEFAULT '0' NOT NULL,
	elements	int(4)		DEFAULT '25' NOT NULL,
	valign		int(2)		DEFAULT '0' NOT NULL,
	halign		int(2)		DEFAULT '0' NOT NULL,
	style		int(4)		DEFAULT '0' NOT NULL,
	url		varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY  (screenitemid)
) TYPE=InnoDB;
