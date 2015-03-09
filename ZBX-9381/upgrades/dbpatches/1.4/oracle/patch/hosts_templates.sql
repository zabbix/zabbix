drop trigger hosts_templates_trigger;
drop sequence hosts_templates_hosttemplateid;
drop table hosts_templates;

CREATE TABLE hosts_templates (
	hosttemplateid          number(20)              DEFAULT '0'     NOT NULL,
	hostid          number(20)              DEFAULT '0'     NOT NULL,
	templateid              number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (hosttemplateid)
);
CREATE UNIQUE INDEX hosts_templates_1 on hosts_templates (hostid,templateid);

insert into hosts_templates select hostid,hostid,templateid from hosts where templateid<>0;

-- hosts.sql

CREATE TABLE hosts_tmp (
	hostid          number(20)              DEFAULT '0'     NOT NULL,
	host            varchar2(64)            DEFAULT ''      ,
	dns             varchar2(64)            DEFAULT ''      ,
	useip           number(10)              DEFAULT '1'     NOT NULL,
	ip              varchar2(15)            DEFAULT '127.0.0.1'     ,
	port            number(10)              DEFAULT '10050' NOT NULL,
	status          number(10)              DEFAULT '0'     NOT NULL,
	disable_until           number(10)              DEFAULT '0'     NOT NULL,
	error           varchar2(128)           DEFAULT ''      ,
	available               number(10)              DEFAULT '0'     NOT NULL,
	errors_from             number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (hostid)
);
CREATE INDEX hosts_1 on hosts_tmp (host);
CREATE INDEX hosts_2 on hosts_tmp (status);

insert into hosts_tmp select hostid,host,host,useip,ip,port,status,disable_until,error,available,errors_from from hosts;
drop trigger hosts_trigger;
drop sequence hosts_hostid;
drop table hosts;
alter table hosts_tmp rename to hosts;
