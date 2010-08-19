ALTER TABLE ONLY slides ALTER slideid DROP DEFAULT,
			ALTER slideshowid DROP DEFAULT,
			ALTER screenid DROP DEFAULT;
DELETE FROM slides WHERE NOT slideshowid IN (SELECT slideshowid FROM slideshows);
DELETE FROM slides WHERE NOT screenid IN (SELECT screenid FROM screens);
ALTER TABLE ONLY slides ADD CONSTRAINT c_slides_1 FOREIGN KEY (slideshowid) REFERENCES slideshows (slideshowid) ON DELETE CASCADE;
ALTER TABLE ONLY slides ADD CONSTRAINT c_slides_2 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE;
