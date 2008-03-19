CREATE TABLE history_str_sync (
        id              number(20)                      ,
        nodeid          number(20)              DEFAULT '0'     NOT NULL,
        itemid          number(20)              DEFAULT '0'     NOT NULL,
        clock           number(10)              DEFAULT '0'     NOT NULL,
        value           varchar2(255)           DEFAULT ''      ,
        PRIMARY KEY (id)
);
CREATE INDEX history_str_sync_1 on history_str_sync (nodeid,id);
