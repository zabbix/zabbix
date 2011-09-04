ALTER TABLE proxy_autoreg_host ADD listen_ip varchar(39) DEFAULT '' NOT NULL,
			       ADD listen_port integer DEFAULT '0' NOT NULL,
			       ADD listen_dns varchar(64) DEFAULT '' NOT NULL;
