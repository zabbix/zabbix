<script type="text/javascript">
	jQuery(function() {
		initPMaster(
			'slideshows',
			<?=
				CJs::encodeJson([
					WIDGET_SLIDESHOW => [
						'frequency' => timeUnitToSeconds($data['element']['delay']) * $data['refreshMultiplier'],
						'url' => 'slides.php?output=html&elementid='.$this->data['elementId'].
							(isset($this->data['groupid']) ? '&groupid='.$this->data['groupid'] : '').
							(isset($this->data['hostid']) ? '&hostid='.$this->data['hostid'] : ''),
						'counter' => 0,
						'darken' => 0,
						'params' => [
							'widgetRefresh' => WIDGET_SLIDESHOW,
							'lastupdate' => time()
						]
					]
				])
			?>
		);
	});
</script>
