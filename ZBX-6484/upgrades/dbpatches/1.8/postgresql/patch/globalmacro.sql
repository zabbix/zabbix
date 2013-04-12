CREATE TABLE globalmacro (
        globalmacroid           bigint          DEFAULT '0'     NOT NULL,
        macro           varchar(64)             DEFAULT ''      NOT NULL,
        value           varchar(255)            DEFAULT ''      NOT NULL,
        PRIMARY KEY (globalmacroid)
) with OIDS;
CREATE INDEX globalmacro_1 on globalmacro (macro);
