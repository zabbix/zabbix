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
			jQuery(obj).val(jQuery(obj).val() + value);
		}
		<?php } else { ?>
		jQuery(obj).val(value);
		<?php } ?>
	}

	jQuery(document).ready(function() {
		'use strict';

		jQuery('#paramtype').change(function() {
			if (jQuery('#expr_type option:selected').val().substr(0, 4) == 'last') {
				if (jQuery('#paramtype option:selected').val() == <?php echo PARAM_TYPE_COUNTS; ?>) {
					jQuery('#param_0').removeAttr('readonly');
				}
				else {
					jQuery('#param_0').attr('readonly', 'readonly');
				}
			}
		});
		jQuery(document).ready(function() {
			if (jQuery('#expr_type option:selected').val().substr(0, 4) == 'last') {
				if (jQuery('#paramtype option:selected').val() == <?php echo PARAM_TYPE_COUNTS; ?>) {
					jQuery('#param_0').removeAttr('readonly');
				}
				else {
					jQuery('#param_0').attr('readonly', 'readonly');
				}
			}
		});
	});
</script>
<?php
if (!empty($this->data['insert'])) {
	if ($this->data['function'] == 'count' && !empty($this->data['param']['2'])) {
		$this->data['param']['2'] = '"'.$this->data['param']['2'].'"';
	}
	if (($this->data['function'] == 'regexp'
		|| $this->data['function'] == 'iregexp'
		|| $this->data['function'] == 'str')
		&& $this->data['paramtype'] == PARAM_TYPE_COUNTS) {
		$this->data['param']['1'] = '#'.$this->data['param']['1'];
	}
	$expression = sprintf('{%s:%s.%s(%s%s)}%s%s',
		$this->data['item_host'],
		$this->data['item_key'],
		$this->data['function'],
		($this->data['paramtype'] == PARAM_TYPE_COUNTS
			&& $this->data['function'] != 'regexp'
			&& $this->data['function'] != 'iregexp'
			&& $this->data['function'] != 'str') ? '#' : '',
		rtrim(implode(',', $this->data['param']), ','),
		$this->data['operator'],
		$this->data['value']
	);
	?>
	<script language="JavaScript" type="text/javascript">
		insertText(jQuery('#<?php echo $this->data['dstfld1']; ?>', window.opener.document), <?php echo zbx_jsvalue($expression); ?>);
		close_window();
	</script>
<?php
}
if (!empty($this->data['cancel'])) {?>
	<script language="JavaScript" type="text/javascript">
		close_window();
	</script>
<?php
}
