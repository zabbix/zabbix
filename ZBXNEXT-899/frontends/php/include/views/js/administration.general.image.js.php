<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery('.header_wide').tooltip({
			items: 'img',
			content: function() {
				return '<img src="' + jQuery($(this)).attr('src').replace('imgstore.php?iconid=', 'image.php?imageid=') + '" />';
			}
		});
	});
</script>
