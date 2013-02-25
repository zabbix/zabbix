CREATE TABLE user_history (
        userhistoryid           number(20)              DEFAULT '0'     NOT NULL,
        userid          number(20)              DEFAULT '0'     NOT NULL,
        title1          nvarchar2(255)          DEFAULT ''      ,
        url1            nvarchar2(255)          DEFAULT ''      ,
        title2          nvarchar2(255)          DEFAULT ''      ,
        url2            nvarchar2(255)          DEFAULT ''      ,
        title3          nvarchar2(255)          DEFAULT ''      ,
        url3            nvarchar2(255)          DEFAULT ''      ,
        title4          nvarchar2(255)          DEFAULT ''      ,
        url4            nvarchar2(255)          DEFAULT ''      ,
        title5          nvarchar2(255)          DEFAULT ''      ,
        url5            nvarchar2(255)          DEFAULT ''      ,
        PRIMARY KEY (userhistoryid)
);
CREATE UNIQUE INDEX user_history_1 on user_history (userid);

