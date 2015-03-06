alter table users add rows_per_page           number(10)         DEFAULT 50      NOT NULL;

alter table users modify alias           nvarchar2(100)          DEFAULT '';
alter table users modify name            nvarchar2(100)          DEFAULT '';
alter table users modify surname         nvarchar2(100)          DEFAULT '';
alter table users modify passwd          nvarchar2(32)           DEFAULT '';
alter table users modify url             nvarchar2(255)          DEFAULT '';
alter table users modify lang            nvarchar2(5)            DEFAULT 'en_gb';
alter table users modify theme           nvarchar2(128)          DEFAULT 'default.css';
alter table users modify attempt_ip              nvarchar2(39)           DEFAULT '';
