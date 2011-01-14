ALTER TABLE ONLY slides ALTER slideid DROP DEFAULT,
			ALTER slideshowid DROP DEFAULT,
			ALTER screenid DROP DEFAULT;
DELETE FROM slides WHERE NOT EXISTS (SELECT 1 FROM slideshows WHERE slideshows.slideshowid=slides.slideshowid);
DELETE FROM slides WHERE NOT EXISTS (SELECT 1 FROM screens WHERE screens.screenid=slides.screenid);
ALTER TABLE ONLY slides ADD CONSTRAINT c_slides_1 FOREIGN KEY (slideshowid) REFERENCES slideshows (slideshowid) ON DELETE CASCADE;
ALTER TABLE ONLY slides ADD CONSTRAINT c_slides_2 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE;
