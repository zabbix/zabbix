ALTER TABLE proxy_autoreg_host ADD listen_ip varchar(39) WITH DEFAULT '' NOT NULL
/
ALTER TABLE proxy_autoreg_host ADD listen_port integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE proxy_autoreg_host
/
