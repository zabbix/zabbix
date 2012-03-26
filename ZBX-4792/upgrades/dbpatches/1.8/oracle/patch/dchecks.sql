alter table dchecks add snmpv3_securityname             nvarchar2(64)           DEFAULT '';
alter table dchecks add snmpv3_securitylevel            number(10)              DEFAULT '0'     NOT NULL;
alter table dchecks add snmpv3_authpassphrase           nvarchar2(64)           DEFAULT '';
alter table dchecks add snmpv3_privpassphrase           nvarchar2(64)           DEFAULT '';

CREATE INDEX dchecks_1 on dchecks (druleid);

alter table dchecks modify key_            nvarchar2(255)          DEFAULT '0';
alter table dchecks modify snmp_community          nvarchar2(255)          DEFAULT '0';
alter table dchecks modify ports           nvarchar2(255)          DEFAULT '0';
