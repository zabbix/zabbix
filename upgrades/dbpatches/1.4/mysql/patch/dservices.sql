CREATE TABLE dservices (
        dserviceid              bigint unsigned         DEFAULT '0'     NOT NULL,
        dhostid         bigint unsigned         DEFAULT '0'     NOT NULL,
        type            integer         DEFAULT '0'     NOT NULL,
        port            integer         DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        lastup          integer         DEFAULT '0'     NOT NULL,
        lastdown        integer         DEFAULT '0'     NOT NULL,
        eventsent       integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (dserviceid)
) type=InnoDB;
