ALTER TABLE httpstepitem MODIFY httpstepitemid DEFAULT NULL;
ALTER TABLE httpstepitem MODIFY httpstepid DEFAULT NULL;
ALTER TABLE httpstepitem MODIFY itemid DEFAULT NULL;
DELETE FROM httpstepitem WHERE NOT httpstepid IN (SELECT httpstepid FROM httpstep);
DELETE FROM httpstepitem WHERE NOT itemid IN (SELECT itemid FROM items);
ALTER TABLE httpstepitem ADD CONSTRAINT c_httpstepitem_1 FOREIGN KEY (httpstepid) REFERENCES httpstep (httpstepid) ON DELETE CASCADE;
ALTER TABLE httpstepitem ADD CONSTRAINT c_httpstepitem_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
