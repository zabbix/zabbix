CREATE INDEX profiles_2 on profiles (userid,profileid);

alter table profiles modify idx             nvarchar2(96)           DEFAULT '';
alter table profiles modify value_str               nvarchar2(255)          DEFAULT '';
alter table profiles modify source          nvarchar2(96)           DEFAULT '';

