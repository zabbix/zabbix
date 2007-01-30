CREATE TABLE trends (
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  num			int(2)		DEFAULT '0' NOT NULL,
  value_min		double(16,4)	DEFAULT '0.0000' NOT NULL,
  value_avg		double(16,4)	DEFAULT '0.0000' NOT NULL,
  value_max		double(16,4)	DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,clock)
) type=InnoDB;
