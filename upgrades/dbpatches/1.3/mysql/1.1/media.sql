CREATE TABLE media (
	mediaid		int(4) NOT NULL auto_increment,
	userid		int(4) DEFAULT '0' NOT NULL,
--	type		varchar(10) DEFAULT '' NOT NULL,
	mediatypeid	int(4) DEFAULT '0' NOT NULL,
	sendto		varchar(100) DEFAULT '' NOT NULL,
	active		int(4) DEFAULT '0' NOT NULL,
	severity	int(4) DEFAULT '63' NOT NULL,
	period		varchar(100) DEFAULT '1-7,00:00-23:59' NOT NULL,
	PRIMARY KEY	(mediaid),
	KEY		(userid),
	KEY		(mediatypeid)
) type=InnoDB;
