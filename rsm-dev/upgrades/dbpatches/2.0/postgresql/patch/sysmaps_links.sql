ALTER TABLE ONLY sysmaps_links ALTER linkid DROP DEFAULT,
			       ALTER sysmapid DROP DEFAULT,
			       ALTER selementid1 DROP DEFAULT,
			       ALTER selementid2 DROP DEFAULT;
DELETE FROM sysmaps_links WHERE NOT EXISTS (SELECT 1 FROM sysmaps WHERE sysmaps.sysmapid=sysmaps_links.sysmapid);
DELETE FROM sysmaps_links WHERE NOT EXISTS (SELECT 1 FROM sysmaps_elements WHERE sysmaps_elements.selementid=sysmaps_links.selementid1);
DELETE FROM sysmaps_links WHERE NOT EXISTS (SELECT 1 FROM sysmaps_elements WHERE sysmaps_elements.selementid=sysmaps_links.selementid2);
ALTER TABLE ONLY sysmaps_links ADD CONSTRAINT c_sysmaps_links_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE;
ALTER TABLE ONLY sysmaps_links ADD CONSTRAINT c_sysmaps_links_2 FOREIGN KEY (selementid1) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE;
ALTER TABLE ONLY sysmaps_links ADD CONSTRAINT c_sysmaps_links_3 FOREIGN KEY (selementid2) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE;
