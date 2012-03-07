CREATE TABLE trends_uint (
	itemid		number(20)	DEFAULT '0'	NOT NULL,
	clock		number(10)	DEFAULT '0'	NOT NULL,
	num		number(10)	DEFAULT '0'	NOT NULL,
	value_min	number(20)	DEFAULT '0'	NOT NULL,
	value_avg	number(20)	DEFAULT '0'	NOT NULL,
	value_max	number(20)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (itemid,clock)
);
