CREATE TABLE dhosts_tmp (
        dhostid         bigint          DEFAULT '0'     NOT NULL,
        druleid         bigint          DEFAULT '0'     NOT NULL,
        ip              varchar(39)             DEFAULT ''      NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        lastup          integer         DEFAULT '0'     NOT NULL,
        lastdown                integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (dhostid)
) with OIDS;

insert into dhosts_tmp select * from dhosts;
drop table dhosts;
alter table dhosts_tmp rename to dhosts;

CREATE INDEX dhosts_1 on dhosts (druleid,ip);
