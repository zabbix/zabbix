alter table config add  event_ack_enable int(11) NOT NULL default '1';
alter table config add  event_expire int(11) NOT NULL default '7';
alter table config add  event_show_max int(11) NOT NULL default '100';
alter table config add  default_theme varchar(128) NOT NULL default 'default.css';


alter table config add authentication_type             integer         DEFAULT 0       NOT NULL;
alter table config add ldap_host               varchar(255)            DEFAULT ''      NOT NULL;
alter table config add ldap_port               integer         DEFAULT 389     NOT NULL;
alter table config add ldap_base_dn            varchar(255)            DEFAULT ''      NOT NULL;
alter table config add ldap_bind_dn            varchar(255)            DEFAULT ''      NOT NULL;
alter table config add ldap_bind_password              varchar(128)            DEFAULT ''      NOT NULL;
alter table config add ldap_search_attribute           varchar(128)            DEFAULT ''      NOT NULL;
