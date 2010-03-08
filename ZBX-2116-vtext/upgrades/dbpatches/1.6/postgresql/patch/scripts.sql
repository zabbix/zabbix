CREATE TABLE scripts (
        scriptid                bigint          DEFAULT '0'     NOT NULL,
        name            varchar(255)            DEFAULT ''      NOT NULL,
        command         varchar(255)            DEFAULT ''      NOT NULL,
        host_access             integer         DEFAULT '2'     NOT NULL,
        usrgrpid                bigint          DEFAULT '0'     NOT NULL,
        groupid         bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (scriptid)
) with OIDS;
