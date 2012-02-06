CREATE TABLE hostmacro (
        hostmacroid             number(20)              DEFAULT '0'     NOT NULL,
        hostid          number(20)              DEFAULT '0'     NOT NULL,
        macro           nvarchar2(64)           DEFAULT ''      ,
        value           nvarchar2(255)          DEFAULT ''      ,
        PRIMARY KEY (hostmacroid)
);
CREATE INDEX hostmacro_1 on hostmacro (hostid,macro);

