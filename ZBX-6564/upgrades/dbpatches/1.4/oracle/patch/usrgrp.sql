CREATE TABLE usrgrp_tmp (
	usrgrpid                number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(64)            DEFAULT ''      ,
	PRIMARY KEY (usrgrpid)
);
CREATE INDEX usrgrp_1 on usrgrp_tmp (name);

insert into usrgrp_tmp select * from usrgrp;
drop trigger usrgrp_trigger;
drop sequence usrgrp_usrgrpid;
drop table usrgrp;
alter table usrgrp_tmp rename to usrgrp;
