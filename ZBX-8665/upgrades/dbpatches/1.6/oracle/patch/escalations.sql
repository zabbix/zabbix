begin execute immediate 'drop table escalations'; exception when others then null; end;
CREATE TABLE escalations (
	escalationid	number(20)	DEFAULT '0'	NOT NULL,
	actionid	number(20)	DEFAULT '0'	NOT NULL,
	triggerid	number(20)	DEFAULT '0'	NOT NULL,
	eventid		number(20)	DEFAULT '0'	NOT NULL,
	r_eventid	number(20)	DEFAULT '0'	NOT NULL,
	nextcheck	number(10)	DEFAULT '0'	NOT NULL,
	esc_step	number(10)	DEFAULT '0'	NOT NULL,
	status		number(10)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (escalationid)
);
CREATE INDEX escalations_1 on escalations (actionid,triggerid);
CREATE INDEX escalations_2 on escalations (status,nextcheck);
