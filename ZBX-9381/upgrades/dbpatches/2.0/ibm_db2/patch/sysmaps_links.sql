ALTER TABLE sysmaps_links ALTER COLUMN linkid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_links
/
ALTER TABLE sysmaps_links ALTER COLUMN sysmapid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_links
/
ALTER TABLE sysmaps_links ALTER COLUMN selementid1 SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_links
/
ALTER TABLE sysmaps_links ALTER COLUMN selementid2 SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_links
/
DELETE FROM sysmaps_links WHERE sysmapid NOT IN (SELECT sysmapid FROM sysmaps)
/
DELETE FROM sysmaps_links WHERE selementid1 NOT IN (SELECT selementid FROM sysmaps_elements)
/
DELETE FROM sysmaps_links WHERE selementid2 NOT IN (SELECT selementid FROM sysmaps_elements)
/
ALTER TABLE sysmaps_links ADD CONSTRAINT c_sysmaps_links_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE
/
ALTER TABLE sysmaps_links ADD CONSTRAINT c_sysmaps_links_2 FOREIGN KEY (selementid1) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE
/
ALTER TABLE sysmaps_links ADD CONSTRAINT c_sysmaps_links_3 FOREIGN KEY (selementid2) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE
/
