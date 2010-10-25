CREATE TABLE users_tmp (
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	alias		varchar(100)		DEFAULT ''	NOT NULL,
	name		varchar(100)		DEFAULT ''	NOT NULL,
	surname		varchar(100)		DEFAULT ''	NOT NULL,
	passwd		char(32)		DEFAULT ''	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	autologout		integer		DEFAULT '900'	NOT NULL,
	lang		varchar(5)		DEFAULT 'en_gb'	NOT NULL,
	refresh		integer		DEFAULT '30'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (userid)
) ENGINE=InnoDB;
CREATE INDEX users_1 on users_tmp (alias);

insert into users_tmp select userid,alias,name,surname,passwd,url,autologout,lang,refresh,1 from users;
update users_tmp set type=3 where alias='Admin';
drop table users;
alter table users_tmp rename users;
