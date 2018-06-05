alter table graphs add show_legend integer NOT NULL default '0';
alter table graphs add show_3d integer NOT NULL default '0';
alter table graphs add percent_left            numeric(16,4)            DEFAULT '0'     NOT NULL;
alter table graphs add percent_right           numeric(16,4)            DEFAULT '0'     NOT NULL;
