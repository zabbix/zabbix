CREATE TABLE triggers (
	triggerid	int(4) NOT NULL auto_increment,
	expression	varchar(255) DEFAULT '' NOT NULL,
	description	varchar(255) DEFAULT '' NOT NULL,
	url		varchar(255) DEFAULT '' NOT NULL,
	status		int(4) DEFAULT '0' NOT NULL,
	value		int(4) DEFAULT '0' NOT NULL,
	priority	int(2) DEFAULT '0' NOT NULL,
	lastchange	int(4) DEFAULT '0' NOT NULL,
	dep_level	int(2) DEFAULT '0' NOT NULL,
	comments	blob,
	error		varchar(128) DEFAULT '' NOT NULL,
	templateid	int(4) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(triggerid),
	KEY		(status),
	KEY		(value)
) type=InnoDB;
