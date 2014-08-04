CREATE TABLE expressions (
        expressionid            number(20)              DEFAULT '0'     NOT NULL,
        regexpid                number(20)              DEFAULT '0'     NOT NULL,
        expression              nvarchar2(255)          DEFAULT ''      ,
        expression_type         number(10)              DEFAULT '0'     NOT NULL,
        exp_delimiter           nvarchar2(1)            DEFAULT ''      ,
        case_sensitive          number(10)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (expressionid)
);
CREATE INDEX expressions_1 on expressions (regexpid);

