ALTER TABLE screens MODIFY screenid bigint unsigned NOT NULL,
		    MODIFY name varchar(255) NOT NULL,
		    ADD templateid bigint unsigned NULL;
ALTER TABLE screens ADD CONSTRAINT c_screens_1 FOREIGN KEY (templateid) REFERENCES hosts (hostid) ON DELETE CASCADE;
