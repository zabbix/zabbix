CREATE TABLE slides (
        slideid         bigint unsigned         DEFAULT '0'     NOT NULL,
        slideshowid             bigint unsigned         DEFAULT '0'     NOT NULL,
        screenid             bigint unsigned         DEFAULT '0'     NOT NULL,
        step            integer         DEFAULT '0'     NOT NULL,
        delay           integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (slideid)
) ENGINE=InnoDB;
CREATE INDEX slides_slides_1 on slides (slideshowid);

