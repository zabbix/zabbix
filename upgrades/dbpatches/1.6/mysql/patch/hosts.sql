alter table hosts add proxy_hostid bigint unsigned DEFAULT '0' NOT NULL after hostid;
alter table hosts add lastaccess integer DEFAULT '0' NOT NULL;
alter table hosts add inbytes bigint unsigned DEFAULT '0' NOT NULL;
alter table hosts add outbytes bigint unsigned DEFAULT '0' NOT NULL;
alter table hosts modify ip varchar(39) DEFAULT '127.0.0.1' NOT NULL;
CREATE INDEX hosts_3 on hosts (proxy_hostid);
