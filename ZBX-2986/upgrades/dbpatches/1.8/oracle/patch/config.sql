alter table config add dropdown_first_entry number(10) DEFAULT '1' NOT NULL;
alter table config add dropdown_first_remember number(10) DEFAULT '1' NOT NULL;
alter table config add discovery_groupid number(20) DEFAULT '0' NOT NULL;
alter table config add max_in_table number(10) DEFAULT '50' NOT NULL;
alter table config add search_limit number(10) DEFAULT '1000' NOT NULL;


alter table config modify work_period             nvarchar2(100)          DEFAULT '1-5,00:00-24:00';
alter table config modify default_theme           nvarchar2(128)          DEFAULT 'default.css';
alter table config modify ldap_host               nvarchar2(255)          DEFAULT '';
alter table config modify ldap_base_dn            nvarchar2(255)          DEFAULT '';
alter table config modify ldap_bind_dn            nvarchar2(255)          DEFAULT '';
alter table config modify ldap_bind_password              nvarchar2(128)          DEFAULT '';
alter table config modify ldap_search_attribute           nvarchar2(128)          DEFAULT '';
