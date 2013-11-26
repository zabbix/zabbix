CREATE TABLE opmediatypes (
      opmediatypeid           bigint unsigned         DEFAULT '0'     NOT NULL,
      operationid             bigint unsigned         DEFAULT '0'     NOT NULL,
      mediatypeid             bigint unsigned         DEFAULT '0'     NOT NULL,
      PRIMARY KEY (opmediatypeid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX opmediatypes_1 on opmediatypes (operationid);
