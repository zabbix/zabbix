CREATE TABLE proxy_history (
	id		number(20)			NOT NULL,
	itemid		number(20)	DEFAULT '0'	NOT NULL,
	clock		number(10)	DEFAULT '0'	NOT NULL,
	timestamp	number(10)	DEFAULT '0'	NOT NULL,
	source		varchar2(64)	DEFAULT '',
	severity	number(10)	DEFAULT '0'	NOT NULL,
	value		varchar2(2048)	DEFAULT '',
	PRIMARY KEY (id)
);
CREATE INDEX proxy_history_1 on proxy_history (clock);
