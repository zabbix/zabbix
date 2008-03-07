alter table hosts add proxyid bigint unsigned DEFAULT '0' NOT NULL;
alter table hosts modify ip varchar(39) DEFAULT '127.0.0.1' NOT NULL;
CREATE INDEX hosts_3 on hosts (proxyid);
