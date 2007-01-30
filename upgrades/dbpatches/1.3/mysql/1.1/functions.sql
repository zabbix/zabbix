CREATE TABLE functions (
  functionid		int(4)		NOT NULL auto_increment,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  lastvalue		varchar(255),
  function		varchar(12)	DEFAULT '' NOT NULL,
  parameter		varchar(255)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (functionid),
  KEY triggerid (triggerid),
  KEY itemidfunctionparameter (itemid,function,parameter)
) type=InnoDB;
