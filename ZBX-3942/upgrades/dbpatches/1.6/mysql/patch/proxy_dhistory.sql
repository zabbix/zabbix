CREATE TABLE proxy_dhistory (
        id              bigint unsigned                 NOT NULL        auto_increment unique,
        clock           integer         DEFAULT '0'     NOT NULL,
        druleid         bigint unsigned         DEFAULT '0'     NOT NULL,
        type            integer         DEFAULT '0'     NOT NULL,
        ip              varchar(39)             DEFAULT ''      NOT NULL,
        port            integer         DEFAULT '0'     NOT NULL,
        key_            varchar(255)            DEFAULT '0'     NOT NULL,
        value           varchar(255)            DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE INDEX proxy_dhistory_1 on proxy_dhistory (clock);
