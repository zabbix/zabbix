CREATE TABLE opconditions (
        opconditionid           bigint          DEFAULT '0'     NOT NULL,
        operationid             bigint          DEFAULT '0'     NOT NULL,
        conditiontype           integer         DEFAULT '0'     NOT NULL,
        operator                integer         DEFAULT '0'     NOT NULL,
        value           varchar(255)            DEFAULT ''      NOT NULL,
        PRIMARY KEY (opconditionid)
) with OIDS;
CREATE INDEX opconditions_1 on opconditions (operationid);
