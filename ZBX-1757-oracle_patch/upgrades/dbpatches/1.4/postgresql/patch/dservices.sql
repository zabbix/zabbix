CREATE TABLE dservices (
        dserviceid      bigint DEFAULT '0'     NOT NULL,
        dhostid         bigint DEFAULT '0'     NOT NULL,
        type            integer         DEFAULT '0'     NOT NULL,
        key_            varchar(255)            DEFAULT '0'     NOT NULL,
        value           varchar(255)            DEFAULT '0'     NOT NULL,
        port            integer         DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        lastup          integer         DEFAULT '0'     NOT NULL,
        lastdown        integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (dserviceid)
) with OIDS;
