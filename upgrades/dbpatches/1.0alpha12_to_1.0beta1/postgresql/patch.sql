alter table hosts add disable_until int4 default '0' not null;

alter table triggers add url varchar(255) default '' not null;

--
-- Table structure for table 'services'
--

CREATE TABLE services (
  serviceid		serial,
  name			varchar(128)	DEFAULT '' NOT NULL,
  status		int2		DEFAULT '0' NOT NULL,
  triggerid		int4,
  PRIMARY KEY (serviceid)
);

--
-- Table structure for table 'services_links'
--

CREATE TABLE services_links (
  serviceupid		int4		DEFAULT '0' NOT NULL,
  servicedownid		int4		DEFAULT '0' NOT NULL,
  soft			int2		DEFAULT '0' NOT NULL
);

CREATE INDEX services_links_serviceupid on services_links (serviceupid);
CREATE INDEX services_links_servicedownid on services_links (servicedownid);
CREATE UNIQUE INDEX services_links_upidownid on services_links (serviceupid, servicedownid);
