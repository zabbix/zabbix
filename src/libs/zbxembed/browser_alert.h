/*
** Copyright (C) 2001-2024 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_BROWSER_ALERT_H
#define ZABBIX_BROWSER_ALERT_H

#include "config.h"

#ifdef HAVE_LIBCURL

#include "duk_config.h"
#include "webdriver.h"

void	wd_alert_create(duk_context *ctx, zbx_webdriver_t *wd, const char *text);

#endif

#endif
