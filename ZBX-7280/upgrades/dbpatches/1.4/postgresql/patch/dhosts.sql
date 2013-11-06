CREATE TABLE dhosts (
        dhostid         bigint DEFAULT '0'     NOT NULL,
        druleid         bigint DEFAULT '0'     NOT NULL,
        ip              varchar(15)             DEFAULT ''      NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        lastup          integer         DEFAULT '0'     NOT NULL,
        lastdown        integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (dhostid)
) with OIDS;
