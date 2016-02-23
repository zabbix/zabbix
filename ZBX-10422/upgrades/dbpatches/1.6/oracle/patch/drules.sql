CREATE TABLE drules_tmp (
	druleid		number(20)		DEFAULT '0'	NOT NULL,
	proxy_hostid		number(20)		DEFAULT '0'	NOT NULL,
	name		varchar2(255)		DEFAULT ''	,
	iprange		varchar2(255)		DEFAULT ''	,
	delay		number(10)		DEFAULT '0'	NOT NULL,
	nextcheck		number(10)		DEFAULT '0'	NOT NULL,
	status		number(10)		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (druleid)
);
insert into drules_tmp select druleid,0,name,iprange,delay,nextcheck,status from drules;
drop table drules;
alter table drules_tmp rename to drules;
