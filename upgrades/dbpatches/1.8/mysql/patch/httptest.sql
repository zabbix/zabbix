alter table httptest add authentication          integer         DEFAULT '0'     NOT NULL;
alter table httptest add http_user               varchar(64)             DEFAULT ''      NOT NULL;
alter table httptest add http_password           varchar(64)             DEFAULT ''      NOT NULL;

CREATE INDEX httptest_2 on httptest (name);
CREATE INDEX httptest_3 on httptest (status);
