CREATE TABLE globalmacro (
      globalmacroid           bigint unsigned         DEFAULT '0'     NOT NULL,
      macro           varchar(64)             DEFAULT ''      NOT NULL,
      value           varchar(255)            DEFAULT ''      NOT NULL,
      PRIMARY KEY (globalmacroid)
) ENGINE=InnoDB;
CREATE INDEX globalmacro_1 on globalmacro (macro);
