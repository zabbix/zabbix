CREATE TABLE node_cksum_tmp (
        nodeid          number(10)              DEFAULT '0'     NOT NULL,
        tablename               nvarchar2(64)           DEFAULT ''      ,
        recordid                number(20)              DEFAULT '0'     NOT NULL,
        cksumtype               number(10)              DEFAULT '0'     NOT NULL,
        cksum           nclob           DEFAULT ''      ,
        sync            nvarchar2(128)          DEFAULT ''
);

insert into node_cksum_tmp select * from node_cksum;

drop table node_cksum;

alter table node_cksum_tmp rename to node_cksum;

CREATE INDEX node_cksum_cksum_1 on node_cksum (nodeid,tablename,recordid,cksumtype);
