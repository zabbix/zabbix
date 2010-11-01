CREATE TABLE trigger_discovery (
	triggerdiscoveryid       bigint unsigned                           NOT NULL,
	triggerid                bigint unsigned                           NOT NULL,
	parent_triggerid         bigint unsigned                           NOT NULL,
	name                     varchar(255)    DEFAULT ''                NOT NULL,
	PRIMARY KEY (triggerdiscoveryid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX trigger_discovery_1 on trigger_discovery (triggerid,parent_triggerid);
ALTER TABLE trigger_discovery ADD CONSTRAINT c_trigger_discovery_1 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
ALTER TABLE trigger_discovery ADD CONSTRAINT c_trigger_discovery_2 FOREIGN KEY (parent_triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
