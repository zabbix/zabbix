ALTER TABLE screens MODIFY screenid DEFAULT NULL;
ALTER TABLE screens MODIFY name DEFAULT NULL;
ALTER TABLE screens ADD templateid number(20) NULL;
ALTER TABLE screens ADD CONSTRAINT c_screens_1 FOREIGN KEY (templateid) REFERENCES hosts (hostid) ON DELETE CASCADE;
