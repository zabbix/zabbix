CREATE TABLE trends_uint (
        itemid          bigint unsigned         DEFAULT '0'     NOT NULL,
        clock           integer         DEFAULT '0'     NOT NULL,
        num             integer         DEFAULT '0'     NOT NULL,
        value_min               bigint unsigned         DEFAULT '0'     NOT NULL,
        value_avg               bigint unsigned         DEFAULT '0'     NOT NULL,
        value_max               bigint unsigned         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (itemid,clock)
) ENGINE=InnoDB;
