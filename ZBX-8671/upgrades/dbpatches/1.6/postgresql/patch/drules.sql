CREATE TABLE drules_tmp (
        druleid         bigint          DEFAULT '0'     NOT NULL,
        proxy_hostid            bigint          DEFAULT '0'     NOT NULL,
        name            varchar(255)            DEFAULT ''      NOT NULL,
        iprange         varchar(255)            DEFAULT ''      NOT NULL,
        delay           integer         DEFAULT '0'     NOT NULL,
        nextcheck               integer         DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (druleid)
) with OIDS;

insert into drules_tmp select druleid,0,name,iprange,delay,nextcheck,status from drules;
drop table drules;
alter table drules_tmp rename to drules;
