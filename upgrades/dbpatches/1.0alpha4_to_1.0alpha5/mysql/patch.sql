alter table items add type int(4) DEFAULT '0' NOT NULL;
alter table items add snmp_community varchar(64) DEFAULT '' NOT NULL;
alter table items add snmp_oid varchar(255) DEFAULT '' NOT NULL;
