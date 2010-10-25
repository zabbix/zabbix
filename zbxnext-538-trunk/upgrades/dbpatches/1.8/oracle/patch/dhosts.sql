alter table dservices add dcheckid                number(20)              DEFAULT '0'     NOT NULL;
alter table dservices add ip              nvarchar2(39)           DEFAULT '';

update dservices set ip=(select dhosts.ip from dhosts where dservices.dhostid=dhosts.dhostid);

alter table dhosts drop column ip;

CREATE INDEX dhosts_1 on dhosts (druleid);

