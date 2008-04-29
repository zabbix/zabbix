delete from profiles;

alter table profiles drop index profiles_1;

alter table profiles add value2          varchar(255)            DEFAULT ''      NOT NULL;
alter table profiles add resource        varchar(255)            DEFAULT ''      NOT NULL;
CREATE INDEX profiles_1 on profiles (idx);
