CREATE TABLE proxy_history (
        id              bigint unsigned                 NOT NULL        auto_increment unique,
        itemid          bigint unsigned         DEFAULT '0'     NOT NULL,
        clock           integer         DEFAULT '0'     NOT NULL,
        timestamp               integer         DEFAULT '0'     NOT NULL,
        source          varchar(64)             DEFAULT ''      NOT NULL,
        severity                integer         DEFAULT '0'     NOT NULL,
        value           text                    NOT NULL,
        PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE INDEX proxy_history_1 on proxy_history (clock);
