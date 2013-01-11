alter table httptest add authentication          number(10)         DEFAULT '0'     NOT NULL;
alter table httptest add http_user               nvarchar2(64)             DEFAULT '';
alter table httptest add http_password           nvarchar2(64)             DEFAULT '';

CREATE INDEX httptest_2 on httptest (name);
CREATE INDEX httptest_3 on httptest (status);

alter table httptest modify name            nvarchar2(64)           DEFAULT '';
alter table httptest modify macros          nvarchar2(2048)         DEFAULT '';
alter table httptest modify agent           nvarchar2(255)          DEFAULT '';
alter table httptest modify error           nvarchar2(255)          DEFAULT '';
