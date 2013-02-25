CREATE TABLE regexps (
      regexpid                bigint unsigned         DEFAULT '0'     NOT NULL,
      name            varchar(128)            DEFAULT ''      NOT NULL,
      test_string             blob                    NOT NULL,
      PRIMARY KEY (regexpid)
) ENGINE=InnoDB;
CREATE INDEX regexps_1 on regexps (name);
