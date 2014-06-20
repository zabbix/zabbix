ALTER TABLE ONLY hosts_groups ALTER hostgroupid DROP DEFAULT,
			      ALTER hostid DROP DEFAULT,
			      ALTER groupid DROP DEFAULT;
DELETE FROM hosts_groups WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=hosts_groups.hostid);
DELETE FROM hosts_groups WHERE NOT EXISTS (SELECT 1 FROM groups WHERE groups.groupid=hosts_groups.groupid);
-- remove duplicates to allow unique index
DELETE FROM hosts_groups
	WHERE hostgroupid IN (
		SELECT hg1.hostgroupid
		FROM hosts_groups hg1
		LEFT OUTER JOIN (
			SELECT MIN(hg2.hostgroupid) AS hostgroupid
			FROM hosts_groups hg2
			GROUP BY hg2.hostid,hg2.groupid
		) keep_rows ON
			hg1.hostgroupid=keep_rows.hostgroupid
		WHERE keep_rows.hostgroupid IS NULL
	);
DROP INDEX hosts_groups_1;
CREATE UNIQUE INDEX hosts_groups_1 ON hosts_groups (hostid,groupid);
ALTER TABLE ONLY hosts_groups ADD CONSTRAINT c_hosts_groups_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE ONLY hosts_groups ADD CONSTRAINT c_hosts_groups_2 FOREIGN KEY (groupid) REFERENCES groups (groupid) ON DELETE CASCADE;
