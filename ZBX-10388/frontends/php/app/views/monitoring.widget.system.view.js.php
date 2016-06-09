<script type="text/x-jquery-tmpl" id="systemStatusTable">
	<table class="list-table" id="syssum_table">
		<thead>
			<tr/>
		</thead>
		<tbody/>
	</table>
</script>

<script type="text/x-jquery-tmpl" id="systemStatusCell">
	<span class="link-action" onMouseOver="hintBox.HintWraper(event, this, jQuery(#{table_id}), '', '')"
		onClick="hintBox.showStaticHint(event, this, jQuery(#{table_id}), '', false, '')">
		#{count}
	</span>
</script>

<script type="text/x-jquery-tmpl" id="systemStatusPopupTable">
	<table class="list-table" id="#{table_id}">
		<thead>
			<tr>
				<th>Host</th>
				<th>Issue</th>
				<th>Age</th>
				<th>Info</th>
				<th>Ack</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody/>
	</table>
</script>

<script>
function syssumParser(data) {
	var i, header, group, tpl;

	tpl = new Template(jQuery('#systemStatusTable').html());
	jQuery('#syssum').html(tpl.evaluate());

	for (i = 0; i < data.header.length; i++) {
		header = data.header[i];
		jQuery('#syssum_table > thead > tr').append('<th>'+header+'</th>');
	}

	for (i = 0; i < data.groups.length; i++) {
		var j, span, popupTable, row;

		group = data.groups[i];

		row = jQuery('<tr/>')
			.append('<td><a href="'+group.name.attributes.href+'">'+group.name.items[0]+'</a></td>');

		span = new Template(jQuery('#systemStatusCell').html());
		popupTable = new Template(jQuery('#systemStatusPopupTable').html());

		for (j = 0; j < group.severities.length; j++) {
			var severity, cell;

			severity = group.severities[j];

			cell = jQuery('<td/>')
				.addClass(severity.style);

			if (typeof severity.popup_unack !== 'undefined' && severity.popup_unack.length > 0) {
				severity.table_id = 't2_'+i+'_'+j;
				cell
					.append(span.evaluate({count:severity.count_unack, table_id:severity.table_id}))
					.append(
						jQuery('<span style="display:none;"/>')
							.append(
								jQuery(popupTable.evaluate(severity))
									.append(severity.popup_unack)
							)
					);
			}

			if (severity.popup.length > 0) {
				severity.table_id = 't1_'+i+'_'+j;
				cell
					.append(span.evaluate(severity))
					.append(
						jQuery('<span style="display:none;"/>')
							.append(
								jQuery(popupTable.evaluate(severity))
									.append(severity.popup)
							)
					);
			}
			else {
				cell.append(severity.count);
			}

			row.append(cell);
		}

		jQuery('#syssum_table > tbody').append(row);
	}
}

</script>
