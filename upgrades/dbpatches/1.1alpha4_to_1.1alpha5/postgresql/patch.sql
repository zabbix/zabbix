CREATE TABLE escalations (
  escalationid		serial		DEFAULT '0' NOT NULL,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationid)
);

CREATE UNIQUE INDEX escalations_name on escalations (name);
