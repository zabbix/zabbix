alter table users add  url			varchar(255)	DEFAULT '' NOT NULL;
alter table sysmaps add  use_background		int(4)	DEFAULT 0 NOT NULL;
alter table sysmaps add  background		longblob	DEFAULT '' NOT NULL;

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
