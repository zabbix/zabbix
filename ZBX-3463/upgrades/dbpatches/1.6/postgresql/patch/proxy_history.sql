CREATE TABLE proxy_history (
        id              serial                  NOT NULL,
        itemid          bigint          DEFAULT '0'     NOT NULL,
        clock           integer         DEFAULT '0'     NOT NULL,
        timestamp               integer         DEFAULT '0'     NOT NULL,
        source          varchar(64)             DEFAULT ''      NOT NULL,
        severity                integer         DEFAULT '0'     NOT NULL,
        value           text            DEFAULT ''      NOT NULL,
        PRIMARY KEY (id)
) with OIDS;
CREATE INDEX proxy_history_1 on proxy_history (clock);
