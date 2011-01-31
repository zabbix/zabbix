<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
define('ZBX_PAGE_NO_HEADER', 1);
define('ZBX_PAGE_NO_FOOTER',1);

$sessionid = get_cookie('zbx_sessionid');
CUser::checkAuthentication(array('sessionid'=>$sessionid));

$request = zbx_htmlstr(get_request('request',''));
$message = zbx_htmlstr(get_request('message','')) ;

require_once('include/page_header.php');
?>
<form action="index.php">
<input type="hidden" name="request" class="input hidden" value="<?php print($request); ?>" />
<div style="position: absolute; top: 25%; left: 25%;">
<div class="loginForm">
	<div style="position: relative; color: #F0F0F0; height: 100%;">
<!-- Help & Support -->
		<div style="position: absolute; top: 0px; right: 10px;">
			<a class="highlight" href="http://www.zabbix.com/documentation/1.8/manual/about/overview_of_zabbix"><?php print(_('Help')); ?></a>
			&nbsp;|&nbsp;
			<a class="highlight" href="https://support.zabbix.com"><?php print(_('Support')); ?></a>
		</div>
<!-- Copyright -->
		<div style="float: left; width: 250px; height: 100%;">
			<div style="position: absolute; top: 39%; left: 2%;" class="loginLogo"></div>
			<div style="position: absolute; bottom: 2px;">
				<span class="bold textwhite" style="margin: 0 0 4px 4px; font-size: 0.9em; opacity: 0.7;">
					<?php print(_s('Zabbix %s Copyright 2001-2011 by SIA Zabbix', ZABBIX_VERSION)); ?>
				</span>
			</div>
		</div>
<!-- Login Form -->
		<div style="height: 100%; padding-top: 58px; padding-right: 40px; margin-left: 275px;">
			<div style="float: right;">
			<ul style="list-style-type: none;">
				<li style="padding-right: 6px; height: 22px;">
					<div class="ui-corner-all textwhite bold" style="padding: 2px 4px; float: right; background-color: #CC3333; visibility: <?php print(zbx_empty($message)?'hidden':'visible'); ?>" >
						<?php print($message); ?>
					</div>
				</li>
				<li style="margin-top: 10px; padding-top: 1px; height: 22px; width: 265px; background-image: url(images/general/login/username_pass_field.png); background-repeat: no-repeat;" >
					<div class="bold" style="display: inline-block; zoom: 1; *display:inline; *margin-right: 2px; font-size: 1.1em; width: 70px; padding-left: 10px; line-height: 22px;"><?php print(_('Username')); ?></div>
					<input type="text" id="name" name="name" class="input bold transparent" style="color: #5f5f5f; height: 16px; line-height: 16px; width: 16.6em;" />
				</li>
				<li style="margin-top: 10px; padding-top: 1px; height: 22px; width: 265px; background-image: url(images/general/login/username_pass_field.png); background-repeat: no-repeat;" >
					<div class="bold" style="display: inline-block; zoom: 1; *display:inline; *margin-right: 2px; font-size: 1.1em; width: 70px; padding-left: 10px; line-height: 22px;"><?php print(_('Password')); ?></div>
					<input type="password" name="password" class="input bold transparent" style="color: #5f5f5f; height: 16px; line-height: 16px; width: 16.6em;" />
				</li>
				<li style="margin-top: 8px; margin-left: 64px;">
					<input type="checkbox" id="autologin" name="autologin" value="1" <?php print((get_request('autologin',1) == 1)?'checked="checked"':''); ?> />
					<label for="autologin" class="bold" style="line-height: 20px; vertical-align: top;">
						<?php print(_('Remember me for 30 days')); ?>
					</label>
					<div style="height: 8px;"></div>
					<input type="submit" class="input" name="enter" id="enter" value="<?php print(_('Sign in'));?>" />
					<?php if($USER_DETAILS['userid'] > 0){ ?>
						<span style="margin-left: 14px;">
							<a class="highlight underline" href="dashboard.php"><?php print(_('Login as Guest')); ?></a>
						</span>
					<?php } ?>
				</li>
			</ul>
			</div>
		</div>
	</div>
</div>
</div>
</form>
<script type="text/javascript">
jQuery(document).ready(function(){
	jQuery(document.body).addClass('loginBG');
	jQuery('#enter').button();
	jQuery('#name').focus();

});
</script>
<?php
require_once('include/page_footer.php');
?>
