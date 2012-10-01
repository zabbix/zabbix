CREATE TABLE maintenances_windows (
        maintenance_timeperiodid                bigint          DEFAULT '0'     NOT NULL,
        maintenanceid           bigint          DEFAULT '0'     NOT NULL,
        timeperiodid            bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (maintenance_timeperiodid)
) with OIDS;
CREATE INDEX maintenances_windows_1 on maintenances_windows (maintenanceid,timeperiodid);
