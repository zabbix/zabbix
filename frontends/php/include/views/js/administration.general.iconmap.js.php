<script type="text/javascript">
	jQuery(document).ready(function() {
		'use strict';

		function recalculateSortOrder() {
			var i = 1;
			jQuery('#iconMapTable tr.sortable .rowNum').each(function() {
				jQuery(this).text(i++ + ':');
			});
		}

		jQuery('#iconMapTable').sortable({
			disabled: (jQuery('#iconMapTable tr.sortable').length <= 1),
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			containment: 'parent',
			handle: 'span.ui-icon-arrowthick-2-n-s',
			tolerance: 'pointer',
			opacity: 0.6,
			update: recalculateSortOrder,
			start: function(e, ui) {
				jQuery(ui.placeholder).height(jQuery(ui.helper).height());
			}
		});

		jQuery('#iconMapTable tbody').delegate('input.removeMapping', 'click', function() {
			jQuery(this).parent().parent().remove();

			if (jQuery('#iconMapTable tr.sortable').length <= 1) {
				jQuery('#iconMapTable').sortable('disable');
			}
			recalculateSortOrder();
		});

		jQuery('#iconMapTable tbody').delegate('select.mappingIcon', 'change', function() {
			jQuery(this).parent().next().children().attr('src', 'imgstore.php?iconid=' + jQuery(this).val());
		});

		jQuery('#iconMapTable tbody').delegate('img.preview', 'click', function() {
			hintBox.onClick(this, '<img src=' + jQuery(this).attr('src') + ' >');
		});

		jQuery('#addMapping').click(function() {
			var tpl = new Template(jQuery('#rowTpl').html()),
				iconmappingid = Math.floor(Math.random() * 10000000).toString(),
				mapping = {};

			while (jQuery('#iconmapidRow_' + iconmappingid).length) {
				iconmappingid++;
			}
			mapping.iconmappingid = iconmappingid;
			jQuery('<tr id="iconmapidRow_' + iconmappingid + '" class="sortable">' + tpl.evaluate(mapping) + '</tr>').insertBefore('#rowTpl');
			jQuery('#iconmapidRow_' + iconmappingid + ' :input').prop('disabled', false);
			jQuery('#iconMapTable').sortable('refresh');

			if (jQuery('#iconMapTable tr.sortable').length > 1) {
				jQuery('#iconMapTable').sortable('enable');
			}

			recalculateSortOrder();
		});

		if (jQuery('#iconMapTable tr.sortable').length === 0) {
			jQuery('#addMapping').click();
		}

	});
</script>
