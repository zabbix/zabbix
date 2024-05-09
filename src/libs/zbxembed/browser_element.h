/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_BROWSER_ELEMENT_H
#define ZABBIX_BROWSER_ELEMENT_H

#ifdef HAVE_LIBCURL

#include "zbxembed.h"
#include "webdriver.h"

void	wd_element_create(duk_context *ctx, zbx_webdriver_t *wd, const char *elementid);
void	wd_element_create_array(duk_context *ctx, zbx_webdriver_t *wd, const zbx_vector_str_t *elements);

#endif

#endif
