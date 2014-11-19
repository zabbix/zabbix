CREATE TABLE services_links_tmp (
	linkid		number(20)	DEFAULT '0'	NOT NULL,
	serviceupid	number(20)	DEFAULT '0'	NOT NULL,
	servicedownid	number(20)	DEFAULT '0'	NOT NULL,
	soft		number(10)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (linkid)
);
CREATE INDEX services_links_links_1 on services_links_tmp (servicedownid);
CREATE UNIQUE INDEX services_links_links_2 on services_links_tmp (serviceupid,servicedownid);

insert into services_links_tmp select * from services_links;
drop trigger services_links_trigger;
drop sequence services_links_linkid;
drop table services_links;
alter table services_links_tmp rename to services_links;
