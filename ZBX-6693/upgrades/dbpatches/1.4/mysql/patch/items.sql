CREATE TABLE items_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	snmp_community		varchar(64)		DEFAULT ''	NOT NULL,
	snmp_oid		varchar(255)		DEFAULT ''	NOT NULL,
	snmp_port		integer		DEFAULT '161'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	description		varchar(255)		DEFAULT ''	NOT NULL,
	key_		varchar(255)		DEFAULT ''	NOT NULL,
	delay		integer		DEFAULT '0'	NOT NULL,
	history		integer		DEFAULT '90'	NOT NULL,
	trends		integer		DEFAULT '365'	NOT NULL,
	nextcheck		integer		DEFAULT '0'	NOT NULL,
	lastvalue		varchar(255)			NULL,
	lastclock		integer			NULL,
	prevvalue		varchar(255)			NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	value_type		integer		DEFAULT '0'	NOT NULL,
	trapper_hosts		varchar(255)		DEFAULT ''	NOT NULL,
	units		varchar(10)		DEFAULT ''	NOT NULL,
	multiplier		integer		DEFAULT '0'	NOT NULL,
	delta		integer		DEFAULT '0'	NOT NULL,
	prevorgvalue		varchar(255)			NULL,
	snmpv3_securityname		varchar(64)		DEFAULT ''	NOT NULL,
	snmpv3_securitylevel		integer		DEFAULT '0'	NOT NULL,
	snmpv3_authpassphrase		varchar(64)		DEFAULT ''	NOT NULL,
	snmpv3_privpassphrase		varchar(64)		DEFAULT ''	NOT NULL,
	formula		varchar(255)		DEFAULT '0'	NOT NULL,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	lastlogsize		integer		DEFAULT '0'	NOT NULL,
	logtimefmt		varchar(64)		DEFAULT ''	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	valuemapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	delay_flex		varchar(255)		DEFAULT ''	NOT NULL,
	params			text		DEFAULT ''	NOT NULL,
	PRIMARY KEY (itemid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX items_1 on items_tmp (hostid,key_);
CREATE INDEX items_2 on items_tmp (nextcheck);
CREATE INDEX items_3 on items_tmp (status);

insert into items_tmp select itemid,type,snmp_community,snmp_oid,snmp_port,hostid,description ,key_,delay,history,trends,nextcheck,lastvalue,lastclock,prevvalue,status,value_type,trapper_hosts,units,multiplier,delta,prevorgvalue,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,'','' from items;
drop table items;
alter table items_tmp rename items;
