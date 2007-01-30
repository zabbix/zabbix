CREATE TABLE trigger_depends (
	triggerid_down	int(4) DEFAULT '0' NOT NULL,
	triggerid_up	int(4) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(triggerid_down, triggerid_up),
--	KEY		(triggerid_down),
	KEY		(triggerid_up)
) type=InnoDB;
