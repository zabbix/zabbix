CREATE TABLE items_applications (
	applicationid           int(4)          DEFAULT '0' NOT NULL,
	itemid                  int(4)          DEFAULT '0' NOT NULL,
	PRIMARY KEY (applicationid,itemid)
) type=InnoDB;
