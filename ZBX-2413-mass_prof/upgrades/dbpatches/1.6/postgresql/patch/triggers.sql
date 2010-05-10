update triggers set comments='' where comments is null;

CREATE TABLE triggers_tmp (
        triggerid               bigint          DEFAULT '0'     NOT NULL,
        expression              varchar(255)            DEFAULT ''      NOT NULL,
        description             varchar(255)            DEFAULT ''      NOT NULL,
        url             varchar(255)            DEFAULT ''      NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        value           integer         DEFAULT '0'     NOT NULL,
        priority                integer         DEFAULT '0'     NOT NULL,
        lastchange              integer         DEFAULT '0'     NOT NULL,
        dep_level               integer         DEFAULT '0'     NOT NULL,
        comments                text            DEFAULT ''      NOT NULL,
        error           varchar(128)            DEFAULT ''      NOT NULL,
        templateid              bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (triggerid)
) with OIDS;

insert into triggers_tmp select * from triggers;
drop table triggers;
alter table triggers_tmp rename to triggers;

alter table triggers add type integer DEFAULT '0' NOT NULL;
CREATE INDEX triggers_1 on triggers (status);
CREATE INDEX triggers_2 on triggers (value);
