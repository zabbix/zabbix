alter table screens_items  add elements		int4		DEFAULT '25' NOT NULL;

alter table hosts_templates drop screens;
alter table hosts_templates drop actions;

--
-- Table structure for table 'conditions'
--

CREATE TABLE conditions (
  conditionid		serial,
  actionid		int4		DEFAULT '0' NOT NULL,
  conditiontype		int4		DEFAULT '0' NOT NULL,
  operator		int1		DEFAULT '0' NOT NULL,
  value			varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (conditionid),
  KEY (actionid)
) ENGINE=InnoDB;

insert into conditions (actionid, conditiontype, operator, value)
select actionid, 2, 0, triggerid from actions where scope=0;

insert into conditions (actionid, conditiontype, operator, value)
select actionid, 1, 0, triggerid from actions where scope=1;

insert into conditions (actionid, conditiontype, operator, value)
select actionid, 5, 0, '0' from actions where good in (0,2);

insert into conditions (actionid, conditiontype, operator, value)
select actionid, 5, 0, '1' from actions where good in (1,2);

insert into conditions (actionid, conditiontype, operator, value)
select actionid, 4, 5, severity from actions where scope in (1,2);

alter table actions drop triggerid;
alter table actions drop scope;
alter table actions drop good;
alter table actions drop severity;

alter table actions add  source			int2		DEFAULT '0' NOT NULL;
alter table actions add  actiontype		int2		DEFAULT '0' NOT NULL;

update actions set message="{TRIGGER.NAME}: {STATUS}" where message="*Automatically generated*";
update actions set subject="{TRIGGER.NAME}: {STATUS}" where subject="*Automatically generated*";
