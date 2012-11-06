CREATE TABLE proxy_dhistory_tmp (
        id              serial                  NOT NULL,
        clock           integer         DEFAULT '0'     NOT NULL,
        druleid         bigint          DEFAULT '0'     NOT NULL,
        type            integer         DEFAULT '0'     NOT NULL,
        ip              varchar(39)             DEFAULT ''      NOT NULL,
        port            integer         DEFAULT '0'     NOT NULL,
        key_            varchar(255)            DEFAULT ''      NOT NULL,
        value           varchar(255)            DEFAULT ''      NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        dcheckid                bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (id)
) with OIDS;

insert into proxy_dhistory_tmp select id,clock,druleid,type,ip,port,key_,value,status,0 from proxy_dhistory;
drop table proxy_dhistory;
alter table proxy_dhistory_tmp rename to proxy_dhistory;

CREATE INDEX proxy_dhistory_1 on proxy_dhistory (clock);
