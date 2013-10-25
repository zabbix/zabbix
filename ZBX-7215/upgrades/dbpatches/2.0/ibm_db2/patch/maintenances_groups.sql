ALTER TABLE maintenances_groups ALTER COLUMN maintenance_groupid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_groups
/
ALTER TABLE maintenances_groups ALTER COLUMN maintenanceid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_groups
/
ALTER TABLE maintenances_groups ALTER COLUMN groupid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_groups
/
DROP INDEX maintenances_groups_1
/
DELETE FROM maintenances_groups WHERE maintenanceid NOT IN (SELECT maintenanceid FROM maintenances)
/
DELETE FROM maintenances_groups WHERE groupid NOT IN (SELECT groupid FROM groups)
/
CREATE UNIQUE INDEX maintenances_groups_1 ON maintenances_groups (maintenanceid,groupid)
/
ALTER TABLE maintenances_groups ADD CONSTRAINT c_maintenances_groups_1 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid) ON DELETE CASCADE
/
ALTER TABLE maintenances_groups ADD CONSTRAINT c_maintenances_groups_2 FOREIGN KEY (groupid) REFERENCES groups (groupid) ON DELETE CASCADE
/
