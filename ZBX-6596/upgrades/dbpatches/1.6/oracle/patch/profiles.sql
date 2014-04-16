drop table profiles;
CREATE TABLE profiles (
	profileid	number(20)	DEFAULT '0'	NOT NULL,
	userid		number(20)	DEFAULT '0'	NOT NULL,
	idx		varchar2(96)	DEFAULT '',
	idx2		number(20)	DEFAULT '0'	NOT NULL,
	value_id	number(20)	DEFAULT '0'	NOT NULL,
	value_int	number(10)	DEFAULT '0'	NOT NULL,
	value_str	varchar2(255)	DEFAULT '',
	source		varchar2(96)	DEFAULT '',
	type		number(10)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (profileid)
);
CREATE INDEX profiles_1 on profiles (userid,idx,idx2);
