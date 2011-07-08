<script type="text/javascript">
	jQuery(document).ready(function(){

		jQuery("#iconMapTable").sortable({
			items: 'tbody tr.sortable',
			axis: 'y',
			containment: 'parent',
			placeholder: "sortableRowPlaceholder",
			handle: 'span.ui-icon-arrowthick-2-n-s',
			tolerance: 'pointer',
			helper: 'clone',
			opacity: 0.6
		});
		jQuery("#iconMapTable").disableSelection();

		jQuery("#iconMapTable tbody").delegate('span.removeMapping', 'click', function(){
			jQuery(this).parent().parent().remove();
		});

		jQuery("#iconMapTable tbody").delegate('select.mappingIcon', 'change', function(){
			jQuery(this).parent().next().children().removeClass();
			jQuery(this).parent().next().children().addClass('sysmap_iconid_' + jQuery(this).val());
		});

		jQuery("#addMapping").click(function(){
			var tpl = new Template(jQuery('#rowTpl').html());

			var mapping = {iconmappingid: 1};
			jQuery('<tr class="sortable">' + tpl.evaluate(mapping) + '</tr>').insertBefore("#iconMapTable tbody tr:last-child");
			jQuery("#iconMapTable").sortable("refresh");
		}).click();

	});
</script>
