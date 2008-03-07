CREATE TABLE proxies (
        proxyid         bigint unsigned         DEFAULT '0'     NOT NULL,
        name            varchar(64)             DEFAULT ''      NOT NULL,
        lastaccess              integer         DEFAULT '0'     NOT NULL,
        history_lastid          bigint unsigned         DEFAULT '0'     NOT NULL,
        history_uint_lastid             bigint unsigned         DEFAULT '0'     NOT NULL,
        history_str_lastid              bigint unsigned         DEFAULT '0'     NOT NULL,
        history_text_lastid             bigint unsigned         DEFAULT '0'     NOT NULL,
        history_log_lastid              bigint unsigned         DEFAULT '0'     NOT NULL,
        dhistory_lastid         bigint unsigned         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (proxyid)
) type=InnoDB;
CREATE INDEX proxies_1 on proxies (name);
