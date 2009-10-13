alter table proxy_dhistory modify key_            varchar(255)            DEFAULT ''      NOT NULL;
alter table proxy_dhistory modify value           varchar(255)            DEFAULT ''      NOT NULL;

alter table proxy_dhistory add         dcheckid                bigint unsigned         DEFAULT '0'     NOT NULL;
