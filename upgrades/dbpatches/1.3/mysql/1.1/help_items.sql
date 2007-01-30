CREATE TABLE help_items (
	itemtype		int(4)          DEFAULT '0' NOT NULL,
	key_			varchar(64)	DEFAULT '' NOT NULL,
	description		varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY (itemtype, key_)
) type=InnoDB;

