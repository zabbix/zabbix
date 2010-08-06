ALTER TABLE services_links MODIFY serviceupid bigint unsigned NOT NULL;
ALTER TABLE services_links MODIFY servicedownid bigint unsigned NOT NULL;
DELETE FROM services_links WHERE NOT serviceupid IN (SELECT serviceid FROM services);
DELETE FROM services_links WHERE NOT servicedownid IN (SELECT serviceid FROM services);
ALTER TABLE services_links ADD CONSTRAINT c_services_links_1 FOREIGN KEY (serviceupid) REFERENCES services (serviceid) ON DELETE CASCADE;
ALTER TABLE services_links ADD CONSTRAINT c_services_links_2 FOREIGN KEY (servicedownid) REFERENCES services (serviceid) ON DELETE CASCADE;
