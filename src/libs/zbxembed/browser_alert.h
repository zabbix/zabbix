/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_BROWSER_ALERT_H
#define ZABBIX_BROWSER_ALERT_H

#include "config.h"

#ifdef HAVE_LIBCURL

#include "duk_config.h"
#include "webdriver.h"

typedef struct
{
	zbx_webdriver_t	*wd;
}
zbx_wd_alert_t;

void	wd_alert_free(zbx_wd_alert_t *alert);
void	wd_alert_create(duk_context *ctx, zbx_webdriver_t *wd, const char *text);

#endif

#endif
