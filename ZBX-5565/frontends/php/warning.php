<!doctype html>
<html>
<head>
	<title>Warning [refreshed every 30 sec]</title>
	<meta name="Author" content="Zabbix SIA" />
	<meta charset="utf-8" />
	<link rel="shortcut icon" href="images/general/zabbix.ico" />
	<link rel="stylesheet" type="text/css" href="css.css" />
</head>

<body>

<table class="warningTable" style="margin-top: 100px;">
	<tr class="header">
		<td>Zabbix <?php echo ZABBIX_VERSION ?></td>
	</tr>
	<tr class="content center">
		<td><?php echo $warningMessage ?></td>
	</tr>
	<tr class="footer">
		<td >
			<div class="buttons">
				<input class="input formlist" type="button" value="Retry" onclick="document.location.reload();" />
			</div>
		</td>
	</tr>
</table>

<script>
setTimeout("document.location.reload();", 30000);
</script>

</body>
</html>
