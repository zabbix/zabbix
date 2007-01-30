CREATE TABLE services_links (
	linkid		bigint unsigned		DEFAULT '0'	NOT NULL,
	serviceupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	servicedownid		bigint unsigned		DEFAULT '0'	NOT NULL,
	soft		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (linkid)
);
CREATE INDEX services_links_links_1 on services_links (servicedownid);
CREATE UNIQUE INDEX services_links_links_2 on services_links (serviceupid,servicedownid);
