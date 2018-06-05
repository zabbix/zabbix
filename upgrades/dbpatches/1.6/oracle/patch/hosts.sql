CREATE TABLE hosts_tmp (
	hostid		number(20)		DEFAULT '0'	NOT NULL,
	proxy_hostid		number(20)		DEFAULT '0'	NOT NULL,
	host		varchar2(64)		DEFAULT ''	,
	dns		varchar2(64)		DEFAULT ''	,
	useip		number(10)		DEFAULT '1'	NOT NULL,
	ip		varchar2(39)		DEFAULT '127.0.0.1'	,
	port		number(10)		DEFAULT '10050'	NOT NULL,
	status		number(10)		DEFAULT '0'	NOT NULL,
	disable_until		number(10)		DEFAULT '0'	NOT NULL,
	error		varchar2(128)		DEFAULT ''	,
	available		number(10)		DEFAULT '0'	NOT NULL,
	errors_from		number(10)		DEFAULT '0'	NOT NULL,
	lastaccess		number(10)		DEFAULT '0'	NOT NULL,
	inbytes		number(20)		DEFAULT '0'	NOT NULL,
	outbytes		number(20)		DEFAULT '0'	NOT NULL,
	useipmi		number(10)		DEFAULT '0'	NOT NULL,
	ipmi_port		number(10)		DEFAULT '623'	NOT NULL,
	ipmi_authtype		number(10)		DEFAULT '0'	NOT NULL,
	ipmi_privilege		number(10)		DEFAULT '2'	NOT NULL,
	ipmi_username		varchar2(16)		DEFAULT ''	,
	ipmi_password		varchar2(20)		DEFAULT ''	,
	PRIMARY KEY (hostid)
);
insert into hosts_tmp select hostid,0,host,dns,useip,ip,port,status,disable_until,error,available,errors_from,0,0,0,0,623,0,2,'','' from hosts;
drop table hosts;
alter table hosts_tmp rename to hosts;
CREATE INDEX hosts_1 on hosts (host);
CREATE INDEX hosts_2 on hosts (status);
CREATE INDEX hosts_3 on hosts (proxy_hostid);
