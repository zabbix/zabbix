ALTER TABLE hosts_groups MODIFY hostgroupid bigint unsigned NOT NULL,
			 MODIFY hostid bigint unsigned NOT NULL,
			 MODIFY groupid bigint unsigned NOT NULL;
DELETE FROM hosts_groups WHERE NOT hostid IN (SELECT hostid FROM hosts);
DELETE FROM hosts_groups WHERE NOT groupid IN (SELECT groupid FROM groups);
-- remove duplicates to allow unique index
DELETE hosts_groups
	FROM hosts_groups
	LEFT OUTER JOIN (
		SELECT MIN(hostgroupid) AS hostgroupid
		FROM hosts_groups
		GROUP BY hostid,groupid
	) keep_rows ON
		hosts_groups.hostgroupid=keep_rows.hostgroupid
	WHERE keep_rows.hostgroupid IS NULL;
DROP INDEX hosts_groups_1 ON hosts_groups;
CREATE UNIQUE INDEX hosts_groups_1 ON hosts_groups (hostid,groupid);
ALTER TABLE hosts_groups ADD CONSTRAINT c_hosts_groups_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE hosts_groups ADD CONSTRAINT c_hosts_groups_2 FOREIGN KEY (groupid) REFERENCES groups (groupid) ON DELETE CASCADE;
