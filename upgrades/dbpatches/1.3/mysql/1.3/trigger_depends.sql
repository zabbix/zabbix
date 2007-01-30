CREATE TABLE trigger_depends (
	triggerdepid		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid_down		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid_up		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (triggerdepid)
);
CREATE INDEX trigger_depends_1 on trigger_depends (triggerid_down,triggerid_up);
CREATE INDEX trigger_depends_2 on trigger_depends (triggerid_up);
