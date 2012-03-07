update triggers set comments='' where comments is null;
alter table triggers modify comments blob NOT NULL;

alter table triggers add type integer DEFAULT '0' NOT NULL;
