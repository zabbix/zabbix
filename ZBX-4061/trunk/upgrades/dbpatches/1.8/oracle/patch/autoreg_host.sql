CREATE TABLE autoreg_host (
        autoreg_hostid          number(20)              DEFAULT '0'     NOT NULL,
        proxy_hostid            number(20)              DEFAULT '0'     NOT NULL,
        host            nvarchar2(64)           DEFAULT ''      ,
        PRIMARY KEY (autoreg_hostid)
);
CREATE UNIQUE INDEX autoreg_host_1 on autoreg_host (proxy_hostid,host);
