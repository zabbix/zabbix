<?php

include dirname(__FILE__).'/common.item.edit.js.php';

$this->data['valueTypeVisibility'] = array();
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'data_type');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_data_type');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'multiplier');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_multiplier');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'delta');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_delta');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_LOG, 'logtimefmt');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_LOG, 'row_logtimefmt');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'valuemap_name');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'valuemap_name');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'valuemap_name');
if (empty($this->data['parent_discoveryid'])) {
	zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'inventory_link');
	zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'row_inventory_link');
	zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_TEXT, 'inventory_link');
	zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_TEXT, 'row_inventory_link');
	zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'inventory_link');
	zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_inventory_link');
	zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'inventory_link');
	zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_inventory_link');
}

$this->data['dataTypeVisibility'] = array();
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_DECIMAL, 'units');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_DECIMAL, 'row_units');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_OCTAL, 'units');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_OCTAL, 'row_units');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_HEXADECIMAL, 'units');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_HEXADECIMAL, 'row_units');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_DECIMAL, 'multiplier');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_DECIMAL, 'row_multiplier');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_OCTAL, 'multiplier');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_OCTAL, 'row_multiplier');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_HEXADECIMAL, 'multiplier');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_HEXADECIMAL, 'row_multiplier');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_DECIMAL, 'delta');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_DECIMAL, 'row_delta');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_OCTAL, 'delta');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_OCTAL, 'row_delta');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_HEXADECIMAL, 'delta');
zbx_subarray_push($this->data['dataTypeVisibility'], ITEM_DATA_TYPE_HEXADECIMAL, 'row_delta');

?>
<script type="text/javascript">
	function displayKeyButton() {
		// selected item type
		var type = parseInt(jQuery('#type').val());

		jQuery('#keyButton').prop('disabled',
			type != <?php echo ITEM_TYPE_ZABBIX; ?>
				&& type != <?php echo ITEM_TYPE_ZABBIX_ACTIVE; ?>
				&& type != <?php echo ITEM_TYPE_SIMPLE; ?>
				&& type != <?php echo ITEM_TYPE_INTERNAL; ?>
				&& type != <?php echo ITEM_TYPE_AGGREGATE; ?>
				&& type != <?php echo ITEM_TYPE_DB_MONITOR; ?>
				&& type != <?php echo ITEM_TYPE_SNMPTRAP; ?>
		)
	}

	jQuery(document).ready(function() {
		// field switchers
		<?php if (!empty($this->data['dataTypeVisibility'])) { ?>
		var dataTypeSwitcher = new CViewSwitcher('data_type', 'change',
			<?php echo zbx_jsvalue($this->data['dataTypeVisibility'], true); ?>);
		<?php } ?>
		<?php
		if (!empty($this->data['valueTypeVisibility'])) { ?>
			var valueTypeSwitcher = new CViewSwitcher('value_type', 'change',
				<?php echo zbx_jsvalue($this->data['valueTypeVisibility'], true); ?>);
		<?php } ?>

		// multiplier
		var multpStat = document.getElementById('multiplier');

		if (multpStat && multpStat.onclick) {
			multpStat.onclick();
		}

		jQuery('#type').change(function() {
				displayKeyButton();
			})
			.trigger('change');
	});
</script>
