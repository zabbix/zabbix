UPDATE services SET triggerid = NULL WHERE NOT EXISTS (SELECT 1 FROM triggers t WHERE t.triggerid = services.triggerid);
ALTER TABLE ONLY services ALTER serviceid DROP DEFAULT;
ALTER TABLE ONLY services ADD CONSTRAINT c_services_1 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
