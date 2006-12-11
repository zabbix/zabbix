drop index functions_i_f_p;
drop index functions_items;
create index functions_i_f_p on functions (itemid,function,parameter);
