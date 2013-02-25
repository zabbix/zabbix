CREATE TABLE hosts_groups_tmp (
	hostgroupid             number(20)              DEFAULT '0'     NOT NULL,
	hostid          number(20)              DEFAULT '0'     NOT NULL,
	groupid         number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (hostgroupid)
);
CREATE INDEX hosts_groups_groups_1 on hosts_groups_tmp (hostid,groupid);

create sequence hosts_groups_tmp_hostgroupid
start with 1
increment by 1
nomaxvalue;

create trigger hosts_groups_tmp_trigger
before insert on hosts_groups_tmp
for each row
begin
	select hosts_groups_tmp_hostgroupid.nextval into :new.hostgroupid from dual;
end;
/

insert into hosts_groups_tmp (hostid,groupid) select hostid,groupid from hosts_groups;
drop trigger hosts_groups_tmp_trigger;
drop sequence hosts_groups_tmp_hostgroupid;
drop table hosts_groups;
alter table hosts_groups_tmp rename to hosts_groups;
