alter table httpstep modify name            nvarchar2(64)           DEFAULT '';
alter table httpstep modify url             nvarchar2(255)          DEFAULT '';
alter table httpstep modify posts           nvarchar2(2048)         DEFAULT '';
alter table httpstep modify required                nvarchar2(255)          DEFAULT '';
alter table httpstep modify status_codes            nvarchar2(255)          DEFAULT '';
