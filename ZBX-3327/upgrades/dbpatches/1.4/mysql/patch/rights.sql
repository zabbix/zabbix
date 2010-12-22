CREATE TABLE rights_tmp (
	rightid		bigint unsigned		DEFAULT '0'	NOT NULL,
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	permission		integer		DEFAULT '0'	NOT NULL,
	id		bigint unsigned			,
	PRIMARY KEY (rightid)
) ENGINE=InnoDB;
CREATE INDEX rights_1 on rights_tmp (groupid);

-- insert into rights_tmp select * from rights;
drop table rights;
alter table rights_tmp rename rights;
