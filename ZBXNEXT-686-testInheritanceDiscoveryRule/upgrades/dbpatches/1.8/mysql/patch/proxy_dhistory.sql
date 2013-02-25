alter table proxy_dhistory change key_ key_ varchar(255) DEFAULT '' NOT NULL;
alter table proxy_dhistory change value value varchar(255) DEFAULT '' NOT NULL;

alter table proxy_dhistory add dcheckid bigint unsigned DEFAULT '0' NOT NULL;
