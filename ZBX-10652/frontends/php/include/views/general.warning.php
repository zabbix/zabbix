<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$pageHeader = new CPageHeader(_('Warning').' ['._s('refreshed every %1$s sec.', 30).']');
$pageHeader->addCssInit();
$pageHeader->display();

?>
<body>
<?php

// check if a CWarning object is passed
if (!$warning = $this->get('warning')) {
	$message = $this->get('message');

	if (is_array($message) && isset($message['header'])) {
		$message = array(bold($message['header']), BR(), $message['text']);
	}

	// if not - render a standard warning with a message
	$warning = new CWarning('Zabbix '.ZABBIX_VERSION, $message);
	$warning->setButtons(array(new CButton('login', _('Retry'), 'document.location.reload();', 'formlist')));
}

$warning->show();

?>
<script type="text/javascript">
	setTimeout('document.location.reload();', 30000);
</script>

</body>
</html>
