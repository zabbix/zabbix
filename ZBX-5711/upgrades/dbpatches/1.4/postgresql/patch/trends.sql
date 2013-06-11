CREATE TABLE trends_tmp (
	itemid		bigint DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	num		integer		DEFAULT '0'	NOT NULL,
	value_min	numeric(16,4)		DEFAULT '0.0000'	NOT NULL,
	value_avg	numeric(16,4)		DEFAULT '0.0000'	NOT NULL,
	value_max	numeric(16,4)		DEFAULT '0.0000'	NOT NULL,
	PRIMARY KEY (itemid,clock)
) with OIDS;

insert into trends_tmp select * from trends;
drop table trends;
alter table trends_tmp rename to trends;
