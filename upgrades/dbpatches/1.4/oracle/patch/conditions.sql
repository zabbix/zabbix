CREATE TABLE conditions_tmp (
        conditionid             number(20)              DEFAULT '0'     NOT NULL,
        actionid                number(20)              DEFAULT '0'     NOT NULL,
        conditiontype           number(10)              DEFAULT '0'     NOT NULL,
        operator                number(10)              DEFAULT '0'     NOT NULL,
        value           varchar2(255)           DEFAULT ''      ,
        PRIMARY KEY (conditionid)
);
CREATE INDEX conditions_1 on conditions_tmp (actionid);

insert into conditions_tmp select * from conditions;
drop table conditions;
alter table conditions_tmp rename conditions;
