alter table graphs_items add calc_fnc int(1) DEFAULT '2' NOT NULL;
alter table graphs_items add show_history int(1) DEFAULT '0' NOT NULL;
alter table graphs_items add history_len int(4) DEFAULT '5' NOT NULL;

