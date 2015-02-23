CREATE TABLE services_links_tmp (
	linkid		bigint DEFAULT '0'	NOT NULL,
	serviceupid	bigint DEFAULT '0'	NOT NULL,
	servicedownid	bigint DEFAULT '0'	NOT NULL,
	soft		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (linkid)
) with OIDS;
CREATE INDEX services_links_links_1 on services_links_tmp (servicedownid);
CREATE UNIQUE INDEX services_links_links_2 on services_links_tmp (serviceupid,servicedownid);

insert into services_links_tmp select * from services_links;
drop table services_links;
alter table services_links_tmp rename to services_links;
