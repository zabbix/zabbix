CREATE TABLE valuemaps (
        valuemapid              number(20)              DEFAULT '0'     NOT NULL,
        name            varchar2(64)            DEFAULT ''      ,
        PRIMARY KEY (valuemapid)
);
CREATE INDEX valuemaps_1 on valuemaps (name);

insert into valuemaps_tmp select * from valuemaps;
drop table valuemaps;
alter table valuemaps_tmp rename valuemaps;
