-- See also dhosts.sql

CREATE UNIQUE INDEX dservices_1 on dservices (dcheckid,type,key_,ip,port);
CREATE INDEX dservices_2 on dservices (dhostid);

alter table dservices modify key_            nvarchar2(255)          DEFAULT '0';
alter table dservices modify value           nvarchar2(255)          DEFAULT '0';
alter table dservices modify ip              nvarchar2(39)           DEFAULT '';

