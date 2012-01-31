<?php

class CHostInventoryExportElement extends CExportElement {

	public function __construct(array $inventory) {
		parent::__construct('inventory', $inventory);
	}

	protected function requiredFields() {
		return array('inventory_mode', 'type', 'type_full', 'name', 'alias', 'os', 'os_full', 'os_short', 'serialno_a',
			'serialno_b', 'tag', 'asset_tag', 'macaddress_a', 'macaddress_b', 'hardware', 'hardware_full', 'software',
			'software_full', 'software_app_a', 'software_app_b', 'software_app_c', 'software_app_d', 'software_app_e',
			'contact', 'location', 'location_lat', 'location_lon', 'notes', 'type', 'chassis', 'model', 'hw_arch', 'vendor',
			'contract_number', 'installer_name', 'deployment_status', 'url_a', 'url_b', 'url_c', 'host_networks',
			'host_netmask', 'host_router', 'oob_ip', 'oob_netmask', 'oob_router', 'date_hw_purchase', 'date_hw_install',
			'date_hw_expiry', 'date_hw_decomm', 'site_address_a', 'site_address_b', 'site_address_c', 'site_city',
			'site_state', 'site_country', 'site_zip', 'site_rack', 'site_notes', 'poc_1_name', 'poc_1_email', 'poc_1_phone_a',
			'poc_1_phone_b', 'poc_1_cell', 'poc_1_screen', 'poc_1_notes', 'poc_2_name', 'poc_2_email', 'poc_2_phone_a',
			'poc_2_phone_b', 'poc_2_cell', 'poc_2_screen', 'poc_2_notes');
	}
}
