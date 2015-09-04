CREATE TABLE alerts_tmp (
	alertid         number(20)              DEFAULT '0'     NOT NULL,
	actionid                number(20)              DEFAULT '0'     NOT NULL,
	triggerid               number(20)              DEFAULT '0'     NOT NULL,
	userid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	mediatypeid             number(20)              DEFAULT '0'     NOT NULL,
	sendto          varchar2(100)           DEFAULT ''      ,
	subject         varchar2(255)           DEFAULT ''      ,
	message         varchar2(2048)          DEFAULT ''      ,
	status          number(10)              DEFAULT '0'     NOT NULL,
	retries         number(10)              DEFAULT '0'     NOT NULL,
	error           varchar2(128)           DEFAULT ''      ,
	nextcheck               number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (alertid)
);
CREATE INDEX alerts_1 on alerts_tmp (actionid);
CREATE INDEX alerts_2 on alerts_tmp (clock);
CREATE INDEX alerts_3 on alerts_tmp (triggerid);
CREATE INDEX alerts_4 on alerts_tmp (status,retries);
CREATE INDEX alerts_5 on alerts_tmp (mediatypeid);
CREATE INDEX alerts_6 on alerts_tmp (userid);

insert into alerts_tmp select alertid,actionid,triggerid,userid,clock,mediatypeid,sendto,subject,message,status,retries,error,nextcheck from alerts;
drop trigger alerts_trigger;
drop sequence alerts_alertid;
drop table alerts;
alter table alerts_tmp rename to alerts;
