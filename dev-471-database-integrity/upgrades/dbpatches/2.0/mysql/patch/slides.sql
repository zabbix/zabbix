ALTER TABLE slides MODIFY slideid bigint unsigned NOT NULL,
		   MODIFY slideshowid bigint unsigned NOT NULL,
		   MODIFY screenid bigint unsigned NOT NULL;
DELETE FROM slides WHERE NOT slideshowid IN (SELECT slideshowid FROM slideshows);
DELETE FROM slides WHERE NOT screenid IN (SELECT screenid FROM screens);
ALTER TABLE slides ADD CONSTRAINT c_slides_1 FOREIGN KEY (slideshowid) REFERENCES slideshows (slideshowid) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE slides ADD CONSTRAINT c_slides_2 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON UPDATE CASCADE ON DELETE CASCADE;
