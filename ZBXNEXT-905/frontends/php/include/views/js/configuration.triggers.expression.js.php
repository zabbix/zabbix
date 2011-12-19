<script type="text/javascript">
	function add_var_to_opener_obj(obj, name, value) {
		new_variable = window.opener.document.createElement('input');
		new_variable.type = 'hidden';
		new_variable.name = name;
		new_variable.value = value;
		obj.appendChild(new_variable);
	}

	function insertText(obj, value) {
		<?php if ($this->data['dstfld1'] == 'expression') { ?>
		if (IE) {
			obj.focus();
			var s = window.opener.document.selection.createRange();
			s.text = value;
		}
		else if (obj.selectionStart || obj.selectionStart == '0') {
			var s = obj.selectionStart;
			var e = obj.selectionEnd;
			var objValue = jQuery(obj).val();
			jQuery(obj).val(objValue.substring(0, s) + value + objValue.substring(e, objValue.length));
		}
		else {
			jQuery(obj).val(value);
		}
		<?php } else { ?>
		jQuery(obj).val(value);
		<?php } ?>
	}

	jQuery(document).ready(function() {
		'use strict';

		jQuery('#paramtype').change(function() {
			if (jQuery('#paramtype option:selected').val() == <?php echo PARAM_TYPE_COUNTS; ?>) {
				jQuery('#value').removeAttr('readonly');
			}
			else {
				jQuery('#value').attr('readonly', 'readonly');
			}
		});
	});
</script>
<?php
if (!empty($this->data['insert'])) {
	$expression = sprintf('{%s:%s.%s(%s%s)}%s%s',
		$this->data['item_host'],
		$this->data['item_key'],
		$this->data['function'],
		$this->data['paramtype'] == PARAM_TYPE_COUNTS ? '#' : '',
		rtrim(implode(',', $this->data['param']), ','),
		$this->data['operator'],
		$this->data['value']
	);
	?>
	<script language="JavaScript" type="text/javascript">
		var form = window.opener.document.forms['<?php echo $this->data['dstfrm']; ?>'];
		if (form) {
			var el = form.elements['<?php echo $this->data['dstfld1']; ?>'];
			if (el) {
				insertText(el, <?php echo zbx_jsvalue($expression); ?>);
				close_window();
			}
		}
	</script>
<?php
}
if (!empty($this->data['cancel'])) {?>
	<script language="JavaScript" type="text/javascript">
		close_window();
	</script>
<?php
}
