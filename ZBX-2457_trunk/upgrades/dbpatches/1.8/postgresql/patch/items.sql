alter table items drop nextcheck;
alter table items add data_type               integer         DEFAULT '0'     NOT NULL;
alter table items add authtype                integer         DEFAULT '0'     NOT NULL;
alter table items add username                varchar(64)             DEFAULT ''      NOT NULL;
alter table items add password                varchar(64)             DEFAULT ''      NOT NULL;
alter table items add publickey               varchar(64)             DEFAULT ''      NOT NULL;
alter table items add privatekey              varchar(64)             DEFAULT ''      NOT NULL;
alter table items add mtime                   integer         DEFAULT '0'     NOT NULL;

UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
