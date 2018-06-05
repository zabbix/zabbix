alter table dhosts modify ip varchar(39) NOT NULL default '';
CREATE INDEX dhosts_1 on dhosts (druleid,ip);
