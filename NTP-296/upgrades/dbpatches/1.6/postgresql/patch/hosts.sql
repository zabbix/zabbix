CREATE TABLE hosts_tmp (
        hostid          bigint          DEFAULT '0'     NOT NULL,
        proxy_hostid            bigint          DEFAULT '0'     NOT NULL,
        host            varchar(64)             DEFAULT ''      NOT NULL,
        dns             varchar(64)             DEFAULT ''      NOT NULL,
        useip           integer         DEFAULT '1'     NOT NULL,
        ip              varchar(39)             DEFAULT '127.0.0.1'     NOT NULL,
        port            integer         DEFAULT '10050' NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        disable_until           integer         DEFAULT '0'     NOT NULL,
        error           varchar(128)            DEFAULT ''      NOT NULL,
        available               integer         DEFAULT '0'     NOT NULL,
        errors_from             integer         DEFAULT '0'     NOT NULL,
        lastaccess              integer         DEFAULT '0'     NOT NULL,
        inbytes         bigint          DEFAULT '0'     NOT NULL,
        outbytes                bigint          DEFAULT '0'     NOT NULL,
        useipmi         integer         DEFAULT '0'     NOT NULL,
        ipmi_port               integer         DEFAULT '623'   NOT NULL,
        ipmi_authtype           integer         DEFAULT '0'     NOT NULL,
        ipmi_privilege          integer         DEFAULT '2'     NOT NULL,
        ipmi_username           varchar(16)             DEFAULT ''      NOT NULL,
        ipmi_password           varchar(20)             DEFAULT ''      NOT NULL,
        ipmi_disable_until              integer         DEFAULT '0'     NOT NULL,
        ipmi_available          integer         DEFAULT '0'     NOT NULL,
        snmp_disable_until              integer         DEFAULT '0'     NOT NULL,
        snmp_available          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (hostid)
) with OIDS;

insert into hosts_tmp select hostid,0,host,dns,useip,ip,port,status,disable_until,error,available,errors_from from hosts;
drop table hosts;
alter table hosts_tmp rename to hosts;

CREATE INDEX hosts_1 on hosts (host);
CREATE INDEX hosts_2 on hosts (status);
CREATE INDEX hosts_3 on hosts (proxy_hostid);
