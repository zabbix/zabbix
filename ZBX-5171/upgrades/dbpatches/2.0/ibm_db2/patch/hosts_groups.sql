ALTER TABLE hosts_groups ALTER COLUMN hostgroupid SET WITH DEFAULT NULL
/
REORG TABLE hosts_groups
/
ALTER TABLE hosts_groups ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE hosts_groups
/
ALTER TABLE hosts_groups ALTER COLUMN groupid SET WITH DEFAULT NULL
/
REORG TABLE hosts_groups
/
DELETE FROM hosts_groups WHERE NOT hostid IN (SELECT hostid FROM hosts)
/
DELETE FROM hosts_groups WHERE NOT groupid IN (SELECT groupid FROM groups)
/
-- remove duplicates to allow unique index
DELETE FROM hosts_groups
	WHERE hostgroupid IN (
		SELECT hg1.hostgroupid
		FROM hosts_groups hg1
		LEFT OUTER JOIN (
			SELECT MIN(hg2.hostgroupid) AS hostgroupid
			FROM hosts_groups hg2
			GROUP BY hostid,groupid
		) keep_rows ON
			hg1.hostgroupid=keep_rows.hostgroupid
		WHERE keep_rows.hostgroupid IS NULL
	)
/
DROP INDEX hosts_groups_1
/
CREATE UNIQUE INDEX hosts_groups_1 ON hosts_groups (hostid,groupid)
/
ALTER TABLE hosts_groups ADD CONSTRAINT c_hosts_groups_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
ALTER TABLE hosts_groups ADD CONSTRAINT c_hosts_groups_2 FOREIGN KEY (groupid) REFERENCES groups (groupid) ON DELETE CASCADE
/
