CREATE TABLE maintenances_hosts (
      maintenance_hostid              bigint unsigned         DEFAULT '0'     NOT NULL,
      maintenanceid           bigint unsigned         DEFAULT '0'     NOT NULL,
      hostid          bigint unsigned         DEFAULT '0'     NOT NULL,
      PRIMARY KEY (maintenance_hostid)
) ENGINE=InnoDB;
CREATE INDEX maintenances_hosts_1 on maintenances_hosts (maintenanceid,hostid);
