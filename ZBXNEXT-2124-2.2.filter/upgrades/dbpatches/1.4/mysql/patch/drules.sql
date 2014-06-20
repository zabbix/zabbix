CREATE TABLE drules (
        druleid         bigint unsigned         DEFAULT '0'     NOT NULL,
        name            varchar(255)            DEFAULT ''      NOT NULL,
        iprange         varchar(255)            DEFAULT ''      NOT NULL,
        iplast          varchar(15)             DEFAULT ''      NOT NULL,
        delay           integer         DEFAULT '0'     NOT NULL,
        nextcheck               integer         DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (druleid)
) ENGINE=InnoDB;
