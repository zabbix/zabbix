alter table usrgrp add api_access              number(10)         DEFAULT '0'     NOT NULL;
alter table usrgrp add debug_mode              number(10)         DEFAULT '0'     NOT NULL;

alter table usrgrp modify name            nvarchar2(64)           DEFAULT '';
