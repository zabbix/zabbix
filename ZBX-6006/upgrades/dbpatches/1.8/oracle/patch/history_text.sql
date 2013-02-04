CREATE TABLE history_text_tmp (
        id              number(20)              DEFAULT '0'     NOT NULL,
        itemid          number(20)              DEFAULT '0'     NOT NULL,
        clock           number(10)              DEFAULT '0'     NOT NULL,
        value           nclob           DEFAULT ''      ,
        PRIMARY KEY (id)
);

insert into history_text_tmp select * from history_text;
drop table history_text;

alter table history_text_tmp rename to history_text;

CREATE INDEX history_text_1 on history_text (itemid,clock);
CREATE UNIQUE INDEX history_text_2 on history_text (itemid,id);
