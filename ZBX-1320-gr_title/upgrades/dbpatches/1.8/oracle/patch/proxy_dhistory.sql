alter table proxy_dhistory modify key_            nvarchar2(255)            DEFAULT '';
alter table proxy_dhistory modify value           nvarchar2(255)            DEFAULT '';

alter table proxy_dhistory add         dcheckid                number(20)         DEFAULT '0'     NOT NULL;

alter table proxy_dhistory modify ip            nvarchar2(39)            DEFAULT '';
