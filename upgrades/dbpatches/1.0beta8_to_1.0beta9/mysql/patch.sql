alter table services modify goodsla double(3,2) default '99.9' not null;
alter table services add sortorder int(4) default '0' not null;

#
# Table structure for table 'screens'
#

CREATE TABLE screens (
  scid int(10) unsigned NOT NULL auto_increment,
  name varchar(255) NOT NULL default 'screen',
  cols int(10) unsigned NOT NULL default '1',
  rows int(10) unsigned NOT NULL default '1',
  PRIMARY KEY  (scid)
) TYPE=InnoDB;

#
# Table structure for table 'screens_items'
#

CREATE TABLE screens_items (
  scitemid int(4) unsigned NOT NULL auto_increment,
  scid int(4) unsigned NOT NULL default '0',
  graphid int(4) unsigned NOT NULL default '0',
  width int(4) unsigned NOT NULL default '100',
  height int(4) unsigned NOT NULL default '50',
  x int(4) unsigned NOT NULL default '0',
  y int(4) unsigned NOT NULL default '0',
  PRIMARY KEY  (scitemid)
) TYPE=InnoDB;

