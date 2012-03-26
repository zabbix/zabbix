ALTER TABLE slides ALTER COLUMN slideid SET WITH DEFAULT NULL
/
REORG TABLE slides
/
ALTER TABLE slides ALTER COLUMN slideshowid SET WITH DEFAULT NULL
/
REORG TABLE slides
/
ALTER TABLE slides ALTER COLUMN screenid SET WITH DEFAULT NULL
/
REORG TABLE slides
/
DELETE FROM slides WHERE NOT slideshowid IN (SELECT slideshowid FROM slideshows)
/
DELETE FROM slides WHERE NOT screenid IN (SELECT screenid FROM screens)
/
ALTER TABLE slides ADD CONSTRAINT c_slides_1 FOREIGN KEY (slideshowid) REFERENCES slideshows (slideshowid) ON DELETE CASCADE
/
ALTER TABLE slides ADD CONSTRAINT c_slides_2 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE
/
