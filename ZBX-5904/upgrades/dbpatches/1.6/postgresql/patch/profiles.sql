drop table profiles;
CREATE TABLE profiles (
        profileid               bigint          DEFAULT '0'     NOT NULL,
        userid          bigint          DEFAULT '0'     NOT NULL,
        idx             varchar(96)             DEFAULT ''      NOT NULL,
        idx2            bigint          DEFAULT '0'     NOT NULL,
        value_id                bigint          DEFAULT '0'     NOT NULL,
        value_int               integer         DEFAULT '0'     NOT NULL,
        value_str               varchar(255)            DEFAULT ''      NOT NULL,
        source          varchar(96)             DEFAULT ''      NOT NULL,
        type            integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (profileid)
) with OIDS;
CREATE INDEX profiles_1 on profiles (userid,idx,idx2);
