CREATE TABLE applications (
	applicationid           int(4)          NOT NULL auto_increment,
	hostid                  int(4)          DEFAULT '0' NOT NULL,
	name                    varchar(255)    DEFAULT '' NOT NULL,
	templateid		int(4)		DEFAULT '0' NOT NULL,
	PRIMARY KEY 	(applicationid),
	KEY 		templateid (templateid),
	UNIQUE          appname (hostid,name)
) type=InnoDB;
