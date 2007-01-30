CREATE TABLE acknowledges (
	acknowledgeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	eventid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	message		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (acknowledgeid)
);
CREATE INDEX acknowledges_1 on acknowledges (userid);
CREATE INDEX acknowledges_2 on acknowledges (eventid);
CREATE INDEX acknowledges_3 on acknowledges (clock);
