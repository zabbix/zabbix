CREATE TABLE auditlog_details (
        auditdetailid           bigint          DEFAULT '0'     NOT NULL,
        auditid         bigint          DEFAULT '0'     NOT NULL,
        table_name              varchar(64)             DEFAULT ''      NOT NULL,
        field_name              varchar(64)             DEFAULT ''      NOT NULL,
        oldvalue                text            DEFAULT ''      NOT NULL,
        newvalue                text            DEFAULT ''      NOT NULL,
        PRIMARY KEY (auditdetailid)
) with OIDS;
CREATE INDEX auditlog_details_1 on auditlog_details (auditid);
