alter table hosts add maintenanceid number(20) DEFAULT '0' NOT NULL;
alter table hosts add maintenance_status number(10) DEFAULT '0' NOT NULL;
alter table hosts add maintenance_type number(10) DEFAULT '0' NOT NULL;
alter table hosts add maintenance_from number(10) DEFAULT '0' NOT NULL;
alter table hosts add ipmi_ip nvarchar2(64) DEFAULT '127.0.0.1';
alter table hosts add ipmi_errors_from number(10) DEFAULT '0' NOT NULL;
alter table hosts add snmp_errors_from number(10) DEFAULT '0' NOT NULL;
alter table hosts add ipmi_error nvarchar2(128) DEFAULT '';
alter table hosts add snmp_error nvarchar2(128) DEFAULT '';

alter table hosts modify host            nvarchar2(64)           DEFAULT '';
alter table hosts modify dns             nvarchar2(64)           DEFAULT '';
alter table hosts modify ip              nvarchar2(39)           DEFAULT '127.0.0.1';
alter table hosts modify error           nvarchar2(128)          DEFAULT '';
alter table hosts modify ipmi_username           nvarchar2(16)           DEFAULT '';
alter table hosts modify ipmi_password           nvarchar2(20)           DEFAULT '';
alter table hosts modify ipmi_ip         nvarchar2(64)           DEFAULT '127.0.0.1';

