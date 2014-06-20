CREATE TABLE maintenances (
        maintenanceid           number(20)              DEFAULT '0'     NOT NULL,
        name            nvarchar2(128)          DEFAULT ''      ,
        maintenance_type                number(10)              DEFAULT '0'     NOT NULL,
        description             nvarchar2(2048)         DEFAULT ''      ,
        active_since            number(10)              DEFAULT '0'     NOT NULL,
        active_till             number(10)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (maintenanceid)
);
CREATE INDEX maintenances_1 on maintenances (active_since,active_till);

