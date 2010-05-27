CREATE TABLE proxy_dhistory (
	id		number(20)			NOT NULL,
	clock		number(10)	DEFAULT '0'	NOT NULL,
	druleid		number(20)	DEFAULT '0'	NOT NULL,
	type		number(10)	DEFAULT '0'	NOT NULL,
	ip		varchar2(39)	DEFAULT '',
	port		number(10)	DEFAULT '0'	NOT NULL,
	key_		varchar2(255)	DEFAULT '0',
	value		varchar2(255)	DEFAULT '0',
	status		number(10)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX proxy_dhistory_1 on proxy_dhistory (clock);
