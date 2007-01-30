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
) type=InnoDB;
