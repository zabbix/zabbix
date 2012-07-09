CREATE TABLE acknowledges_tmp (
	acknowledgeid	   number(20)              DEFAULT '0'     NOT NULL,
	userid	  number(20)              DEFAULT '0'     NOT NULL,
	eventid	 number(20)              DEFAULT '0'     NOT NULL,
	clock	   number(10)              DEFAULT '0'     NOT NULL,
	message	 varchar2(255)           DEFAULT ''      ,
	PRIMARY KEY (acknowledgeid)
);
CREATE INDEX acknowledges_1 on acknowledges_tmp (userid);
CREATE INDEX acknowledges_2 on acknowledges_tmp (eventid);
CREATE INDEX acknowledges_3 on acknowledges_tmp (clock);

insert into acknowledges_tmp select * from acknowledges;
drop trigger acknowledges_trigger;
drop sequence acknowledges_acknowledgeid;
drop table acknowledges;
alter table acknowledges_tmp rename to acknowledges;
