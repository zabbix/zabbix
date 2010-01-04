alter table proxy_history add logeventid              number(10)         DEFAULT '0'     NOT NULL;

CREATE TABLE proxy_history_tmp (
        id              number(20)              DEFAULT '0'     NOT NULL,
        itemid          number(20)              DEFAULT '0'     NOT NULL,
        clock           number(10)              DEFAULT '0'     NOT NULL,
        timestamp               number(10)              DEFAULT '0'     NOT NULL,
        source          nvarchar2(64)           DEFAULT ''      ,
        severity                number(10)              DEFAULT '0'     NOT NULL,
        value           nclob           DEFAULT ''      ,
        logeventid              number(10)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (id)
);


insert into proxy_history_tmp select * from proxy_history;
drop table proxy_history;

alter table proxy_history_tmp rename to proxy_history;

CREATE INDEX proxy_history_1 on proxy_history (clock);
