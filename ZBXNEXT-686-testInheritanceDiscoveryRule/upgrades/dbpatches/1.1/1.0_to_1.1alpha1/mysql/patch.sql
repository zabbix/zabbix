CREATE TABLE images (
  imageid               int(4)          DEFAULT '0' NOT NULL,
  imagetype             int(4)          DEFAULT '0' NOT NULL,
  name                  varchar(64)     DEFAULT '0' NOT NULL,
  image                 longblob            DEFAULT '' NOT NULL,
  PRIMARY KEY (imageid),
  UNIQUE (imagetype, name)
) ENGINE=InnoDB;

alter table users add  url			varchar(255)	DEFAULT '' NOT NULL;

alter table sysmaps_hosts add  url		varchar(255)	DEFAULT '' NOT NULL;
alter table sysmaps_hosts add  icon_on		varchar(32)	DEFAULT 'Server' NOT NULL;
update sysmaps_hosts set icon_on=icon;

alter table sysmaps add  background		varchar(64)	DEFAULT '' NOT NULL;

alter table items add trends int(4) DEFAULT '365' NOT NULL;

alter table graphs add  yaxistype		int(1)		DEFAULT '0' NOT NULL;
alter table graphs add  yaxismin		double(16,4)	DEFAULT '0' NOT NULL;
alter table graphs add  yaxismax		double(16,4)	DEFAULT '0' NOT NULL;

alter table items add snmpv3_securityname	varchar(64)	DEFAULT '' NOT NULL;
alter table items add snmpv3_securitylevel	int(1)		DEFAULT '0' NOT NULL;
alter table items add snmpv3_authpassphrase	varchar(64)	DEFAULT '' NOT NULL;
alter table items add snmpv3_privpassphrase	varchar(64)	DEFAULT '' NOT NULL;

alter table items add formula			varchar(255)	DEFAULT '0' NOT NULL;

update items set formula="1024" where multiplier=1;
update items set formula="1048576" where multiplier=2;
update items set formula="1073741824" where multiplier=3;

update items set multiplier=1 where multiplier!=0;

--
-- Table structure for table 'audit'
--

CREATE TABLE audit (
  auditid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  action		int(4)		DEFAULT '0' NOT NULL,
  resource		int(4)		DEFAULT '0' NOT NULL,
  details		varchar(128)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (auditid),
  UNIQUE (userid,clock),
  KEY (clock)
) ENGINE=InnoDB;

insert into images values(1,1,"Hub",load_file("Hub.png"));
insert into images values(2,1,"Hub (small)",load_file("Hub_small.png"));
insert into images values(3,1,"Network",load_file("Network.png"));
insert into images values(4,1,"Network (small)",load_file("Network_small.png"));
insert into images values(5,1,"Notebook",load_file("Notebook.png"));
insert into images values(6,1,"Notebook (small)",load_file("Notebook_small.png"));
insert into images values(7,1,"Phone",load_file("Phone.png"));
insert into images values(8,1,"Phone (small)",load_file("Phone_small.png"));
insert into images values(9,1,"Printer",load_file("Printer.png"));
insert into images values(10,1,"Printer (small)",load_file("Printer_small.png"));
insert into images values(11,1,"Router",load_file("Router.png"));
insert into images values(12,1,"Router (small)",load_file("Router_small.png"));
insert into images values(13,1,"Satellite",load_file("Satellite.png"));
insert into images values(14,1,"Satellite (small)",load_file("Satellite_small.png"));
insert into images values(15,1,"Server",load_file("Server.png"));
insert into images values(16,1,"Server (small)",load_file("Server_small.png"));
insert into images values(17,1,"UPS",load_file("UPS.png"));
insert into images values(18,1,"UPS (small)",load_file("UPS_small.png"));
insert into images values(19,1,"Workstation",load_file("Workstation.png"));
insert into images values(20,1,"Workstation (small)",load_file("Workstation_small.png"));
