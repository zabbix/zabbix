CREATE TABLE maintenances_windows (
      maintenance_timeperiodid                bigint unsigned         DEFAULT '0'     NOT NULL,
      maintenanceid           bigint unsigned         DEFAULT '0'     NOT NULL,
      timeperiodid            bigint unsigned         DEFAULT '0'     NOT NULL,
      PRIMARY KEY (maintenance_timeperiodid)
) ENGINE=InnoDB;
CREATE INDEX maintenances_windows_1 on maintenances_windows (maintenanceid,timeperiodid);
