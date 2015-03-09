DROP TABLE IF EXISTS escalations;
CREATE TABLE escalations (
        escalationid            bigint unsigned         DEFAULT '0'     NOT NULL,
        actionid                bigint unsigned         DEFAULT '0'     NOT NULL,
        triggerid               bigint unsigned         DEFAULT '0'     NOT NULL,
        eventid         bigint unsigned         DEFAULT '0'     NOT NULL,
        r_eventid               bigint unsigned         DEFAULT '0'     NOT NULL,
        nextcheck               integer         DEFAULT '0'     NOT NULL,
        esc_step                integer         DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (escalationid)
) ENGINE=InnoDB;
CREATE INDEX escalations_1 on escalations (actionid,triggerid);
CREATE INDEX escalations_2 on escalations (status,nextcheck);
