CREATE TABLE regexps (
        regexpid                number(20)              DEFAULT '0'     NOT NULL,
        name            nvarchar2(128)          DEFAULT ''      ,
        test_string             nvarchar2(2048)         DEFAULT ''      ,
        PRIMARY KEY (regexpid)
);
CREATE INDEX regexps_1 on regexps (name);

