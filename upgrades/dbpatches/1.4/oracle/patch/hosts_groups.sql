CREATE TABLE hosts_groups_tmp (
        hostgroupid             number(20)              DEFAULT '0'     NOT NULL,
        hostid          number(20)              DEFAULT '0'     NOT NULL,
        groupid         number(20)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (hostgroupid)
);
CREATE INDEX hosts_groups_groups_1 on hosts_groups_tmp (hostid,groupid);

insert into hosts_groups_tmp select NULL,hostid,groupid from hosts_groups;
drop table hosts_groups;
alter table hosts_groups_tmp rename hosts_groups;

CREATE TABLE hosts_groups_tmp (
        hostgroupid             number(20)              DEFAULT '0'     NOT NULL,
        hostid          number(20)              DEFAULT '0'     NOT NULL,
        groupid         number(20)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (hostgroupid)
);
CREATE INDEX hosts_groups_groups_1 on hosts_groups_tmp (hostid,groupid);

insert into hosts_groups_tmp select * from hosts_groups;
drop table hosts_groups;
alter table hosts_groups_tmp rename hosts_groups;
