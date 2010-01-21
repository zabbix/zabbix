CREATE TABLE proxy_autoreg_host (
        id              number(20)                      NOT NULL,
        clock           number(10)              DEFAULT '0'     NOT NULL,
        host            nvarchar2(64)           DEFAULT ''      ,
        PRIMARY KEY (id)
);
CREATE INDEX proxy_autoreg_host_1 on proxy_autoreg_host (clock);

