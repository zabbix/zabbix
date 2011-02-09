ALTER TABLE services ALTER COLUMN serviceid SET WITH DEFAULT NULL
/
REORG TABLE services
/
ALTER TABLE services ADD CONSTRAINT c_services_1 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE
/
