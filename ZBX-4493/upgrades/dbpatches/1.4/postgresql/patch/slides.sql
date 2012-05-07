CREATE TABLE slides (
        slideid         bigint	DEFAULT '0'     NOT NULL,
        slideshowid     bigint 	DEFAULT '0'     NOT NULL,
        screenid        bigint 	DEFAULT '0'     NOT NULL,
        step            integer         DEFAULT '0'     NOT NULL,
        delay           integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (slideid)
) with OIDS;
CREATE INDEX slides_slides_1 on slides (slideshowid);

