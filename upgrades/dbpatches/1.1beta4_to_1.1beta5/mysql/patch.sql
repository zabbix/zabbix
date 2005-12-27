alter table screens_items  add elements		int(4)		DEFAULT '25' NOT NULL;

alter table actions add  source			int(1)		DEFAULT '0' NOT NULL;
alter table actions add  actiontype		int(1)		DEFAULT '0' NOT NULL;
alter table actions add  filter_triggerid	int(4)		DEFAULT '0' NOT NULL;
alter table actions add  filter_hostid		int(4)		DEFAULT '0' NOT NULL;
alter table actions add  filter_groupid	int(4)		DEFAULT '0' NOT NULL;
alter table actions add  filter_trigger_name	varchar(255)	DEFAULT '' NOT NULL;
update actions set filter_triggerid=triggerid where scope=0;
update actions set filter_hostid=triggerid where scope=1;
alter table actions drop triggerid;
alter table actions drop scope;
alter table actions drop good;
