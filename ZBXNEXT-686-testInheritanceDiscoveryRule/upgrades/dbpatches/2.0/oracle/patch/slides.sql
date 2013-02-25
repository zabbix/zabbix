ALTER TABLE slides MODIFY slideid DEFAULT NULL;
ALTER TABLE slides MODIFY slideshowid DEFAULT NULL;
ALTER TABLE slides MODIFY screenid DEFAULT NULL;
DELETE FROM slides WHERE NOT slideshowid IN (SELECT slideshowid FROM slideshows);
DELETE FROM slides WHERE NOT screenid IN (SELECT screenid FROM screens);
ALTER TABLE slides ADD CONSTRAINT c_slides_1 FOREIGN KEY (slideshowid) REFERENCES slideshows (slideshowid) ON DELETE CASCADE;
ALTER TABLE slides ADD CONSTRAINT c_slides_2 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE;
