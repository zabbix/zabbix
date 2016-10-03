CREATE TABLE maintenances_groups (
        maintenance_groupid             number(20)              DEFAULT '0'     NOT NULL,
        maintenanceid           number(20)              DEFAULT '0'     NOT NULL,
        groupid         number(20)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (maintenance_groupid)
);
CREATE INDEX maintenances_groups_1 on maintenances_groups (maintenanceid,groupid);

