CREATE TABLE proxy_autoreg_host (
        id              serial                  NOT NULL,
        clock           integer         DEFAULT '0'     NOT NULL,
        host            varchar(64)             DEFAULT ''      NOT NULL,
        PRIMARY KEY (id)
) with OIDS;
CREATE INDEX proxy_autoreg_host_1 on proxy_autoreg_host (clock);
