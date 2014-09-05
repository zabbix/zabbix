CREATE TABLE trigger_discovery (
	triggerdiscoveryid       bigint                                    NOT NULL,
	triggerid                bigint                                    NOT NULL,
	parent_triggerid         bigint                                    NOT NULL,
	name                     varchar(255)    DEFAULT ''                NOT NULL,
	PRIMARY KEY (triggerdiscoveryid)
);
CREATE UNIQUE INDEX trigger_discovery_1 on trigger_discovery (triggerid,parent_triggerid);
ALTER TABLE ONLY trigger_discovery ADD CONSTRAINT c_trigger_discovery_1 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
ALTER TABLE ONLY trigger_discovery ADD CONSTRAINT c_trigger_discovery_2 FOREIGN KEY (parent_triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
