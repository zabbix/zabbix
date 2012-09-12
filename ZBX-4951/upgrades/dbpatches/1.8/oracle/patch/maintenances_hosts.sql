CREATE TABLE maintenances_hosts (
        maintenance_hostid              number(20)              DEFAULT '0'     NOT NULL,
        maintenanceid           number(20)              DEFAULT '0'     NOT NULL,
        hostid          number(20)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (maintenance_hostid)
);
CREATE INDEX maintenances_hosts_1 on maintenances_hosts (maintenanceid,hostid);

