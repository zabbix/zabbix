<script type="text/javascript">
	function addMacroRow() {
		if (typeof(addMacroRow.macro_count) == 'undefined') {
			addMacroRow.macro_count = <?php echo count($this->data['macros']); ?>;
		}

		var tr = document.createElement('tr');
		tr.className = (addMacroRow.macro_count % 2) ? 'form_even_row' : 'form_odd_row';

		var td1 = document.createElement('td');
		tr.appendChild(td1);

		var cb = document.createElement('input');
		cb.setAttribute('type', 'checkbox');
		cb.className = 'input checkbox pointer';
		td1.appendChild(cb);
		td1.appendChild(document.createTextNode(' '));

		var td2 = document.createElement('td');
		tr.appendChild(td2);

		var text1 = document.createElement('input');
		text1.className = 'input text';
		text1.setAttribute('type', 'text');
		text1.setAttribute('name', 'macros['+addMacroRow.macro_count+'][macro]');
		text1.setAttribute('size', 30);
		text1.setAttribute('maxlength', 64);
		text1.setAttribute('placeholder', '{$MACRO}');
		text1.setAttribute('style', 'text-transform:uppercase;');
		td2.appendChild(text1);
		td2.appendChild(document.createTextNode(' '));

		var td3 = document.createElement('td');
		tr.appendChild(td3);

		var span = document.createElement('span');
		span.innerHTML = '&rArr;';
		span.setAttribute('style', 'vertical-align:top;');
		td3.appendChild(span);

		var td4 = document.createElement('td');
		tr.appendChild(td4);

		var text2 = document.createElement('input');
		text2.className = 'input text';
		text2.setAttribute('type', 'text');
		text2.setAttribute('placeholder', "<?php echo _('value'); ?>");
		text2.setAttribute('name', 'macros['+addMacroRow.macro_count+'][value]');
		text2.setAttribute('size', 40);
		text2.setAttribute('maxlength', 255);
		td4.appendChild(text2);

		var sd = $('row_new_macro').insert({before : tr});
		addMacroRow.macro_count++;
	}
</script>
