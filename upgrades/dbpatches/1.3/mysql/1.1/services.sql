CREATE TABLE services (
  serviceid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  status		int(1)		DEFAULT '0' NOT NULL,
  algorithm		int(1)		DEFAULT '0' NOT NULL,
  triggerid		int(4),
  showsla		int(1)		DEFAULT '0' NOT NULL,
  goodsla		double(5,2)	DEFAULT '99.9' NOT NULL,
  sortorder		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (serviceid)
) type=InnoDB;
