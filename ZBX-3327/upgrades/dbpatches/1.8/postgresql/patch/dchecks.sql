alter table dchecks add snmpv3_securityname             varchar(64)             DEFAULT ''      NOT NULL;
alter table dchecks add snmpv3_securitylevel            integer         DEFAULT '0'     NOT NULL;
alter table dchecks add snmpv3_authpassphrase           varchar(64)             DEFAULT ''      NOT NULL;
alter table dchecks add snmpv3_privpassphrase           varchar(64)             DEFAULT ''      NOT NULL;

CREATE INDEX dchecks_1 on dchecks (druleid);

