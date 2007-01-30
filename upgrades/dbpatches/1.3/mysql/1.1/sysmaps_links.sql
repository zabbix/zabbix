CREATE TABLE sysmaps_links (
  linkid		int(4)		NOT NULL auto_increment,
  sysmapid		int(4)		DEFAULT '0' NOT NULL,
  selementid1		int(4)		DEFAULT '0' NOT NULL,
  selementid2		int(4)		DEFAULT '0' NOT NULL,
 -- may be NULL 
  triggerid		int(4),
  drawtype_off		int(4)		DEFAULT '0' NOT NULL,
  color_off		varchar(32)	DEFAULT 'Black' NOT NULL,
  drawtype_on		int(4)		DEFAULT '0' NOT NULL,
  color_on		varchar(32)	DEFAULT 'Red' NOT NULL,
  PRIMARY KEY (linkid)
) type=InnoDB;
