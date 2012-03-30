CREATE TABLE autoreg_host (
        autoreg_hostid          bigint          DEFAULT '0'     NOT NULL,
        proxy_hostid            bigint          DEFAULT '0'     NOT NULL,
        host            varchar(64)             DEFAULT ''      NOT NULL,
        PRIMARY KEY (autoreg_hostid)
) with OIDS;
CREATE INDEX autoreg_host_1 on autoreg_host (proxy_hostid,host);
