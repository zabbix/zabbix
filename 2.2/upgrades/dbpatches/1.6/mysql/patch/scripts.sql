CREATE TABLE scripts (
        scriptid                bigint unsigned         DEFAULT '0'     NOT NULL,
        name            varchar(255)            DEFAULT ''      NOT NULL,
        command         varchar(255)            DEFAULT ''      NOT NULL,
        host_access             integer         DEFAULT '2'     NOT NULL,
	usrgrpid                bigint unsigned         DEFAULT '0'     NOT NULL,
	groupid         bigint unsigned         DEFAULT '0'     NOT NULL,

        PRIMARY KEY (scriptid)
) ENGINE=InnoDB;
