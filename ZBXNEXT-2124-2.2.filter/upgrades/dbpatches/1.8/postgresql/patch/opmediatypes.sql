CREATE TABLE opmediatypes (
        opmediatypeid           bigint          DEFAULT '0'     NOT NULL,
        operationid             bigint          DEFAULT '0'     NOT NULL,
        mediatypeid             bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (opmediatypeid)
) with OIDS;
CREATE UNIQUE INDEX opmediatypes_1 on opmediatypes (operationid);
