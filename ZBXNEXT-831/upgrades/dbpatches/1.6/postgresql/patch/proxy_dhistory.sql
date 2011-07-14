CREATE TABLE proxy_dhistory (
        id              serial                  NOT NULL,
        clock           integer         DEFAULT '0'     NOT NULL,
        druleid         bigint          DEFAULT '0'     NOT NULL,
        type            integer         DEFAULT '0'     NOT NULL,
        ip              varchar(39)             DEFAULT ''      NOT NULL,
        port            integer         DEFAULT '0'     NOT NULL,
        key_            varchar(255)            DEFAULT '0'     NOT NULL,
        value           varchar(255)            DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (id)
) with OIDS;
CREATE INDEX proxy_dhistory_1 on proxy_dhistory (clock);
