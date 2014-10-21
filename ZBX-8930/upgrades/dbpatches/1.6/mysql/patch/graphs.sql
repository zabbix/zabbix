alter table graphs add show_legend int(11) NOT NULL default '0';
alter table graphs add show_3d int(11) NOT NULL default '0';
alter table graphs add percent_left            double(16,4)            DEFAULT '0'     NOT NULL;
alter table graphs add percent_right           double(16,4)            DEFAULT '0'     NOT NULL;
