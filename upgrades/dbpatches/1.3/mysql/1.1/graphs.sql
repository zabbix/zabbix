CREATE TABLE graphs (
  graphid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int(4)		DEFAULT '0' NOT NULL,
  height		int(4)		DEFAULT '0' NOT NULL,
  yaxistype		int(1)		DEFAULT '0' NOT NULL,
  yaxismin		double(16,4)	DEFAULT '0' NOT NULL,
  yaxismax		double(16,4)	DEFAULT '0' NOT NULL,
  templateid		int(4)		DEFAULT '0' NOT NULL,
  show_work_period	int(1)		DEFAULT '1' NOT NULL,
  show_triggers		int(1)		DEFAULT '1' NOT NULL,
  PRIMARY KEY (graphid),
  KEY (name)
) type=InnoDB;
