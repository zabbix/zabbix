ALTER TABLE proxy_autoreg_host ADD listen_ip varchar(39) WITH DEFAULT '' NOT NULL
/
REORG TABLE proxy_autoreg_host
/
ALTER TABLE proxy_autoreg_host ADD listen_port integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE proxy_autoreg_host
/
ALTER TABLE proxy_autoreg_host ADD listen_dns varchar(64) WITH DEFAULT '' NOT NULL
/
REORG TABLE proxy_autoreg_host
/
