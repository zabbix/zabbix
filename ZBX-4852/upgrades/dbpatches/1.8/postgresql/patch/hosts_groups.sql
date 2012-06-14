drop index hosts_groups_groups_1;

CREATE INDEX hosts_groups_1 on hosts_groups (hostid,groupid);
CREATE INDEX hosts_groups_2 on hosts_groups (groupid);
