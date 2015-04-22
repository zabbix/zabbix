ALTER TABLE httpstepitem MODIFY httpstepitemid bigint unsigned NOT NULL,
			 MODIFY httpstepid bigint unsigned NOT NULL,
			 MODIFY itemid bigint unsigned NOT NULL;
DELETE FROM httpstepitem WHERE NOT httpstepid IN (SELECT httpstepid FROM httpstep);
DELETE FROM httpstepitem WHERE NOT itemid IN (SELECT itemid FROM items);
ALTER TABLE httpstepitem ADD CONSTRAINT c_httpstepitem_1 FOREIGN KEY (httpstepid) REFERENCES httpstep (httpstepid) ON DELETE CASCADE;
ALTER TABLE httpstepitem ADD CONSTRAINT c_httpstepitem_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
