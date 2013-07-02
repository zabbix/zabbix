CREATE TABLE rights_tmp (
	rightid         number(20)              DEFAULT '0'     NOT NULL,
	groupid         number(20)              DEFAULT '0'     NOT NULL,
	type            number(10)              DEFAULT '0'     NOT NULL,
	permission              number(10)              DEFAULT '0'     NOT NULL,
	id              number(20)                      ,
	PRIMARY KEY (rightid)
);
CREATE INDEX rights_1 on rights_tmp (groupid);

-- insert into rights_tmp select * from rights;
drop trigger rights_trigger;
drop sequence rights_rightid;
drop table rights;
alter table rights_tmp rename to rights;
