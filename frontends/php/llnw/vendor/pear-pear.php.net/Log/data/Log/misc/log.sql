-- $Id: log.sql 215378 2006-06-26 15:25:35Z jon $

CREATE TABLE log_table (
    id          INT NOT NULL,
    logtime     TIMESTAMP NOT NULL,
    ident       CHAR(16) NOT NULL,
    priority    INT NOT NULL,
    message     VARCHAR(200),
    PRIMARY KEY (id)
);
