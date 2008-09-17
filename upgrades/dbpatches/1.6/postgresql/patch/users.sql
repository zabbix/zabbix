CREATE TABLE users_tmp (
        userid          bigint          DEFAULT '0'     NOT NULL,
        alias           varchar(100)            DEFAULT ''      NOT NULL,
        name            varchar(100)            DEFAULT ''      NOT NULL,
        surname         varchar(100)            DEFAULT ''      NOT NULL,
        passwd          char(32)                DEFAULT ''      NOT NULL,
        url             varchar(255)            DEFAULT ''      NOT NULL,
        autologin               integer         DEFAULT '0'     NOT NULL,
        autologout              integer         DEFAULT '900'   NOT NULL,
        lang            varchar(5)              DEFAULT 'en_gb' NOT NULL,
        refresh         integer         DEFAULT '30'    NOT NULL,
        type            integer         DEFAULT '0'     NOT NULL,
        theme           varchar(128)            DEFAULT 'default.css'   NOT NULL,
        attempt_failed          integer         DEFAULT 0       NOT NULL,
        attempt_ip              varchar(39)             DEFAULT ''      NOT NULL,
        attempt_clock           integer         DEFAULT 0       NOT NULL,
        PRIMARY KEY (userid)
) with OIDS;

insert into users_tmp select userid,alias,name,surname,passwd,url,0,autologout,lang,refresh,type from users;
drop table users;
alter table users_tmp rename to users;

CREATE INDEX users_1 on users (alias);
