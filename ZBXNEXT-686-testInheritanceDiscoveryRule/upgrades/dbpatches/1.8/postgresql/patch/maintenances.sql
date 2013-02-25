CREATE TABLE maintenances (
        maintenanceid           bigint          DEFAULT '0'     NOT NULL,
        name            varchar(128)            DEFAULT ''      NOT NULL,
        maintenance_type                integer         DEFAULT '0'     NOT NULL,
        description             text            DEFAULT ''      NOT NULL,
        active_since            integer         DEFAULT '0'     NOT NULL,
        active_till             integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (maintenanceid)
) with OIDS;
CREATE INDEX maintenances_1 on maintenances (active_since,active_till);
