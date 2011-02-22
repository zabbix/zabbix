<script type="text/x-jquery-tmpl" id="globalSearch">
<form action="search.php" method="get">
	<input type="text" id="search" name="search" class="input text ui-corner-left" value="<?php print(get_request('search','')); ?>"autocomplete="off" />
	<input type="submit" id="searchbttn" name="searchbttn" value="<?php print(_('Search')); ?>" class="input button" style="margin: 0; padding: 0 8px; height: 20px; position: relative; left: -3px;" />
</form>
</script>

<script type="text/javascript">
jQuery(document).ready(function(){
	var tpl = new Template(jQuery('#globalSearch').html());
	jQuery("#zbx_search").html(tpl.evaluate([]));
	jQuery('#searchbttn').button()
		.removeClass('ui-corner-all')
		.addClass('ui-corner-right');
	jQuery('#searchbttn').css('height', '18px');

	createSuggest('search');

// FF visual fix
if(GK) jQuery('#searchbttn').css({'line-height':'12px', 'padding-bottom': '2px'});
if(IE) jQuery('#search').css({'height':'14px', 'padding-top': '2px'});
});


</script>
