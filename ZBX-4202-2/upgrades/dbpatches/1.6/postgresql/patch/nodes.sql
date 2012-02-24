CREATE TABLE nodes_tmp (
        nodeid          integer         DEFAULT '0'     NOT NULL,
        name            varchar(64)             DEFAULT '0'     NOT NULL,
        timezone                integer         DEFAULT '0'     NOT NULL,
        ip              varchar(39)             DEFAULT ''      NOT NULL,
        port            integer         DEFAULT '10051' NOT NULL,
        slave_history           integer         DEFAULT '30'    NOT NULL,
        slave_trends            integer         DEFAULT '365'   NOT NULL,
        nodetype                integer         DEFAULT '0'     NOT NULL,
        masterid                integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (nodeid)
) with OIDS;

insert into nodes_tmp select nodeid,name,timezone,ip,port,slave_history,slave_trends,nodetype,masterid from nodes;
drop table nodes;
alter table nodes_tmp rename to nodes;
