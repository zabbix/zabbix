CREATE TABLE media_tmp (
	mediaid         number(20)              DEFAULT '0'     NOT NULL,
	userid          number(20)              DEFAULT '0'     NOT NULL,
	mediatypeid             number(20)              DEFAULT '0'     NOT NULL,
	sendto          varchar2(100)           DEFAULT ''      ,
	active          number(10)              DEFAULT '0'     NOT NULL,
	severity                number(10)              DEFAULT '63'    NOT NULL,
	period          varchar2(100)           DEFAULT '1-7,00:00-23:59'       ,
	PRIMARY KEY (mediaid)
);
CREATE INDEX media_1 on media_tmp (userid);
CREATE INDEX media_2 on media_tmp (mediatypeid);

insert into media_tmp select mediaid,userid,mediatypeid,sendto,active,severity,period from media;
drop trigger media_trigger;
drop sequence media_mediaid;
drop table media;
alter table media_tmp rename to media;
