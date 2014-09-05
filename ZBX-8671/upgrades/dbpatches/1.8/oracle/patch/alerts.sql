alter table alerts modify sendto          nvarchar2(100)          DEFAULT '';
alter table alerts modify subject         nvarchar2(255)          DEFAULT '';
alter table alerts modify message         nvarchar2(2048)         DEFAULT '';
alter table alerts modify error           nvarchar2(128)          DEFAULT '';
