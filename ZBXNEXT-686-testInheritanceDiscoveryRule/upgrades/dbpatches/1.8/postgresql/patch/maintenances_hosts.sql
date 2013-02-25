CREATE TABLE maintenances_hosts (
        maintenance_hostid              bigint          DEFAULT '0'     NOT NULL,
        maintenanceid           bigint          DEFAULT '0'     NOT NULL,
        hostid          bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (maintenance_hostid)
) with OIDS;
CREATE INDEX maintenances_hosts_1 on maintenances_hosts (maintenanceid,hostid);
