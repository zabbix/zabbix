CREATE TABLE maintenances_groups (
      maintenance_groupid             bigint unsigned         DEFAULT '0'     NOT NULL,
      maintenanceid           bigint unsigned         DEFAULT '0'     NOT NULL,
      groupid         bigint unsigned         DEFAULT '0'     NOT NULL,
      PRIMARY KEY (maintenance_groupid)
) ENGINE=InnoDB;
CREATE INDEX maintenances_groups_1 on maintenances_groups (maintenanceid,groupid);
