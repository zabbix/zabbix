CREATE TABLE user_history (
        userhistoryid           bigint          DEFAULT '0'     NOT NULL,
        userid          bigint          DEFAULT '0'     NOT NULL,
        title1          varchar(255)            DEFAULT ''      NOT NULL,
        url1            varchar(255)            DEFAULT ''      NOT NULL,
        title2          varchar(255)            DEFAULT ''      NOT NULL,
        url2            varchar(255)            DEFAULT ''      NOT NULL,
        title3          varchar(255)            DEFAULT ''      NOT NULL,
        url3            varchar(255)            DEFAULT ''      NOT NULL,
        title4          varchar(255)            DEFAULT ''      NOT NULL,
        url4            varchar(255)            DEFAULT ''      NOT NULL,
        title5          varchar(255)            DEFAULT ''      NOT NULL,
        url5            varchar(255)            DEFAULT ''      NOT NULL,
        PRIMARY KEY (userhistoryid)
) with OIDS;
CREATE UNIQUE INDEX user_history_1 on user_history (userid);
