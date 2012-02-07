CREATE TABLE hostmacro (
      hostmacroid             bigint unsigned         DEFAULT '0'     NOT NULL,
      hostid          bigint unsigned         DEFAULT '0'     NOT NULL,
      macro           varchar(64)             DEFAULT ''      NOT NULL,
      value           varchar(255)            DEFAULT ''      NOT NULL,
      PRIMARY KEY (hostmacroid)
) ENGINE=InnoDB;
CREATE INDEX hostmacro_1 on hostmacro (hostid,macro);
