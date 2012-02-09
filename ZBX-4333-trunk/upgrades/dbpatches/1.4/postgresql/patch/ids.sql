CREATE TABLE ids (
        nodeid          integer         DEFAULT '0'     NOT NULL,
        table_name	varchar(64)             DEFAULT ''      NOT NULL,
        field_name	varchar(64)             DEFAULT ''      NOT NULL,
        nextid          bigint DEFAULT '0'     NOT NULL,
        PRIMARY KEY (nodeid,table_name,field_name)
) with OIDS;
