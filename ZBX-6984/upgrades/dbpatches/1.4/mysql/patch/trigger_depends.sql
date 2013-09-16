CREATE TABLE trigger_depends_tmp (
	triggerdepid		bigint unsigned		NOT NULL auto_increment,
	triggerid_down		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid_up		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (triggerdepid)
) ENGINE=InnoDB;
CREATE INDEX trigger_depends_1 on trigger_depends_tmp (triggerid_down,triggerid_up);
CREATE INDEX trigger_depends_2 on trigger_depends_tmp (triggerid_up);

insert into trigger_depends_tmp select NULL,triggerid_down,triggerid_up from trigger_depends;
drop table trigger_depends;
alter table trigger_depends_tmp rename trigger_depends;

CREATE TABLE trigger_depends_tmp (
	triggerdepid		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid_down		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid_up		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (triggerdepid)
) ENGINE=InnoDB;
CREATE INDEX trigger_depends_1 on trigger_depends_tmp (triggerid_down,triggerid_up);
CREATE INDEX trigger_depends_2 on trigger_depends_tmp (triggerid_up);

insert into trigger_depends_tmp select * from trigger_depends;
drop table trigger_depends;
alter table trigger_depends_tmp rename trigger_depends;
