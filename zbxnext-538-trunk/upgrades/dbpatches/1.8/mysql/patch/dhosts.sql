alter table dservices add dcheckid                bigint unsigned         DEFAULT '0'     NOT NULL;
alter table dservices add ip              varchar(39)             DEFAULT ''      NOT NULL;

update dservices set ip=(select dhosts.ip from dhosts where dservices.dhostid=dhosts.dhostid);

alter table dhosts drop ip;

CREATE INDEX dhosts_1 on dhosts (druleid);
