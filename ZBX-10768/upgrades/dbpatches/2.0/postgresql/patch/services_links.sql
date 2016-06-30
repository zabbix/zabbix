ALTER TABLE ONLY services_links ALTER linkid DROP DEFAULT,
				ALTER serviceupid DROP DEFAULT,
				ALTER servicedownid DROP DEFAULT;
DELETE FROM services_links WHERE NOT EXISTS (SELECT 1 FROM services WHERE services.serviceid=services_links.serviceupid);
DELETE FROM services_links WHERE NOT EXISTS (SELECT 1 FROM services WHERE services.serviceid=services_links.servicedownid);
ALTER TABLE ONLY services_links ADD CONSTRAINT c_services_links_1 FOREIGN KEY (serviceupid) REFERENCES services (serviceid) ON DELETE CASCADE;
ALTER TABLE ONLY services_links ADD CONSTRAINT c_services_links_2 FOREIGN KEY (servicedownid) REFERENCES services (serviceid) ON DELETE CASCADE;
