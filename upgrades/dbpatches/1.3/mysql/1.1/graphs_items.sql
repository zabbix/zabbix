CREATE TABLE graphs_items (
  gitemid		int(4)		NOT NULL auto_increment,
  graphid		int(4)		DEFAULT '0' NOT NULL,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  drawtype		int(4)		DEFAULT '0' NOT NULL,
  sortorder		int(4)		DEFAULT '0' NOT NULL,
  color			varchar(32)	DEFAULT 'Dark Green' NOT NULL,
  yaxisside		int(1)		DEFAULT '1' NOT NULL,
  calc_fnc		int(1)		DEFAULT '2' NOT NULL,
  type			int(1)		DEFAULT '0' NOT NULL,
  periods_cnt		int(4)		DEFAULT '5' NOT NULL,
  PRIMARY KEY (gitemid)
) type=InnoDB;
