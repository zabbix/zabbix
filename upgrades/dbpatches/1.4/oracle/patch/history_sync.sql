CREATE TABLE history_sync (
        id              number(20)                      ,
        nodeid          number(20)              DEFAULT '0'     NOT NULL,
        itemid          number(20)              DEFAULT '0'     NOT NULL,
        clock           number(10)              DEFAULT '0'     NOT NULL,
        value           number(20,4)            DEFAULT '0.0000'        NOT NULL,
        PRIMARY KEY (id)
);
CREATE INDEX history_sync_1 on history_sync (nodeid,id);
