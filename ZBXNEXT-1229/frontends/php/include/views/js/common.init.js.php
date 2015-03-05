<script type="text/javascript">
	jQuery(document).ready(function() {
		<?php if (isset($ZBX_PAGE_POST_JS)): ?>
			<?php foreach ($ZBX_PAGE_POST_JS as $script): ?>
				<?php echo $script."\n"; ?>
			<?php endforeach ?>
		<?php endif ?>

		<?php if (defined('ZBX_PAGE_DO_REFRESH') && CWebUser::$data['refresh']): ?>
			PageRefresh.init(<?php echo CWebUser::$data['refresh'] * 1000; ?>);
		<?php endif ?>

		<?php if (isset($page['scripts']) && in_array('flickerfreescreen.js', $page['scripts'])): ?>
			window.flickerfreeScreenShadow.timeout = <?php echo SCREEN_REFRESH_TIMEOUT * 1000; ?> ;
			window.flickerfreeScreenShadow.responsiveness = <?php echo SCREEN_REFRESH_RESPONSIVENESS * 1000; ?>;
		<?php endif ?>

		// the chkbxRange.init() method must be called after the inserted post scripts and initializing cookies
		cookie.init();
		chkbxRange.init();
	});
</script>
