drop table node_cksum;
CREATE TABLE node_cksum (
        nodeid          integer         DEFAULT '0'     NOT NULL,
        tablename               varchar(64)             DEFAULT ''      NOT NULL,
        recordid                bigint          DEFAULT '0'     NOT NULL,
        cksumtype               integer         DEFAULT '0'     NOT NULL,
        cksum           text            DEFAULT ''      NOT NULL,
        sync            char(128)               DEFAULT ''      NOT NULL
) with OIDS;
CREATE INDEX node_cksum_cksum_1 on node_cksum (nodeid,tablename,recordid,cksumtype);
