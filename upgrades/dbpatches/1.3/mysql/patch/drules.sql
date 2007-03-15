CREATE TABLE drules (
        druleid         bigint unsigned         DEFAULT '0'     NOT NULL,
        name            varchar(255)            DEFAULT ''      NOT NULL,
        ipfirst         varchar(15)             DEFAULT ''      NOT NULL,
        iplast          varchar(15)             DEFAULT ''      NOT NULL,
        delay           integer         DEFAULT '0'     NOT NULL,
        nextcheck               integer         DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        upevent         integer         DEFAULT '0'     NOT NULL,
        downevent               integer         DEFAULT '0'     NOT NULL,
        svcupevent              integer         DEFAULT '0'     NOT NULL,
        svcdownevent            integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (druleid)
) type=InnoDB;
