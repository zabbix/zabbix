--
-- Table structure for table 'images'
--

CREATE TABLE images (
  imageid               int4          DEFAULT '0' NOT NULL,
  imagetype             int4          DEFAULT '0' NOT NULL,
  name                  varchar(64)   DEFAULT '0' NOT NULL,
  image                 blob          DEFAULT '' NOT NULL,
  PRIMARY KEY (imageid),
  UNIQUE (imagetype, name)
);

alter table users add  url			varchar(255)	DEFAULT '' NOT NULL;

alter table sysmaps_hosts add  url		varchar(255)	DEFAULT '' NOT NULL;
alter table sysmaps_hosts add  icon_on		varchar(32)	DEFAULT 'Server' NOT NULL;
update sysmaps_hosts set icon_on=icon;

alter table sysmaps add  background		varchar(64)	DEFAULT '' NOT NULL;

alter table items add trends int4 DEFAULT '365' NOT NULL;

alter table graphs add  yaxistype		int2		DEFAULT '0' NOT NULL;
alter table graphs add  yaxismin		float8		DEFAULT '0' NOT NULL;
alter table graphs add  yaxismax		float8		DEFAULT '0' NOT NULL;

alter table items add snmpv3_securityname	varchar(64)	DEFAULT '' NOT NULL;
alter table items add snmpv3_securitylevel	int4		DEFAULT '0' NOT NULL;
alter table items add snmpv3_authpassphrase	varchar(64)	DEFAULT '' NOT NULL;
alter table items add snmpv3_privpassphrase	varchar(64)	DEFAULT '' NOT NULL;

alter table items add formula			varchar(255)	DEFAULT '1' NOT NULL;

update items set formula="1024" where multiplier=1;
update items set formula="1048576" where multiplier=2;
update items set formula="1073741824" where multiplier=3;

update items set multiplier=1 where multiplier!=0;

--
-- Table structure for table 'audit'
--

CREATE TABLE audit (
  auditid               serial,
  userid                int4            DEFAULT '0' NOT NULL,
  clock                 int4            DEFAULT '0' NOT NULL,
  action                int4            DEFAULT '0' NOT NULL,
  resource              int4            DEFAULT '0' NOT NULL,
  details               varchar(128)    DEFAULT '0' NOT NULL,
  PRIMARY KEY (auditid)
);

CREATE UNIQUE INDEX audit_userid_clock on audit (userid,clock);
CREATE INDEX audit_clock on audit (clock);

insert into images values(1,1,"Hub",load_file("../data/images/Hub.png"));
insert into images values(2,1,"Hub (small)",load_file("../data/images/Hub_small.png"));
insert into images values(3,1,"Network",load_file("../data/images/Network.png"));
insert into images values(4,1,"Network (small)",load_file("../data/images/Network_small.png"));
insert into images values(5,1,"Notebook",load_file("../data/images/Notebook.png"));
insert into images values(6,1,"Notebook (small)",load_file("../data/images/Notebook_small.png"));
insert into images values(7,1,"Phone",load_file("../data/images/Phone.png"));
insert into images values(8,1,"Phone (small)",load_file("../data/images/Phone_small.png"));
insert into images values(9,1,"Printer",load_file("../data/images/Printer.png"));
insert into images values(10,1,"Printer (small)",load_file("../data/images/Printer_small.png"));
insert into images values(11,1,"Router",load_file("../data/images/Router.png"));
insert into images values(12,1,"Router (small)",load_file("../data/images/Router_small.png"));
insert into images values(13,1,"Satellite",load_file("../data/images/Satellite.png"));
insert into images values(14,1,"Satellite (small)",load_file("../data/images/Satellite_small.png"));
insert into images values(15,1,"Server",load_file("../data/images/Server.png"));
insert into images values(16,1,"Server (small)",load_file("../data/images/Server_small.png"));
insert into images values(17,1,"UPS",load_file("../data/images/UPS.png"));
insert into images values(18,1,"UPS (small)",load_file("../data/images/UPS_small.png"));
insert into images values(19,1,"Workstation",load_file("../data/images/Workstation.png"));
insert into images values(20,1,"Workstation (small)",load_file("../data/images/Workstation_small.png"));
