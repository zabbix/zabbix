DELETE FROM hosts_profiles WHERE NOT EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=hosts_profiles.hostid);
DELETE FROM hosts_profiles_ext WHERE NOT EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=hosts_profiles_ext.hostid);

CREATE TABLE host_profile (
	hostid			BIGINT NOT NULL,
	type			VARCHAR(64) DEFAULT '' NOT NULL,
	type_full		VARCHAR(64) DEFAULT '' NOT NULL,
	name			VARCHAR(64) DEFAULT '' NOT NULL,
	alias			VARCHAR(64) DEFAULT '' NOT NULL,
	os			VARCHAR(64) DEFAULT '' NOT NULL,
	os_full			VARCHAR(255) DEFAULT '' NOT NULL,
	os_short		VARCHAR(64) DEFAULT '' NOT NULL,
	serialno_a		VARCHAR(64) DEFAULT '' NOT NULL,
	serialno_b		VARCHAR(64) DEFAULT '' NOT NULL,
	tag			VARCHAR(64) DEFAULT '' NOT NULL,
	asset_tag		VARCHAR(64) DEFAULT '' NOT NULL,
	macaddress_a		VARCHAR(64) DEFAULT '' NOT NULL,
	macaddress_b		VARCHAR(64) DEFAULT '' NOT NULL,
	hardware		VARCHAR(255) DEFAULT '' NOT NULL,
	hardware_full		TEXT DEFAULT '' NOT NULL,
	software		VARCHAR(255) DEFAULT '' NOT NULL,
	software_full		TEXT DEFAULT '' NOT NULL,
	software_app_a		VARCHAR(64) DEFAULT '' NOT NULL,
	software_app_b		VARCHAR(64) DEFAULT '' NOT NULL,
	software_app_c		VARCHAR(64) DEFAULT '' NOT NULL,
	software_app_d		VARCHAR(64) DEFAULT '' NOT NULL,
	software_app_e		VARCHAR(64) DEFAULT '' NOT NULL,
	contact			TEXT DEFAULT '' NOT NULL,
	location		TEXT DEFAULT '' NOT NULL,
	location_lat		VARCHAR(16) DEFAULT '' NOT NULL,
	location_lon		VARCHAR(16) DEFAULT '' NOT NULL,
	notes			TEXT DEFAULT '' NOT NULL,
	chassis			VARCHAR(64) DEFAULT '' NOT NULL,
	model			VARCHAR(64) DEFAULT '' NOT NULL,
	hw_arch			VARCHAR(32) DEFAULT '' NOT NULL,
	vendor			VARCHAR(64) DEFAULT '' NOT NULL,
	contract_number		VARCHAR(64) DEFAULT '' NOT NULL,
	installer_name		VARCHAR(64) DEFAULT '' NOT NULL,
	deployment_status	VARCHAR(64) DEFAULT '' NOT NULL,
	url_a			VARCHAR(255) DEFAULT '' NOT NULL,
	url_b			VARCHAR(255) DEFAULT '' NOT NULL,
	url_c			VARCHAR(255) DEFAULT '' NOT NULL,
	host_networks		TEXT DEFAULT '' NOT NULL,
	host_netmask		VARCHAR(39) DEFAULT '' NOT NULL,
	host_router		VARCHAR(39) DEFAULT '' NOT NULL,
	oob_ip			VARCHAR(39) DEFAULT '' NOT NULL,
	oob_netmask		VARCHAR(39) DEFAULT '' NOT NULL,
	oob_router		VARCHAR(39) DEFAULT '' NOT NULL,
	date_hw_purchase	VARCHAR(64) DEFAULT '' NOT NULL,
	date_hw_install		VARCHAR(64) DEFAULT '' NOT NULL,
	date_hw_expiry		VARCHAR(64) DEFAULT '' NOT NULL,
	date_hw_decomm		VARCHAR(64) DEFAULT '' NOT NULL,
	site_address_a		VARCHAR(128) DEFAULT '' NOT NULL,
	site_address_b		VARCHAR(128) DEFAULT '' NOT NULL,
	site_address_c		VARCHAR(128) DEFAULT '' NOT NULL,
	site_city		VARCHAR(128) DEFAULT '' NOT NULL,
	site_state		VARCHAR(64) DEFAULT '' NOT NULL,
	site_country		VARCHAR(64) DEFAULT '' NOT NULL,
	site_zip		VARCHAR(64) DEFAULT '' NOT NULL,
	site_rack		VARCHAR(128) DEFAULT '' NOT NULL,
	site_notes		TEXT DEFAULT '' NOT NULL,
	poc_1_name		VARCHAR(128) DEFAULT '' NOT NULL,
	poc_1_email		VARCHAR(128) DEFAULT '' NOT NULL,
	poc_1_phone_a		VARCHAR(64) DEFAULT '' NOT NULL,
	poc_1_phone_b		VARCHAR(64) DEFAULT '' NOT NULL,
	poc_1_cell		VARCHAR(64) DEFAULT '' NOT NULL,
	poc_1_screen		VARCHAR(64) DEFAULT '' NOT NULL,
	poc_1_notes		TEXT DEFAULT '' NOT NULL,
	poc_2_name		VARCHAR(128) DEFAULT '' NOT NULL,
	poc_2_email		VARCHAR(128) DEFAULT '' NOT NULL,
	poc_2_phone_a		VARCHAR(64) DEFAULT '' NOT NULL,
	poc_2_phone_b		VARCHAR(64) DEFAULT '' NOT NULL,
	poc_2_cell		VARCHAR(64) DEFAULT '' NOT NULL,
	poc_2_screen		VARCHAR(64) DEFAULT '' NOT NULL,
	poc_2_notes		TEXT DEFAULT '' NOT NULL,
	PRIMARY KEY ( hostid )
) with OIDS;
ALTER TABLE ONLY host_profile ADD CONSTRAINT c_host_profile_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;


-- create temporary t_host_profile table
CREATE TABLE t_host_profile (
	hostid			BIGINT NOT NULL,
	type			VARCHAR(64) DEFAULT '',
	type_full		VARCHAR(64) DEFAULT '',
	name			VARCHAR(64) DEFAULT '',
	alias			VARCHAR(64) DEFAULT '',
	os			VARCHAR(64) DEFAULT '',
	os_full			VARCHAR(255) DEFAULT '',
	os_short		VARCHAR(64) DEFAULT '',
	serialno_a		VARCHAR(64) DEFAULT '',
	serialno_b		VARCHAR(64) DEFAULT '',
	tag			VARCHAR(64) DEFAULT '',
	asset_tag		VARCHAR(64) DEFAULT '',
	macaddress_a		VARCHAR(64) DEFAULT '',
	macaddress_b		VARCHAR(64) DEFAULT '',
	hardware		VARCHAR(255) DEFAULT '',
	hardware_full		TEXT DEFAULT '',
	software		VARCHAR(255) DEFAULT '',
	software_full		TEXT DEFAULT '',
	software_app_a		VARCHAR(64) DEFAULT '',
	software_app_b		VARCHAR(64) DEFAULT '',
	software_app_c		VARCHAR(64) DEFAULT '',
	software_app_d		VARCHAR(64) DEFAULT '',
	software_app_e		VARCHAR(64) DEFAULT '',
	contact			TEXT DEFAULT '',
	location		TEXT DEFAULT '',
	location_lat		VARCHAR(16) DEFAULT '',
	location_lon		VARCHAR(16) DEFAULT '',
	notes			TEXT DEFAULT '',
	chassis			VARCHAR(64) DEFAULT '',
	model			VARCHAR(64) DEFAULT '',
	hw_arch			VARCHAR(32) DEFAULT '',
	vendor			VARCHAR(64) DEFAULT '',
	contract_number		VARCHAR(64) DEFAULT '',
	installer_name		VARCHAR(64) DEFAULT '',
	deployment_status	VARCHAR(64) DEFAULT '',
	url_a			VARCHAR(255) DEFAULT '',
	url_b			VARCHAR(255) DEFAULT '',
	url_c			VARCHAR(255) DEFAULT '',
	host_networks		TEXT DEFAULT '',
	host_netmask		VARCHAR(39) DEFAULT '',
	host_router		VARCHAR(39) DEFAULT '',
	oob_ip			VARCHAR(39) DEFAULT '',
	oob_netmask		VARCHAR(39) DEFAULT '',
	oob_router		VARCHAR(39) DEFAULT '',
	date_hw_purchase	VARCHAR(64) DEFAULT '',
	date_hw_install		VARCHAR(64) DEFAULT '',
	date_hw_expiry		VARCHAR(64) DEFAULT '',
	date_hw_decomm		VARCHAR(64) DEFAULT '',
	site_address_a		VARCHAR(128) DEFAULT '',
	site_address_b		VARCHAR(128) DEFAULT '',
	site_address_c		VARCHAR(128) DEFAULT '',
	site_city		VARCHAR(128) DEFAULT '',
	site_state		VARCHAR(64) DEFAULT '',
	site_country		VARCHAR(64) DEFAULT '',
	site_zip		VARCHAR(64) DEFAULT '',
	site_rack		VARCHAR(128) DEFAULT '',
	site_notes		TEXT DEFAULT '',
	poc_1_name		VARCHAR(128) DEFAULT '',
	poc_1_email		VARCHAR(128) DEFAULT '',
	poc_1_phone_a		VARCHAR(64) DEFAULT '',
	poc_1_phone_b		VARCHAR(64) DEFAULT '',
	poc_1_cell		VARCHAR(64) DEFAULT '',
	poc_1_screen		VARCHAR(64) DEFAULT '',
	poc_1_notes		TEXT DEFAULT '',
	poc_2_name		VARCHAR(128) DEFAULT '',
	poc_2_email		VARCHAR(128) DEFAULT '',
	poc_2_phone_a		VARCHAR(64) DEFAULT '',
	poc_2_phone_b		VARCHAR(64) DEFAULT '',
	poc_2_cell		VARCHAR(64) DEFAULT '',
	poc_2_screen		VARCHAR(64) DEFAULT '',
	poc_2_notes		TEXT DEFAULT '',
	notes_ext		TEXT DEFAULT '',
	PRIMARY KEY ( hostid )
) with OIDS;

-- select all profiles into temporary table
INSERT INTO t_host_profile
	SELECT p.hostid, p.devicetype, ep.device_type, p.name, ep.device_alias, p.os, ep.device_os, ep.device_os_short, p.serialno, ep.device_serial,
		p.tag, ep.device_tag, p.macaddress, ep.ip_macaddress, ep.device_hardware, p.hardware, ep.device_software, p.software,
		ep.device_app_01, ep.device_app_02, ep.device_app_03, ep.device_app_04, ep.device_app_05, p.contact, p.location, '', '',
		p.notes, ep.device_chassis, ep.device_model, ep.device_hw_arch,	ep.device_vendor, ep.device_contract, ep.device_who,
		ep.device_status, ep.device_url_1, ep.device_url_2, ep.device_url_3, ep.device_networks, ep.ip_subnet_mask, ep.ip_router,
		ep.oob_ip, ep.oob_subnet_mask, ep.oob_router, ep.date_hw_buy, ep.date_hw_install, ep.date_hw_expiry, ep.date_hw_decomm,
		ep.site_street_1, ep.site_street_2, ep.site_street_3, ep.site_city, ep.site_state, ep.site_country, ep.site_zip, ep.site_rack,
		ep.site_notes, ep.poc_1_name, ep.poc_1_email, ep.poc_1_phone_1, ep.poc_1_phone_2, ep.poc_1_cell, ep.poc_1_screen, ep.poc_1_notes,
		ep.poc_2_name, ep.poc_2_email, ep.poc_2_phone_1, ep.poc_2_phone_2, ep.poc_2_cell, ep.poc_2_screen, ep.poc_2_notes, ep.device_notes
	FROM hosts_profiles p LEFT JOIN hosts_profiles_ext ep on p.hostid=ep.hostid
	UNION ALL
	SELECT ep.hostid, p.devicetype, ep.device_type, p.name, ep.device_alias, p.os, ep.device_os, ep.device_os_short, p.serialno, ep.device_serial,
		p.tag, ep.device_tag, p.macaddress, ep.ip_macaddress, ep.device_hardware, p.hardware, ep.device_software, p.software,
		ep.device_app_01, ep.device_app_02, ep.device_app_03, ep.device_app_04, ep.device_app_05, p.contact, p.location, '', '',
		p.notes, ep.device_chassis, ep.device_model, ep.device_hw_arch,	ep.device_vendor, ep.device_contract, ep.device_who,
		ep.device_status, ep.device_url_1, ep.device_url_2, ep.device_url_3, ep.device_networks, ep.ip_subnet_mask, ep.ip_router,
		ep.oob_ip, ep.oob_subnet_mask, ep.oob_router, ep.date_hw_buy, ep.date_hw_install, ep.date_hw_expiry, ep.date_hw_decomm,
		ep.site_street_1, ep.site_street_2, ep.site_street_3, ep.site_city, ep.site_state, ep.site_country, ep.site_zip, ep.site_rack,
		ep.site_notes, ep.poc_1_name, ep.poc_1_email, ep.poc_1_phone_1, ep.poc_1_phone_2, ep.poc_1_cell, ep.poc_1_screen, ep.poc_1_notes,
		ep.poc_2_name, ep.poc_2_email, ep.poc_2_phone_1, ep.poc_2_phone_2, ep.poc_2_cell, ep.poc_2_screen, ep.poc_2_notes, ep.device_notes
	FROM hosts_profiles p RIGHT JOIN hosts_profiles_ext ep on p.hostid=ep.hostid
	WHERE p.hostid IS NULL;


UPDATE t_host_profile SET type='' WHERE type IS NULL;
UPDATE t_host_profile SET type_full='' WHERE type_full IS NULL;
UPDATE t_host_profile SET name='' WHERE name IS NULL;
UPDATE t_host_profile SET alias='' WHERE alias IS NULL;
UPDATE t_host_profile SET os='' WHERE os IS NULL;
UPDATE t_host_profile SET os_full='' WHERE os_full IS NULL;
UPDATE t_host_profile SET os_short='' WHERE os_short IS NULL;
UPDATE t_host_profile SET serialno_a='' WHERE serialno_a IS NULL;
UPDATE t_host_profile SET serialno_b='' WHERE serialno_b IS NULL;
UPDATE t_host_profile SET tag='' WHERE tag IS NULL;
UPDATE t_host_profile SET asset_tag='' WHERE asset_tag IS NULL;
UPDATE t_host_profile SET macaddress_a='' WHERE macaddress_a IS NULL;
UPDATE t_host_profile SET macaddress_b='' WHERE macaddress_b IS NULL;
UPDATE t_host_profile SET hardware='' WHERE hardware IS NULL;
UPDATE t_host_profile SET hardware_full='' WHERE hardware_full IS NULL;
UPDATE t_host_profile SET software='' WHERE software IS NULL;
UPDATE t_host_profile SET software_full='' WHERE software_full IS NULL;
UPDATE t_host_profile SET software_app_a='' WHERE software_app_a IS NULL;
UPDATE t_host_profile SET software_app_b='' WHERE software_app_b IS NULL;
UPDATE t_host_profile SET software_app_c='' WHERE software_app_c IS NULL;
UPDATE t_host_profile SET software_app_d='' WHERE software_app_d IS NULL;
UPDATE t_host_profile SET software_app_e='' WHERE software_app_e IS NULL;
UPDATE t_host_profile SET contact='' WHERE contact IS NULL;
UPDATE t_host_profile SET location='' WHERE location IS NULL;
UPDATE t_host_profile SET location_lat='' WHERE location_lat IS NULL;
UPDATE t_host_profile SET location_lon='' WHERE location_lon IS NULL;
UPDATE t_host_profile SET notes='' WHERE notes IS NULL;
UPDATE t_host_profile SET chassis='' WHERE chassis IS NULL;
UPDATE t_host_profile SET model='' WHERE model IS NULL;
UPDATE t_host_profile SET hw_arch='' WHERE hw_arch IS NULL;
UPDATE t_host_profile SET vendor='' WHERE vendor IS NULL;
UPDATE t_host_profile SET contract_number='' WHERE contract_number IS NULL;
UPDATE t_host_profile SET installer_name='' WHERE installer_name IS NULL;
UPDATE t_host_profile SET deployment_status='' WHERE deployment_status IS NULL;
UPDATE t_host_profile SET url_a='' WHERE url_a IS NULL;
UPDATE t_host_profile SET url_b='' WHERE url_b IS NULL;
UPDATE t_host_profile SET url_c='' WHERE url_c IS NULL;
UPDATE t_host_profile SET host_networks='' WHERE host_networks IS NULL;
UPDATE t_host_profile SET host_netmask='' WHERE host_netmask IS NULL;
UPDATE t_host_profile SET host_router='' WHERE host_router IS NULL;
UPDATE t_host_profile SET oob_ip='' WHERE oob_ip IS NULL;
UPDATE t_host_profile SET oob_netmask='' WHERE oob_netmask IS NULL;
UPDATE t_host_profile SET oob_router='' WHERE oob_router IS NULL;
UPDATE t_host_profile SET date_hw_purchase='' WHERE date_hw_purchase IS NULL;
UPDATE t_host_profile SET date_hw_install='' WHERE date_hw_install IS NULL;
UPDATE t_host_profile SET date_hw_expiry='' WHERE date_hw_expiry IS NULL;
UPDATE t_host_profile SET date_hw_decomm='' WHERE date_hw_decomm IS NULL;
UPDATE t_host_profile SET site_address_a='' WHERE site_address_a IS NULL;
UPDATE t_host_profile SET site_address_b='' WHERE site_address_b IS NULL;
UPDATE t_host_profile SET site_address_c='' WHERE site_address_c IS NULL;
UPDATE t_host_profile SET site_city='' WHERE site_city IS NULL;
UPDATE t_host_profile SET site_state='' WHERE site_state IS NULL;
UPDATE t_host_profile SET site_country='' WHERE site_country IS NULL;
UPDATE t_host_profile SET site_zip='' WHERE site_zip IS NULL;
UPDATE t_host_profile SET site_rack='' WHERE site_rack IS NULL;
UPDATE t_host_profile SET site_notes='' WHERE site_notes IS NULL;
UPDATE t_host_profile SET poc_1_name='' WHERE poc_1_name IS NULL;
UPDATE t_host_profile SET poc_1_email='' WHERE poc_1_email IS NULL;
UPDATE t_host_profile SET poc_1_phone_a='' WHERE poc_1_phone_a IS NULL;
UPDATE t_host_profile SET poc_1_phone_b='' WHERE poc_1_phone_b IS NULL;
UPDATE t_host_profile SET poc_1_cell='' WHERE poc_1_cell IS NULL;
UPDATE t_host_profile SET poc_1_screen='' WHERE poc_1_screen IS NULL;
UPDATE t_host_profile SET poc_1_notes='' WHERE poc_1_notes IS NULL;
UPDATE t_host_profile SET poc_2_name='' WHERE poc_2_name IS NULL;
UPDATE t_host_profile SET poc_2_email='' WHERE poc_2_email IS NULL;
UPDATE t_host_profile SET poc_2_phone_a='' WHERE poc_2_phone_a IS NULL;
UPDATE t_host_profile SET poc_2_phone_b='' WHERE poc_2_phone_b IS NULL;
UPDATE t_host_profile SET poc_2_cell='' WHERE poc_2_cell IS NULL;
UPDATE t_host_profile SET poc_2_screen='' WHERE poc_2_screen IS NULL;
UPDATE t_host_profile SET poc_2_notes='' WHERE poc_2_notes IS NULL;

-- merge notes field
UPDATE t_host_profile SET notes_ext='' WHERE notes_ext IS NULL;
UPDATE t_host_profile SET notes=notes||'\n'||notes_ext WHERE notes<>'' AND notes_ext<>'';
UPDATE t_host_profile SET notes=notes_ext WHERE notes='';
ALTER TABLE ONLY t_host_profile DROP COLUMN notes_ext;

-- copy data from temporary table
INSERT INTO host_profile SELECT * FROM t_host_profile;

DROP TABLE t_host_profile;
DROP TABLE hosts_profiles;
DROP TABLE hosts_profiles_ext;
