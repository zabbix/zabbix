CREATE TABLE trends_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	num		integer		DEFAULT '0'	NOT NULL,
	value_min		double(16,4)		DEFAULT '0.0000'	NOT NULL,
	value_avg		double(16,4)		DEFAULT '0.0000'	NOT NULL,
	value_max		double(16,4)		DEFAULT '0.0000'	NOT NULL,
	PRIMARY KEY (itemid,clock)
) ENGINE=InnoDB;

insert into trends_tmp select * from trends;
drop table trends;
alter table trends_tmp rename trends;
