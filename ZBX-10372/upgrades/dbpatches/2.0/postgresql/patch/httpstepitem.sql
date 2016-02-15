ALTER TABLE ONLY httpstepitem ALTER httpstepitemid DROP DEFAULT,
			      ALTER httpstepid DROP DEFAULT,
			      ALTER itemid DROP DEFAULT;
DELETE FROM httpstepitem WHERE NOT EXISTS (SELECT 1 FROM httpstep WHERE httpstep.httpstepid=httpstepitem.httpstepid);
DELETE FROM httpstepitem WHERE NOT EXISTS (SELECT 1 FROM items WHERE items.itemid=httpstepitem.itemid);
ALTER TABLE ONLY httpstepitem ADD CONSTRAINT c_httpstepitem_1 FOREIGN KEY (httpstepid) REFERENCES httpstep (httpstepid) ON DELETE CASCADE;
ALTER TABLE ONLY httpstepitem ADD CONSTRAINT c_httpstepitem_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
