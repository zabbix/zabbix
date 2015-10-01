alter table groups add internal                number(10)         DEFAULT '0'     NOT NULL;

alter table groups modify name            nvarchar2(64)           DEFAULT '';
