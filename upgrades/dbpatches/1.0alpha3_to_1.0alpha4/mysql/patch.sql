alter table items_template drop platformid;
alter table hosts drop platformid;

drop table platforms;

alter table media add key (userid);
alter table actions add key (triggerid);
alter table triggers add key (istrue);
alter table items add key (nextcheck);
alter table items add key (status);
