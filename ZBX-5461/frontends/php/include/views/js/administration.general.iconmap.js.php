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

		jQuery('#iconMapTable tbody').delegate('select.mappingIcon, select#iconmap_default_iconid', 'change', function() {
			jQuery(this).closest('tr').find('.preview')
				.attr('src', 'imgstore.php?&width=<?php echo ZBX_ICON_PREVIEW_WIDTH; ?>&height=<?php echo ZBX_ICON_PREVIEW_HEIGHT; ?>&iconid=' + jQuery(this).val())
				.data('imageFull', 'imgstore.php?iconid=' + jQuery(this).val());
		});

		jQuery('#iconMapTable tbody').delegate('img.preview', 'click', function(e) {
			var img = jQuery('<img src=' + jQuery(this).data('imageFull') + ' >');
			hintBox.showStaticHint(e, this, img, '', '', true);
		});

		jQuery('#addMapping').click(function() {
			var tpl = new Template(jQuery('#rowTpl').html()),
				iconmappingid =  getUniqueId(),
				mapping = {};

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
