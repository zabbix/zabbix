DELETE FROM hosts_profiles WHERE NOT hostid IN (SELECT hostid FROM hosts);
DELETE FROM hosts_profiles_ext WHERE NOT hostid IN (SELECT hostid FROM hosts);

CREATE TABLE host_inventory (
	hostid                   bigint unsigned                           NOT NULL,
	inventory_mode           integer         DEFAULT '0'               NOT NULL,
	type                     varchar(64)     DEFAULT ''                NOT NULL,
	type_full                varchar(64)     DEFAULT ''                NOT NULL,
	name                     varchar(64)     DEFAULT ''                NOT NULL,
	alias                    varchar(64)     DEFAULT ''                NOT NULL,
	os                       varchar(64)     DEFAULT ''                NOT NULL,
	os_full                  varchar(255)    DEFAULT ''                NOT NULL,
	os_short                 varchar(64)     DEFAULT ''                NOT NULL,
	serialno_a               varchar(64)     DEFAULT ''                NOT NULL,
	serialno_b               varchar(64)     DEFAULT ''                NOT NULL,
	tag                      varchar(64)     DEFAULT ''                NOT NULL,
	asset_tag                varchar(64)     DEFAULT ''                NOT NULL,
	macaddress_a             varchar(64)     DEFAULT ''                NOT NULL,
	macaddress_b             varchar(64)     DEFAULT ''                NOT NULL,
	hardware                 varchar(255)    DEFAULT ''                NOT NULL,
	hardware_full            text                                      NOT NULL,
	software                 varchar(255)    DEFAULT ''                NOT NULL,
	software_full            text                                      NOT NULL,
	software_app_a           varchar(64)     DEFAULT ''                NOT NULL,
	software_app_b           varchar(64)     DEFAULT ''                NOT NULL,
	software_app_c           varchar(64)     DEFAULT ''                NOT NULL,
	software_app_d           varchar(64)     DEFAULT ''                NOT NULL,
	software_app_e           varchar(64)     DEFAULT ''                NOT NULL,
	contact                  text                                      NOT NULL,
	location                 text                                      NOT NULL,
	location_lat             varchar(16)     DEFAULT ''                NOT NULL,
	location_lon             varchar(16)     DEFAULT ''                NOT NULL,
	notes                    text                                      NOT NULL,
	chassis                  varchar(64)     DEFAULT ''                NOT NULL,
	model                    varchar(64)     DEFAULT ''                NOT NULL,
	hw_arch                  varchar(32)     DEFAULT ''                NOT NULL,
	vendor                   varchar(64)     DEFAULT ''                NOT NULL,
	contract_number          varchar(64)     DEFAULT ''                NOT NULL,
	installer_name           varchar(64)     DEFAULT ''                NOT NULL,
	deployment_status        varchar(64)     DEFAULT ''                NOT NULL,
	url_a                    varchar(255)    DEFAULT ''                NOT NULL,
	url_b                    varchar(255)    DEFAULT ''                NOT NULL,
	url_c                    varchar(255)    DEFAULT ''                NOT NULL,
	host_networks            text                                      NOT NULL,
	host_netmask             varchar(39)     DEFAULT ''                NOT NULL,
	host_router              varchar(39)     DEFAULT ''                NOT NULL,
	oob_ip                   varchar(39)     DEFAULT ''                NOT NULL,
	oob_netmask              varchar(39)     DEFAULT ''                NOT NULL,
	oob_router               varchar(39)     DEFAULT ''                NOT NULL,
	date_hw_purchase         varchar(64)     DEFAULT ''                NOT NULL,
	date_hw_install          varchar(64)     DEFAULT ''                NOT NULL,
	date_hw_expiry           varchar(64)     DEFAULT ''                NOT NULL,
	date_hw_decomm           varchar(64)     DEFAULT ''                NOT NULL,
	site_address_a           varchar(128)    DEFAULT ''                NOT NULL,
	site_address_b           varchar(128)    DEFAULT ''                NOT NULL,
	site_address_c           varchar(128)    DEFAULT ''                NOT NULL,
	site_city                varchar(128)    DEFAULT ''                NOT NULL,
	site_state               varchar(64)     DEFAULT ''                NOT NULL,
	site_country             varchar(64)     DEFAULT ''                NOT NULL,
	site_zip                 varchar(64)     DEFAULT ''                NOT NULL,
	site_rack                varchar(128)    DEFAULT ''                NOT NULL,
	site_notes               text                                      NOT NULL,
	poc_1_name               varchar(128)    DEFAULT ''                NOT NULL,
	poc_1_email              varchar(128)    DEFAULT ''                NOT NULL,
	poc_1_phone_a            varchar(64)     DEFAULT ''                NOT NULL,
	poc_1_phone_b            varchar(64)     DEFAULT ''                NOT NULL,
	poc_1_cell               varchar(64)     DEFAULT ''                NOT NULL,
	poc_1_screen             varchar(64)     DEFAULT ''                NOT NULL,
	poc_1_notes              text                                      NOT NULL,
	poc_2_name               varchar(128)    DEFAULT ''                NOT NULL,
	poc_2_email              varchar(128)    DEFAULT ''                NOT NULL,
	poc_2_phone_a            varchar(64)     DEFAULT ''                NOT NULL,
	poc_2_phone_b            varchar(64)     DEFAULT ''                NOT NULL,
	poc_2_cell               varchar(64)     DEFAULT ''                NOT NULL,
	poc_2_screen             varchar(64)     DEFAULT ''                NOT NULL,
	poc_2_notes              text                                      NOT NULL,
	PRIMARY KEY (hostid)
) ENGINE=InnoDB;
ALTER TABLE host_inventory ADD CONSTRAINT c_host_inventory_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;

-- create temporary t_host_inventory table
CREATE TABLE t_host_inventory (
	hostid                   bigint unsigned,
	inventory_mode           integer,
	type                     varchar(64),
	type_full                varchar(64),
	name                     varchar(64),
	alias                    varchar(64),
	os                       varchar(64),
	os_full                  varchar(255),
	os_short                 varchar(64),
	serialno_a               varchar(64),
	serialno_b               varchar(64),
	tag                      varchar(64),
	asset_tag                varchar(64),
	macaddress_a             varchar(64),
	macaddress_b             varchar(64),
	hardware                 varchar(255),
	hardware_full            text,
	software                 varchar(255),
	software_full            text,
	software_app_a           varchar(64),
	software_app_b           varchar(64),
	software_app_c           varchar(64),
	software_app_d           varchar(64),
	software_app_e           varchar(64),
	contact                  text,
	location                 text,
	location_lat             varchar(16),
	location_lon             varchar(16),
	notes                    text,
	chassis                  varchar(64),
	model                    varchar(64),
	hw_arch                  varchar(32),
	vendor                   varchar(64),
	contract_number          varchar(64),
	installer_name           varchar(64),
	deployment_status        varchar(64),
	url_a                    varchar(255),
	url_b                    varchar(255),
	url_c                    varchar(255),
	host_networks            text,
	host_netmask             varchar(39),
	host_router              varchar(39),
	oob_ip                   varchar(39),
	oob_netmask              varchar(39),
	oob_router               varchar(39),
	date_hw_purchase         varchar(64),
	date_hw_install          varchar(64),
	date_hw_expiry           varchar(64),
	date_hw_decomm           varchar(64),
	site_address_a           varchar(128),
	site_address_b           varchar(128),
	site_address_c           varchar(128),
	site_city                varchar(128),
	site_state               varchar(64),
	site_country             varchar(64),
	site_zip                 varchar(64),
	site_rack                varchar(128),
	site_notes               text,
	poc_1_name               varchar(128),
	poc_1_email              varchar(128),
	poc_1_phone_a            varchar(64),
	poc_1_phone_b            varchar(64),
	poc_1_cell               varchar(64),
	poc_1_screen             varchar(64),
	poc_1_notes              text,
	poc_2_name               varchar(128),
	poc_2_email              varchar(128),
	poc_2_phone_a            varchar(64),
	poc_2_phone_b            varchar(64),
	poc_2_cell               varchar(64),
	poc_2_screen             varchar(64),
	poc_2_notes              text,
	notes_ext                text
);

-- select all inventories into temporary table
INSERT INTO t_host_inventory
	SELECT p.hostid,0,p.devicetype,ep.device_type,p.name,ep.device_alias,p.os,ep.device_os,ep.device_os_short,
		p.serialno,ep.device_serial,p.tag,ep.device_tag,p.macaddress,ep.ip_macaddress,ep.device_hardware,
		p.hardware,ep.device_software,p.software,ep.device_app_01,ep.device_app_02,ep.device_app_03,
		ep.device_app_04,ep.device_app_05,p.contact,p.location,'','',p.notes,ep.device_chassis,ep.device_model,
		ep.device_hw_arch,ep.device_vendor,ep.device_contract,ep.device_who,ep.device_status,ep.device_url_1,
		ep.device_url_2,ep.device_url_3,ep.device_networks,ep.ip_subnet_mask,ep.ip_router,ep.oob_ip,
		ep.oob_subnet_mask,ep.oob_router,ep.date_hw_buy,ep.date_hw_install,ep.date_hw_expiry,ep.date_hw_decomm,
		ep.site_street_1,ep.site_street_2,ep.site_street_3,ep.site_city,ep.site_state,ep.site_country,
		ep.site_zip,ep.site_rack,ep.site_notes,ep.poc_1_name,ep.poc_1_email,ep.poc_1_phone_1,ep.poc_1_phone_2,
		ep.poc_1_cell,ep.poc_1_screen,ep.poc_1_notes,ep.poc_2_name,ep.poc_2_email,ep.poc_2_phone_1,
		ep.poc_2_phone_2,ep.poc_2_cell,ep.poc_2_screen,ep.poc_2_notes,ep.device_notes
	FROM hosts_profiles p LEFT JOIN hosts_profiles_ext ep on p.hostid=ep.hostid
	UNION ALL
	SELECT ep.hostid,0,p.devicetype,ep.device_type,p.name,ep.device_alias,p.os,ep.device_os,ep.device_os_short,
		p.serialno,ep.device_serial,p.tag,ep.device_tag,p.macaddress,ep.ip_macaddress,ep.device_hardware,
		p.hardware,ep.device_software,p.software,ep.device_app_01,ep.device_app_02,ep.device_app_03,
		ep.device_app_04,ep.device_app_05,p.contact,p.location,'','',p.notes,ep.device_chassis,ep.device_model,
		ep.device_hw_arch,ep.device_vendor,ep.device_contract,ep.device_who,ep.device_status,ep.device_url_1,
		ep.device_url_2,ep.device_url_3,ep.device_networks,ep.ip_subnet_mask,ep.ip_router,ep.oob_ip,
		ep.oob_subnet_mask,ep.oob_router,ep.date_hw_buy,ep.date_hw_install,ep.date_hw_expiry,ep.date_hw_decomm,
		ep.site_street_1,ep.site_street_2,ep.site_street_3,ep.site_city,ep.site_state,ep.site_country,
		ep.site_zip,ep.site_rack,ep.site_notes,ep.poc_1_name,ep.poc_1_email,ep.poc_1_phone_1,ep.poc_1_phone_2,
		ep.poc_1_cell,ep.poc_1_screen,ep.poc_1_notes,ep.poc_2_name,ep.poc_2_email,ep.poc_2_phone_1,
		ep.poc_2_phone_2,ep.poc_2_cell,ep.poc_2_screen,ep.poc_2_notes,ep.device_notes
	FROM hosts_profiles p RIGHT JOIN hosts_profiles_ext ep on p.hostid=ep.hostid
	WHERE p.hostid IS NULL;

UPDATE t_host_inventory SET type='' WHERE type IS NULL;
UPDATE t_host_inventory SET type_full='' WHERE type_full IS NULL;
UPDATE t_host_inventory SET name='' WHERE name IS NULL;
UPDATE t_host_inventory SET alias='' WHERE alias IS NULL;
UPDATE t_host_inventory SET os='' WHERE os IS NULL;
UPDATE t_host_inventory SET os_full='' WHERE os_full IS NULL;
UPDATE t_host_inventory SET os_short='' WHERE os_short IS NULL;
UPDATE t_host_inventory SET serialno_a='' WHERE serialno_a IS NULL;
UPDATE t_host_inventory SET serialno_b='' WHERE serialno_b IS NULL;
UPDATE t_host_inventory SET tag='' WHERE tag IS NULL;
UPDATE t_host_inventory SET asset_tag='' WHERE asset_tag IS NULL;
UPDATE t_host_inventory SET macaddress_a='' WHERE macaddress_a IS NULL;
UPDATE t_host_inventory SET macaddress_b='' WHERE macaddress_b IS NULL;
UPDATE t_host_inventory SET hardware='' WHERE hardware IS NULL;
UPDATE t_host_inventory SET hardware_full='' WHERE hardware_full IS NULL;
UPDATE t_host_inventory SET software='' WHERE software IS NULL;
UPDATE t_host_inventory SET software_full='' WHERE software_full IS NULL;
UPDATE t_host_inventory SET software_app_a='' WHERE software_app_a IS NULL;
UPDATE t_host_inventory SET software_app_b='' WHERE software_app_b IS NULL;
UPDATE t_host_inventory SET software_app_c='' WHERE software_app_c IS NULL;
UPDATE t_host_inventory SET software_app_d='' WHERE software_app_d IS NULL;
UPDATE t_host_inventory SET software_app_e='' WHERE software_app_e IS NULL;
UPDATE t_host_inventory SET contact='' WHERE contact IS NULL;
UPDATE t_host_inventory SET location='' WHERE location IS NULL;
UPDATE t_host_inventory SET location_lat='' WHERE location_lat IS NULL;
UPDATE t_host_inventory SET location_lon='' WHERE location_lon IS NULL;
UPDATE t_host_inventory SET notes='' WHERE notes IS NULL;
UPDATE t_host_inventory SET chassis='' WHERE chassis IS NULL;
UPDATE t_host_inventory SET model='' WHERE model IS NULL;
UPDATE t_host_inventory SET hw_arch='' WHERE hw_arch IS NULL;
UPDATE t_host_inventory SET vendor='' WHERE vendor IS NULL;
UPDATE t_host_inventory SET contract_number='' WHERE contract_number IS NULL;
UPDATE t_host_inventory SET installer_name='' WHERE installer_name IS NULL;
UPDATE t_host_inventory SET deployment_status='' WHERE deployment_status IS NULL;
UPDATE t_host_inventory SET url_a='' WHERE url_a IS NULL;
UPDATE t_host_inventory SET url_b='' WHERE url_b IS NULL;
UPDATE t_host_inventory SET url_c='' WHERE url_c IS NULL;
UPDATE t_host_inventory SET host_networks='' WHERE host_networks IS NULL;
UPDATE t_host_inventory SET host_netmask='' WHERE host_netmask IS NULL;
UPDATE t_host_inventory SET host_router='' WHERE host_router IS NULL;
UPDATE t_host_inventory SET oob_ip='' WHERE oob_ip IS NULL;
UPDATE t_host_inventory SET oob_netmask='' WHERE oob_netmask IS NULL;
UPDATE t_host_inventory SET oob_router='' WHERE oob_router IS NULL;
UPDATE t_host_inventory SET date_hw_purchase='' WHERE date_hw_purchase IS NULL;
UPDATE t_host_inventory SET date_hw_install='' WHERE date_hw_install IS NULL;
UPDATE t_host_inventory SET date_hw_expiry='' WHERE date_hw_expiry IS NULL;
UPDATE t_host_inventory SET date_hw_decomm='' WHERE date_hw_decomm IS NULL;
UPDATE t_host_inventory SET site_address_a='' WHERE site_address_a IS NULL;
UPDATE t_host_inventory SET site_address_b='' WHERE site_address_b IS NULL;
UPDATE t_host_inventory SET site_address_c='' WHERE site_address_c IS NULL;
UPDATE t_host_inventory SET site_city='' WHERE site_city IS NULL;
UPDATE t_host_inventory SET site_state='' WHERE site_state IS NULL;
UPDATE t_host_inventory SET site_country='' WHERE site_country IS NULL;
UPDATE t_host_inventory SET site_zip='' WHERE site_zip IS NULL;
UPDATE t_host_inventory SET site_rack='' WHERE site_rack IS NULL;
UPDATE t_host_inventory SET site_notes='' WHERE site_notes IS NULL;
UPDATE t_host_inventory SET poc_1_name='' WHERE poc_1_name IS NULL;
UPDATE t_host_inventory SET poc_1_email='' WHERE poc_1_email IS NULL;
UPDATE t_host_inventory SET poc_1_phone_a='' WHERE poc_1_phone_a IS NULL;
UPDATE t_host_inventory SET poc_1_phone_b='' WHERE poc_1_phone_b IS NULL;
UPDATE t_host_inventory SET poc_1_cell='' WHERE poc_1_cell IS NULL;
UPDATE t_host_inventory SET poc_1_screen='' WHERE poc_1_screen IS NULL;
UPDATE t_host_inventory SET poc_1_notes='' WHERE poc_1_notes IS NULL;
UPDATE t_host_inventory SET poc_2_name='' WHERE poc_2_name IS NULL;
UPDATE t_host_inventory SET poc_2_email='' WHERE poc_2_email IS NULL;
UPDATE t_host_inventory SET poc_2_phone_a='' WHERE poc_2_phone_a IS NULL;
UPDATE t_host_inventory SET poc_2_phone_b='' WHERE poc_2_phone_b IS NULL;
UPDATE t_host_inventory SET poc_2_cell='' WHERE poc_2_cell IS NULL;
UPDATE t_host_inventory SET poc_2_screen='' WHERE poc_2_screen IS NULL;
UPDATE t_host_inventory SET poc_2_notes='' WHERE poc_2_notes IS NULL;

-- merge notes field
UPDATE t_host_inventory SET notes_ext='' WHERE notes_ext IS NULL;
UPDATE t_host_inventory SET notes=CONCAT(notes, '\r\n', notes_ext) WHERE notes<>'' AND notes_ext<>'';
UPDATE t_host_inventory SET notes=notes_ext WHERE notes='';
ALTER TABLE t_host_inventory DROP COLUMN notes_ext;

-- copy data from temporary table
INSERT INTO host_inventory SELECT * FROM t_host_inventory;

DROP TABLE t_host_inventory;
DROP TABLE hosts_profiles;
DROP TABLE hosts_profiles_ext;

DELETE FROM ids WHERE table_name IN ('hosts_profiles', 'hosts_profiles_ext');
