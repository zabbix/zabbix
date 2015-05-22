CREATE TABLE trends_tmp (
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	num             number(10)              DEFAULT '0'     NOT NULL,
	value_min               number(20,4)            DEFAULT '0.0000'        NOT NULL,
	value_avg               number(20,4)            DEFAULT '0.0000'        NOT NULL,
	value_max               number(20,4)            DEFAULT '0.0000'        NOT NULL,
	PRIMARY KEY (itemid,clock)
);

insert into trends_tmp select * from trends;
drop table trends;
alter table trends_tmp rename to trends;
