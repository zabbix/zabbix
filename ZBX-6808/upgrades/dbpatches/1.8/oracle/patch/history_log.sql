alter table history_log add logeventid              number(10)         DEFAULT '0'     NOT NULL;

CREATE TABLE history_log_tmp (
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


insert into history_log_tmp select * from history_log;
drop table history_log;

alter table history_log_tmp rename to history_log;

CREATE INDEX history_log_1 on history_log (itemid,clock);
CREATE UNIQUE INDEX history_log_2 on history_log (itemid,id);

