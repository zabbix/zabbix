CREATE TABLE auditlog_details (
      auditdetailid           bigint unsigned         DEFAULT '0'     NOT NULL,
      auditid         bigint unsigned         DEFAULT '0'     NOT NULL,
      table_name              varchar(64)             DEFAULT ''      NOT NULL,
      field_name              varchar(64)             DEFAULT ''      NOT NULL,
      oldvalue                blob                    NOT NULL,
      newvalue                blob                    NOT NULL,
      PRIMARY KEY (auditdetailid)
) ENGINE=InnoDB;
CREATE INDEX auditlog_details_1 on auditlog_details (auditid);
