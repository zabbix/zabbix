CREATE TABLE items_tmp (
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	type            number(10)              DEFAULT '0'     NOT NULL,
	snmp_community          varchar2(64)            DEFAULT ''      ,
	snmp_oid                varchar2(255)           DEFAULT ''      ,
	snmp_port               number(10)              DEFAULT '161'   NOT NULL,
	hostid          number(20)              DEFAULT '0'     NOT NULL,
	description             varchar2(255)           DEFAULT ''      ,
	key_            varchar2(255)           DEFAULT ''      ,
	delay           number(10)              DEFAULT '0'     NOT NULL,
	history         number(10)              DEFAULT '90'    NOT NULL,
	trends          number(10)              DEFAULT '365'   NOT NULL,
	nextcheck               number(10)              DEFAULT '0'     NOT NULL,
	lastvalue               varchar2(255)                   ,
	lastclock               number(10)                      NULL,
	prevvalue               varchar2(255)                   ,
	status          number(10)              DEFAULT '0'     NOT NULL,
	value_type              number(10)              DEFAULT '0'     NOT NULL,
	trapper_hosts           varchar2(255)           DEFAULT ''      ,
	units           varchar2(10)            DEFAULT ''      ,
	multiplier              number(10)              DEFAULT '0'     NOT NULL,
	delta           number(10)              DEFAULT '0'     NOT NULL,
	prevorgvalue            varchar2(255)                   ,
	snmpv3_securityname             varchar2(64)            DEFAULT ''      ,
	snmpv3_securitylevel            number(10)              DEFAULT '0'     NOT NULL,
	snmpv3_authpassphrase           varchar2(64)            DEFAULT ''      ,
	snmpv3_privpassphrase           varchar2(64)            DEFAULT ''      ,
	formula         varchar2(255)           DEFAULT '1'     ,
	error           varchar2(128)           DEFAULT ''      ,
	lastlogsize             number(10)              DEFAULT '0'     NOT NULL,
	logtimefmt              varchar2(64)            DEFAULT ''      ,
	templateid              number(20)              DEFAULT '0'     NOT NULL,
	valuemapid              number(20)              DEFAULT '0'     NOT NULL,
	delay_flex              varchar2(255)           DEFAULT ''      ,
	params          varchar2(2048)          DEFAULT ''      ,
	PRIMARY KEY (itemid)
);
CREATE UNIQUE INDEX items_1 on items_tmp (hostid,key_);
CREATE INDEX items_2 on items_tmp (nextcheck);
CREATE INDEX items_3 on items_tmp (status);

insert into items_tmp (itemid,type,snmp_community,snmp_oid,snmp_port,hostid,description,key_,delay,history,trends,nextcheck,lastvalue,lastclock,prevvalue,status,value_type,trapper_hosts,units,multiplier,delta,prevorgvalue,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params) select itemid,type,snmp_community,snmp_oid,snmp_port,hostid,description,key_,delay,history,trends,nextcheck,lastvalue,lastclock,prevvalue,status,value_type,trapper_hosts,units,multiplier,delta,prevorgvalue,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,'','' from items;
drop trigger items_trigger;
drop sequence items_itemid;
drop table items;
alter table items_tmp rename to items;
