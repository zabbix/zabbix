ALTER TABLE services_links ALTER COLUMN linkid SET WITH DEFAULT NULL
/
REORG TABLE services_links
/
ALTER TABLE services_links ALTER COLUMN serviceupid SET WITH DEFAULT NULL
/
REORG TABLE services_links
/
ALTER TABLE services_links ALTER COLUMN servicedownid SET WITH DEFAULT NULL
/
REORG TABLE services_links
/
DELETE FROM services_links WHERE NOT serviceupid IN (SELECT serviceid FROM services)
/
DELETE FROM services_links WHERE NOT servicedownid IN (SELECT serviceid FROM services)
/
ALTER TABLE services_links ADD CONSTRAINT c_services_links_1 FOREIGN KEY (serviceupid) REFERENCES services (serviceid) ON DELETE CASCADE
/
ALTER TABLE services_links ADD CONSTRAINT c_services_links_2 FOREIGN KEY (servicedownid) REFERENCES services (serviceid) ON DELETE CASCADE
/
