alter table drules add unique_dcheckid number(20) DEFAULT '0' NOT NULL;

alter table drules modify name            nvarchar2(255)          DEFAULT '';
alter table drules modify iprange         nvarchar2(255)          DEFAULT '';
