CREATE TABLE alerts (
	alertid		bigint unsigned		DEFAULT '0'	NOT NULL,
	actionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	mediatypeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	sendto		varchar(100)		DEFAULT ''	NOT NULL,
	subject		varchar(255)		DEFAULT ''	NOT NULL,
	message		blob		DEFAULT ''	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	retries		integer		DEFAULT '0'	NOT NULL,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	repeats		integer		DEFAULT '0'	NOT NULL,
	maxrepeats		integer		DEFAULT '0'	NOT NULL,
	nextcheck		integer		DEFAULT '0'	NOT NULL,
	delay		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (alertid)
);
CREATE INDEX alerts_1 on alerts (actionid);
CREATE INDEX alerts_2 on alerts (clock);
CREATE INDEX alerts_3 on alerts (triggerid);
CREATE INDEX alerts_4 on alerts (status,retries);
CREATE INDEX alerts_5 on alerts (mediatypeid);
CREATE INDEX alerts_6 on alerts (userid);
