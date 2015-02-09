CREATE TABLE regexps (
        regexpid                bigint          DEFAULT '0'     NOT NULL,
        name            varchar(128)            DEFAULT ''      NOT NULL,
        test_string             text            DEFAULT ''      NOT NULL,
        PRIMARY KEY (regexpid)
) with OIDS;
CREATE INDEX regexps_1 on regexps (name);
