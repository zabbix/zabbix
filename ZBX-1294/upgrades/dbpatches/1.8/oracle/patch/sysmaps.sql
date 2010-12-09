ALTER TABLE sysmaps ADD highlight number(10) DEFAULT '1' NOT NULL;

alter table sysmaps modify name            nvarchar2(128)          DEFAULT '';
