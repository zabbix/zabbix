CREATE TABLE users_tmp (
	userid          number(20)              DEFAULT '0'     NOT NULL,
	alias           varchar2(100)           DEFAULT ''      ,
	name            varchar2(100)           DEFAULT ''      ,
	surname         varchar2(100)           DEFAULT ''      ,
	passwd          varchar2(32)            DEFAULT ''      ,
	url             varchar2(255)           DEFAULT ''      ,
	autologout              number(10)              DEFAULT '900'   NOT NULL,
	lang            varchar2(5)             DEFAULT 'en_gb' ,
	refresh         number(10)              DEFAULT '30'    NOT NULL,
	type            number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (userid)
);
CREATE INDEX users_1 on users_tmp (alias);

insert into users_tmp select userid,alias,name,surname,passwd,url,autologout,lang,refresh,1 from users;
update users_tmp set type=3 where alias='Admin';
drop trigger users_trigger;
drop sequence users_userid;
drop table users;
alter table users_tmp rename to users;
