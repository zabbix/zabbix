ALTER TABLE ONLY sysmaps_link_triggers ALTER linktriggerid DROP DEFAULT,
				       ALTER linkid DROP DEFAULT,
				       ALTER triggerid DROP DEFAULT;
DELETE FROM sysmaps_link_triggers WHERE NOT EXISTS (SELECT 1 FROM sysmaps_links WHERE sysmaps_links.linkid=sysmaps_link_triggers.linkid);
DELETE FROM sysmaps_link_triggers WHERE NOT EXISTS (SELECT 1 FROM triggers WHERE triggers.triggerid=sysmaps_link_triggers.triggerid);
ALTER TABLE ONLY sysmaps_link_triggers ADD CONSTRAINT c_sysmaps_link_triggers_1 FOREIGN KEY (linkid) REFERENCES sysmaps_links (linkid) ON DELETE CASCADE;
ALTER TABLE ONLY sysmaps_link_triggers ADD CONSTRAINT c_sysmaps_link_triggers_2 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
