-- TODO Populate data from sysmaps_links

CREATE TABLE sysmaps_link_triggers (
        linkid          bigint unsigned         DEFAULT '0'     NOT NULL,
        triggerid               bigint unsigned                 ,
        drawtype                integer         DEFAULT '0'     NOT NULL,
        color           varchar(6)              DEFAULT '000000'        NOT NULL,
        PRIMARY KEY (linkid,triggerid)
) type=InnoDB;
CREATE INDEX sysmaps_link_triggers_1 on sysmaps_link_triggers (linkid);
