CREATE TABLE globalmacro (
        globalmacroid           number(20)              DEFAULT '0'     NOT NULL,
        macro           nvarchar2(64)           DEFAULT ''      ,
        value           nvarchar2(255)          DEFAULT ''      ,
        PRIMARY KEY (globalmacroid)
);
CREATE INDEX globalmacro_1 on globalmacro (macro);
