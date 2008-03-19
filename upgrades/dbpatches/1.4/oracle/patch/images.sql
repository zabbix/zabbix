CREATE TABLE images (
        imageid         number(20)              DEFAULT '0'     NOT NULL,
        imagetype               number(10)              DEFAULT '0'     NOT NULL,
        name            varchar2(64)            DEFAULT '0'     ,
        image           blob            DEFAULT ''      NOT NULL,
        PRIMARY KEY (imageid)
);
CREATE INDEX images_1 on images (imagetype,name);

insert into images_tmp select * from images;
drop table images;
alter table images_tmp rename images;
