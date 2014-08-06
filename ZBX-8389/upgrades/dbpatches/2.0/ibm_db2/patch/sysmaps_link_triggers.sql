ALTER TABLE sysmaps_link_triggers ALTER COLUMN linktriggerid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_link_triggers
/
ALTER TABLE sysmaps_link_triggers ALTER COLUMN linkid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_link_triggers
/
ALTER TABLE sysmaps_link_triggers ALTER COLUMN triggerid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_link_triggers
/
DELETE FROM sysmaps_link_triggers WHERE linkid NOT IN (SELECT linkid FROM sysmaps_links)
/
DELETE FROM sysmaps_link_triggers WHERE triggerid NOT IN (SELECT triggerid FROM triggers)
/
ALTER TABLE sysmaps_link_triggers ADD CONSTRAINT c_sysmaps_link_triggers_1 FOREIGN KEY (linkid) REFERENCES sysmaps_links (linkid) ON DELETE CASCADE
/
ALTER TABLE sysmaps_link_triggers ADD CONSTRAINT c_sysmaps_link_triggers_2 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE
/
