CREATE TABLE services_tmp (
        serviceid               bigint          DEFAULT '0'     NOT NULL,
        name            varchar(128)            DEFAULT ''      NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        algorithm               integer         DEFAULT '0'     NOT NULL,
        triggerid               bigint                  ,
        showsla         integer         DEFAULT '0'     NOT NULL,
        goodsla         numeric(16,4)           DEFAULT '99.9'  NOT NULL,
        sortorder               integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (serviceid)
) with OIDS;

insert into services_tmp select * from services;
drop table services;
alter table services_tmp rename to services;

CREATE INDEX services_1 on services (triggerid);
