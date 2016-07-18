CREATE TABLE auditlog_details (
        auditdetailid           number(20)              DEFAULT '0'     NOT NULL,
        auditid         number(20)              DEFAULT '0'     NOT NULL,
        table_name              nvarchar2(64)           DEFAULT ''      ,
        field_name              nvarchar2(64)           DEFAULT ''      ,
        oldvalue                nvarchar2(2048)         DEFAULT ''      ,
        newvalue                nvarchar2(2048)         DEFAULT ''      ,
        PRIMARY KEY (auditdetailid)
);
CREATE INDEX auditlog_details_1 on auditlog_details (auditid);
