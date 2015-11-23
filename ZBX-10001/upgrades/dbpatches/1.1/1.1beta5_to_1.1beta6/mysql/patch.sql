alter table sysmaps add  label_location	int(1)		DEFAULT '0' NOT NULL;

alter table hosts drop network_errors;
alter table hosts add errors_from	int(4)		DEFAULT '0' NOT NULL;
