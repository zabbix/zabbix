alter table items drop column nextcheck;
alter table items add data_type  number(10)     DEFAULT '0' NOT NULL;
alter table items add authtype   number(10)     DEFAULT '0' NOT NULL;
alter table items add username   nvarchar2(64) DEFAULT '';
alter table items add password   nvarchar2(64) DEFAULT '';
alter table items add publickey  nvarchar2(64) DEFAULT '';
alter table items add privatekey nvarchar2(64) DEFAULT '';
alter table items add mtime      number(10)     DEFAULT '0' NOT NULL;

alter table items modify snmp_community          nvarchar2(64)           DEFAULT '';
alter table items modify snmp_oid                nvarchar2(255)          DEFAULT '';
alter table items modify description             nvarchar2(255)          DEFAULT '';
alter table items modify key_            nvarchar2(255)          DEFAULT '';
alter table items modify lastvalue               nvarchar2(255);
alter table items modify prevvalue               nvarchar2(255);
alter table items modify trapper_hosts           nvarchar2(255);
alter table items modify units           nvarchar2(10);
alter table items modify prevorgvalue            nvarchar2(255);
alter table items modify snmpv3_securityname             nvarchar2(64);
alter table items modify snmpv3_authpassphrase           nvarchar2(64);
alter table items modify snmpv3_privpassphrase           nvarchar2(64);
alter table items modify formula         nvarchar2(255)          DEFAULT '1';
alter table items modify error           nvarchar2(128)          DEFAULT '';
alter table items modify logtimefmt              nvarchar2(64)           DEFAULT '';
alter table items modify delay_flex              nvarchar2(255)          DEFAULT '';
alter table items modify params          nvarchar2(2048)         DEFAULT '';
alter table items modify ipmi_sensor             nvarchar2(128)          DEFAULT '';

UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
