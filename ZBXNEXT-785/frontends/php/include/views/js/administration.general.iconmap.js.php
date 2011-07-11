<script type="text/javascript">
	jQuery(document).ready(function(){
		"use strict";
console.log(jQuery("#iconMapTable tr.sortable").length <= 1);
		jQuery("#iconMapTable").sortable({
			disabled: (jQuery("#iconMapTable tr.sortable").length <= 1),
			items: 'tbody tr.sortable',
			axis: 'y',
			containment: 'parent',
			placeholder: "sortableRowPlaceholder",
			handle: 'span.ui-icon-arrowthick-2-n-s',
			tolerance: 'pointer',
			opacity: 0.6
		});

		jQuery("#iconMapTable tbody").delegate('span.removeMapping', 'click', function(){
			jQuery(this).parent().parent().remove();

			if(jQuery("#iconMapTable tr.sortable").length <= 1){
				jQuery("#iconMapTable").sortable('disable');
			}
		});

		jQuery("#iconMapTable tbody").delegate('select.mappingIcon', 'change', function(){
			jQuery(this).parent().next().children().removeClass();
			jQuery(this).parent().next().children().addClass('sysmap_iconid_' + jQuery(this).val());
		});

		jQuery("#addMapping").click(function(){
			var tpl = new Template(jQuery('#rowTpl').html()),
				iconmappingid = Math.floor(Math.random() * 10000000).toString(),
				mapping = {};

			while(jQuery('#iconmapidRow_' + iconmappingid).length){
				iconmappingid++;
			}
			mapping.iconmappingid = iconmappingid;
			jQuery('<tr id="iconmapidRow_' + iconmappingid + '" class="sortable">' + tpl.evaluate(mapping) + '</tr>')
					.insertBefore("#iconMapTable tbody tr:last-child");
			jQuery('#iconmapidRow_' + iconmappingid + ' :input').prop('disabled', false);
			jQuery("#iconMapTable").sortable("refresh");

			if(jQuery("#iconMapTable tr.sortable").length > 1){
				jQuery("#iconMapTable").sortable('enable');
			}
		});

		if(jQuery("#iconMapTable tr.sortable").length === 0){
			jQuery("#addMapping").click();
		}

	});
</script>
