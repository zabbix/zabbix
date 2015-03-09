alter table auditlog add ip              varchar(39)             DEFAULT ''      NOT NULL;
alter table auditlog add resourceid              bigint unsigned         DEFAULT '0'     NOT NULL;
alter table auditlog add resourcename            varchar(255)            DEFAULT ''      NOT NULL;
