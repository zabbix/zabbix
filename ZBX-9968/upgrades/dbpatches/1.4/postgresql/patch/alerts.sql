CREATE TABLE alerts_tmp (
	alertid		bigint	DEFAULT '0'	NOT NULL,
	actionid	bigint	DEFAULT '0'	NOT NULL,
	triggerid	bigint	DEFAULT '0'	NOT NULL,
	userid		bigint	DEFAULT '0'	NOT NULL,
	clock		integer	DEFAULT '0'	NOT NULL,
	mediatypeid	bigint	DEFAULT '0'	NOT NULL,
	sendto		varchar(100)		DEFAULT ''	NOT NULL,
	subject		varchar(255)		DEFAULT ''	NOT NULL,
	message		text	DEFAULT ''	NOT NULL,
	status		integer	DEFAULT '0'	NOT NULL,
	retries		integer	DEFAULT '0'	NOT NULL,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	nextcheck	integer	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (alertid)
) with OIDS;
CREATE INDEX alerts_1 on alerts_tmp (actionid);
CREATE INDEX alerts_2 on alerts_tmp (clock);
CREATE INDEX alerts_3 on alerts_tmp (triggerid);
CREATE INDEX alerts_4 on alerts_tmp (status,retries);
CREATE INDEX alerts_5 on alerts_tmp (mediatypeid);
CREATE INDEX alerts_6 on alerts_tmp (userid);

insert into alerts_tmp select alertid,actionid,triggerid,userid,clock,mediatypeid,sendto,subject,message,status,retries,error,nextcheck from alerts;
drop table alerts;
alter table alerts_tmp rename to alerts;
