CREATE TABLE maintenances_groups (
        maintenance_groupid             bigint          DEFAULT '0'     NOT NULL,
        maintenanceid           bigint          DEFAULT '0'     NOT NULL,
        groupid         bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (maintenance_groupid)
) with OIDS;
CREATE INDEX maintenances_groups_1 on maintenances_groups (maintenanceid,groupid);
