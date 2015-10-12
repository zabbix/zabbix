<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


define('ZBX_PAGE_NO_HEADER', 1);
define('ZBX_PAGE_NO_FOOTER', 1);

$request = CHtml::encode(getRequest('request', ''));
$message = CHtml::encode(getRequest('message', '')) ;
// remove debug code for login form message, trimming not in regex to relay only on [ ] in debug message.
$message = trim(preg_replace('/\[.*\]/', '', $message));

require_once dirname(__FILE__).'/../page_header.php';
?>
<header role="banner">
		<div><div class="signin-logo"></div></div>
</header>
<div class="<?= ZBX_STYLE_ARTICLE ?>">
<div class="signin-container">
	<h1>Sign in</h1>
	<form action="index.php" method="post">
	<input type="hidden" name="request" value="<?= $request; ?>" />
		<ul>
			<li>
				<label for="name"><?= _('Username'); ?></label><input id="name" name="name" autofocus="" type="text">
<?php if (!empty($message)): ?>
				<div class="red"><?= $message ?></div>
<?php endif ?>
			</li>
			<li><label for="password"><?= _('Password'); ?></label><input id="password" name="password" type="password"></li>
			<li><label for="autologin"><input name="autologin" value="1" id="autologin" <?= (getRequest('autologin', 1) == 1) ? 'checked="checked"' : ''; ?> type="checkbox"><?= _('Remember me for 30 days'); ?></label></li>
			<li><button name="enter" type="submit" value="<?= _('Sign in'); ?>"><?= _('Sign in'); ?></button></li>
<?php if (CWebUser::$data['userid'] > 0): ?>
			<li class="sign-in-txt">or <a href="<?= ZBX_DEFAULT_URL; ?>">sign in as guest</a></li>
<?php endif ?>
		</ul>
	</form>
</div>
<div class="signin-links"><a target="_blank" href="http://www.zabbix.com/documentation/"><?= _('Help'); ?></a>&nbsp;&nbsp;•&nbsp;&nbsp;<a target="_lbank" href="http://www.zabbix.com/support.php"><?= _('Support'); ?></a></div>
</div>

<?= makePageFooter(false, false)->toString() ?>

</body>
