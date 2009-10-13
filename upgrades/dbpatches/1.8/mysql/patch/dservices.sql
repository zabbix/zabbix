alter table dservices add dcheckid                bigint unsigned         DEFAULT '0'     NOT NULL;

CREATE UNIQUE INDEX dservices_1 on dservices (dcheckid,type,key_,ip,port);
CREATE INDEX dservices_2 on dservices (dhostid);
