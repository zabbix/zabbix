update items set key_='check_service[ftp]' where key_='net[listen_21]';
update items set key_='check_service[ssh]' where key_='net[listen_22]';
update items set key_='check_service[smtp]' where key_='net[listen_25]';
update items set key_='check_service[pop]' where key_='net[listen_110]';

alter table triggers add dep_level int2 not null default '0';

CREATE TABLE trigger_depends (
  triggerid_down	int4	DEFAULT '0' NOT NULL,
  triggerid_up		int4	DEFAULT '0' NOT NULL,
  PRIMARY KEY		(triggerid_down, triggerid_up)
);

CREATE INDEX trigger_depends_down on trigger_depends (triggerid_down);
CREATE INDEX trigger_depends_up   on trigger_depends (triggerid_up);
