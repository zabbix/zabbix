CREATE TABLE usrgrp (
	usrgrpid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (usrgrpid)
);
CREATE INDEX usrgrp_1 on usrgrp (name);
