alter table auditlog add ip nvarchar2(39)           DEFAULT '';
alter table auditlog add resourceid              number(20)              DEFAULT '0'     NOT NULL;
alter table auditlog add resourcename            nvarchar2(255)          DEFAULT '';

alter table auditlog modify details         nvarchar2(128)          DEFAULT '0';
