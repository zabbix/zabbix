CREATE TABLE maintenances_windows (
        maintenance_timeperiodid                number(20)              DEFAULT '0'     NOT NULL,
        maintenanceid           number(20)              DEFAULT '0'     NOT NULL,
        timeperiodid            number(20)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (maintenance_timeperiodid)
);
CREATE INDEX maintenances_windows_1 on maintenances_windows (maintenanceid,timeperiodid);

