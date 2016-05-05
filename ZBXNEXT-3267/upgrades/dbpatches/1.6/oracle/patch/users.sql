CREATE TABLE users_tmp (
	userid		number(20)	DEFAULT '0'	NOT NULL,
	alias		varchar2(100)	DEFAULT '',
	name		varchar2(100)	DEFAULT '',
	surname		varchar2(100)	DEFAULT '',
	passwd		varchar2(32)	DEFAULT '',
	url		varchar2(255)	DEFAULT '',
	autologin	number(10)	DEFAULT '0'	NOT NULL,
	autologout	number(10)	DEFAULT '900'	NOT NULL,
	lang		varchar2(5)	DEFAULT 'en_gb',
	refresh		number(10)	DEFAULT '30'	NOT NULL,
	type		number(10)	DEFAULT '0'	NOT NULL,
	theme		varchar2(128)	DEFAULT 'default.css',
	attempt_failed	number(10)	DEFAULT 0	NOT NULL,
	attempt_ip	varchar2(39)	DEFAULT '',
	attempt_clock	number(10)	DEFAULT 0	NOT NULL,
	PRIMARY KEY (userid)
);
insert into users_tmp select userid,alias,name,surname,passwd,url,0,autologout,lang,refresh,type,'default.css',0,'',0 from users;
drop table users;
alter table users_tmp rename to users;
update users set passwd='5fce1b3e34b520afeffb37ce08c7cd66' where alias<>'guest' and passwd='d41d8cd98f00b204e9800998ecf8427e';
CREATE INDEX users_1 on users (alias);
