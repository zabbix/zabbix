alter table dhosts modify ip varchar2(39) default '';
CREATE INDEX dhosts_1 on dhosts (druleid,ip);
