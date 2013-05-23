ALTER TABLE sysmaps_link_triggers MODIFY linktriggerid DEFAULT NULL;
ALTER TABLE sysmaps_link_triggers MODIFY linkid DEFAULT NULL;
ALTER TABLE sysmaps_link_triggers MODIFY triggerid DEFAULT NULL;
DELETE FROM sysmaps_link_triggers WHERE linkid NOT IN (SELECT linkid FROM sysmaps_links);
DELETE FROM sysmaps_link_triggers WHERE triggerid NOT IN (SELECT triggerid FROM triggers);
ALTER TABLE sysmaps_link_triggers ADD CONSTRAINT c_sysmaps_link_triggers_1 FOREIGN KEY (linkid) REFERENCES sysmaps_links (linkid) ON DELETE CASCADE;
ALTER TABLE sysmaps_link_triggers ADD CONSTRAINT c_sysmaps_link_triggers_2 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
