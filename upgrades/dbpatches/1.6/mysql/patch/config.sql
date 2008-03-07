alter table config add  event_ack_enable int(11) NOT NULL default '1';
alter table config add  event_expire int(11) NOT NULL default '7';
alter table config add  event_show_max int(11) NOT NULL default '100';
alter table config add  default_theme varchar(128) NOT NULL default 'default.css';
