CREATE TABLE expressions (
        expressionid            bigint          DEFAULT '0'     NOT NULL,
        regexpid                bigint          DEFAULT '0'     NOT NULL,
        expression              varchar(255)            DEFAULT ''      NOT NULL,
        expression_type         integer         DEFAULT '0'     NOT NULL,
        exp_delimiter           varchar(1)              DEFAULT ''      NOT NULL,
        case_sensitive          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (expressionid)
) with OIDS;
CREATE INDEX expressions_1 on expressions (regexpid);
