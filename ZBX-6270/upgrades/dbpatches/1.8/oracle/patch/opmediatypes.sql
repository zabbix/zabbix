CREATE TABLE opmediatypes (
        opmediatypeid           number(20)              DEFAULT '0'     NOT NULL,
        operationid             number(20)              DEFAULT '0'     NOT NULL,
        mediatypeid             number(20)              DEFAULT '0'     NOT NULL,
        PRIMARY KEY (opmediatypeid)
);
CREATE UNIQUE INDEX opmediatypes_1 on opmediatypes (operationid);

