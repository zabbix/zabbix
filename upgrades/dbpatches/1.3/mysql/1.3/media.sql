CREATE TABLE media (
	mediaid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	mediatypeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	sendto		varchar(100)		DEFAULT ''	NOT NULL,
	active		integer		DEFAULT '0'	NOT NULL,
	severity		integer		DEFAULT '63'	NOT NULL,
	period		varchar(100)		DEFAULT '1-7,00:00-23:59'	NOT NULL,
	PRIMARY KEY (mediaid)
);
CREATE INDEX media_1 on media (userid);
CREATE INDEX media_2 on media (mediatypeid);
