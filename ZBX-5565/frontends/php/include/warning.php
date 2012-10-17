<?php
$pageHeader = new CPageHeader('Warning [refreshed every 30 sec]');
$pageHeader->addCssFile('css.css');
$pageHeader->display();
?>
<body>
<?php
$warning = new CWarning('Zabbix '.ZABBIX_VERSION, $warningMessage);
$warning->setButtons(array(
	new CButton('login', _('Retry'), 'document.location.reload();', 'formlist'),
));
$warning->show();
?>
<script>
setTimeout("document.location.reload();", 30000);
</script>

</body>
</html>
