ALTER TABLE proxy_autoreg_host ADD listen_ip nvarchar2(39) DEFAULT '';
ALTER TABLE proxy_autoreg_host ADD listen_port number(10) DEFAULT '0' NOT NULL;
ALTER TABLE proxy_autoreg_host ADD listen_dns nvarchar2(64) DEFAULT '';
