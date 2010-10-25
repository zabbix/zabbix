ALTER TABLE services MODIFY serviceid bigint unsigned NOT NULL;
ALTER TABLE services ADD CONSTRAINT c_services_1 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
