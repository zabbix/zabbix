alter table hosts add disable_until int(4) default '0' not null;

alter table triggers add url varchar(255) default '' not null;
