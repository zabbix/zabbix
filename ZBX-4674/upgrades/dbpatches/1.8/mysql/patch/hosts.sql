alter table hosts add maintenanceid bigint unsigned DEFAULT '0' NOT NULL;
alter table hosts add maintenance_status integer DEFAULT '0' NOT NULL;
alter table hosts add maintenance_type integer DEFAULT '0' NOT NULL;
alter table hosts add maintenance_from integer DEFAULT '0' NOT NULL;
alter table hosts add ipmi_ip varchar(64) DEFAULT '127.0.0.1' NOT NULL;
alter table hosts add ipmi_errors_from integer DEFAULT '0' NOT NULL;
alter table hosts add snmp_errors_from integer DEFAULT '0' NOT NULL;
alter table hosts add ipmi_error varchar(128) DEFAULT '' NOT NULL;
alter table hosts add snmp_error varchar(128) DEFAULT '' NOT NULL;
