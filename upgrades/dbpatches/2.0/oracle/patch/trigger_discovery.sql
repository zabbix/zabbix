CREATE TABLE trigger_discovery (
	triggerdiscoveryid       number(20)                                NOT NULL,
	triggerid                number(20)                                NOT NULL,
	parent_triggerid         number(20)                                NOT NULL,
	name                     nvarchar2(255)  DEFAULT ''                ,
	PRIMARY KEY (triggerdiscoveryid)
);
CREATE UNIQUE INDEX trigger_discovery_1 on trigger_discovery (triggerid,parent_triggerid);
ALTER TABLE trigger_discovery ADD CONSTRAINT c_trigger_discovery_1 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
ALTER TABLE trigger_discovery ADD CONSTRAINT c_trigger_discovery_2 FOREIGN KEY (parent_triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
