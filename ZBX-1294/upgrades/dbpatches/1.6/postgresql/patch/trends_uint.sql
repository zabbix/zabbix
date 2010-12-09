CREATE TABLE trends_uint (
        itemid          bigint          DEFAULT '0'     NOT NULL,
        clock           integer         DEFAULT '0'     NOT NULL,
        num             integer         DEFAULT '0'     NOT NULL,
        value_min               bigint          DEFAULT '0'     NOT NULL,
        value_avg               bigint          DEFAULT '0'     NOT NULL,
        value_max               bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (itemid,clock)
) with OIDS;
