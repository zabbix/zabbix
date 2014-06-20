DROP TABLE IF EXISTS escalations;
CREATE TABLE escalations (
        escalationid            bigint          DEFAULT '0'     NOT NULL,
        actionid                bigint          DEFAULT '0'     NOT NULL,
        triggerid               bigint          DEFAULT '0'     NOT NULL,
        eventid         bigint          DEFAULT '0'     NOT NULL,
        r_eventid               bigint          DEFAULT '0'     NOT NULL,
        nextcheck               integer         DEFAULT '0'     NOT NULL,
        esc_step                integer         DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (escalationid)
) with OIDS;
CREATE INDEX escalations_1 on escalations (actionid,triggerid);
CREATE INDEX escalations_2 on escalations (status,nextcheck);
