alter table hosts add proxy_hostid bigint unsigned DEFAULT '0' NOT NULL after hostid;
alter table hosts add lastaccess integer DEFAULT '0' NOT NULL;
alter table hosts add inbytes bigint unsigned DEFAULT '0' NOT NULL;
alter table hosts add outbytes bigint unsigned DEFAULT '0' NOT NULL;
alter table hosts modify ip varchar(39) DEFAULT '127.0.0.1' NOT NULL;
alter table hosts add useipmi         integer         DEFAULT '0'     NOT NULL;
alter table hosts add ipmi_port               integer         DEFAULT '623'   NOT NULL;
alter table hosts add ipmi_authtype           integer         DEFAULT '0'     NOT NULL;
alter table hosts add ipmi_privilege          integer         DEFAULT '2'     NOT NULL;
alter table hosts add ipmi_username           varchar(16)             DEFAULT ''      NOT NULL;
alter table hosts add ipmi_password           varchar(20)             DEFAULT ''      NOT NULL;
alter table hosts add ipmi_disable_until              integer         DEFAULT '0'     NOT NULL;
alter table hosts add ipmi_available          integer         DEFAULT '0'     NOT NULL;
alter table hosts add snmp_disable_until              integer         DEFAULT '0'     NOT NULL;
alter table hosts add snmp_available          integer         DEFAULT '0'     NOT NULL;

CREATE INDEX hosts_3 on hosts (proxy_hostid);
