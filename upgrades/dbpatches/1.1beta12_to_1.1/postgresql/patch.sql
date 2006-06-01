ALTER TABLE graphs_items ADD calc_fnc int2 DEFAULT '2' NOT NULL;
ALTER TABLE graphs_items ADD show_history int2 DEFAULT '0' NOT NULL;
ALTER TABLE graphs_items ADD history_len int4 DEFAULT '5' NOT NULL;

