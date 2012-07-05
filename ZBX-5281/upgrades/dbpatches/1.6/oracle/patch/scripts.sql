CREATE TABLE scripts (
	scriptid	number(20)	DEFAULT '0'	NOT NULL,
	name		varchar2(255)	DEFAULT '',
	command		varchar2(255)	DEFAULT '',
	host_access	number(10)	DEFAULT '0'	NOT NULL,
	usrgrpid	number(20)	DEFAULT '0'	NOT NULL,
	groupid		number(20)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (scriptid)
);
