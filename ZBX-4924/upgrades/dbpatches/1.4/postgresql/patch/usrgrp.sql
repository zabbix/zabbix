CREATE TABLE usrgrp_tmp (
	usrgrpid	bigint DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (usrgrpid)
) with OIDS;
CREATE INDEX usrgrp_1 on usrgrp_tmp (name);

insert into usrgrp_tmp select * from usrgrp;
drop table usrgrp;
alter table usrgrp_tmp rename to usrgrp;
