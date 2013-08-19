<?php
/*
** Zabbix
** copy of include/views/general.login.php
**/
?>
<?php
require_once dirname(__FILE__).'/../page_header.php';
?>
<div style="display: table; position: absolute; height: 99%; width: 99%;">
<div class="vertical-middle">
<div class="loginForm">
	<div style="position: relative; color: #F0F0F0; height: 100%;">
		<!-- Search Form -->
		<div style="height: 100%; padding-top: 100px; padding-right: 40px; margin-left: 275px;">
			<div style="float: right;">
				<?php
					$searchForm = new CView('general.search');
					echo $searchForm->render();
				?>
				<div style="height: 45px;"></div>
				<span style="margin-left: 5px;">
					<a class="highlight underline" href="charts.php?fullscreen=0&groupid=0&hostid=0&graphid=0"><?php echo _('Graphs'); ?></a>
					<a class="highlight underline" href="profile.php"><?php echo _('Profile'); ?></a>
				</span>
			</div>
		</div>
	</div>
</div>
</div>
</div>
<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('#search').focus();
		jQuery('#search').height(40);
		jQuery('#search').css('font-size', 20);
		jQuery('#searchbttn').height(42);
	});
</script>
<?php
require_once dirname(__FILE__).'/../page_footer.php';
?>
