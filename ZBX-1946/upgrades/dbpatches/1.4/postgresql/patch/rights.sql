CREATE TABLE rights_tmp (
	rightid		bigint DEFAULT '0'	NOT NULL,
	groupid		bigint DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	permission	integer		DEFAULT '0'	NOT NULL,
	id		bigint,
	PRIMARY KEY (rightid)
) with OIDS;
CREATE INDEX rights_1 on rights_tmp (groupid);

--insert into rights_tmp select rightid,groupid,type::integer,permission,id from rights;
drop table rights;
alter table rights_tmp rename to rights;
