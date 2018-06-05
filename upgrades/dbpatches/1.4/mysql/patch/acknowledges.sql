CREATE TABLE acknowledges_tmp (
	acknowledgeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	eventid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	message		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (acknowledgeid)
) ENGINE=InnoDB;
CREATE INDEX acknowledges_1 on acknowledges_tmp (userid);
CREATE INDEX acknowledges_2 on acknowledges_tmp (eventid);
CREATE INDEX acknowledges_3 on acknowledges_tmp (clock);

insert into acknowledges_tmp select * from acknowledges;
drop table acknowledges;
alter table acknowledges_tmp rename acknowledges;
