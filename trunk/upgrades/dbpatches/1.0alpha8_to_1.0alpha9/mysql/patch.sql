alter table functions drop index itemid;
alter table functions drop index itemidfunctionparameter;
alter table functions add index itemidfunctionparameter (itemid,function,parameter);
