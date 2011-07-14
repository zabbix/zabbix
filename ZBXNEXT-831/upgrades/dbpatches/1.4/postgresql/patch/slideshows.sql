CREATE TABLE slideshows (
        slideshowid     bigint DEFAULT '0'     NOT NULL,
        name            varchar(255)            DEFAULT ''      NOT NULL,
        delay           integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (slideshowid)
) with OIDS;
