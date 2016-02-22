CREATE TABLE applications_tmp (
        applicationid	bigint	DEFAULT '0'     NOT NULL,
        hostid          bigint	DEFAULT '0'     NOT NULL,
        name            varchar(255)            DEFAULT ''      NOT NULL,
        templateid	bigint	DEFAULT '0'     NOT NULL,
        PRIMARY KEY (applicationid)
) with OIDS;

CREATE INDEX applications_1 on applications_tmp (templateid);
CREATE UNIQUE INDEX applications_2 on applications_tmp (hostid,name);

insert into applications_tmp select * from applications;
drop table applications;
alter table applications_tmp rename to applications;
