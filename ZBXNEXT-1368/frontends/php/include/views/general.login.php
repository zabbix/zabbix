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
<article>
<div class="signin-container">
	<h1>Sign in</h1>
	<form action="index.php" method="post">
	<input type="hidden" name="request" class="input hidden" value="<?php echo $request; ?>" />
		<ul>
			<li>
				<label for="name"><?php echo _('Username'); ?></label><input id="name" name="name" autofocus="" type="text">
<?php if (!empty($message)) { ?>
				<div class="red"><?php echo $message ?></div>
<?php } ?>
			</li>
			<li><label for="password"><?php echo _('Password'); ?></label><input id="password" name="password" type="password"></li>
			<li><label for="autologin"><input name="autologin" value="1" id="autologin" <?php echo (getRequest('autologin', 1) == 1) ? 'checked="checked"' : ''; ?> type="checkbox"><?php echo _('Remember me for 30 days'); ?></label></li>
			<li><button name="enter" type="submit" value="<?php echo _('Sign in'); ?>"><?php echo _('Sign in'); ?></button></li>
<?php if (CWebUser::$data['userid'] > 0) { ?>
			<li class="sign-in-txt">or <a href="<?php echo ZBX_DEFAULT_URL; ?>">sign in as guest</a></li>
<?php } ?>
		</ul>
	</form>
</div>
<div class="signin-links"><a target="_blank" href="http://www.zabbix.com/documentation/"><?php echo _('Help'); ?></a>&nbsp;&nbsp;•&nbsp;&nbsp;<a target="_lbank" href="http://www.zabbix.com/support.php"><?php echo _('Support'); ?></a></div>
</article>

<footer>
<?php echo _s('Zabbix %1$s Copyright %2$s-%3$s by Zabbix SIA', ZABBIX_VERSION, ZABBIX_COPYRIGHT_FROM, ZABBIX_COPYRIGHT_TO); ?>
</footer>

</body>
