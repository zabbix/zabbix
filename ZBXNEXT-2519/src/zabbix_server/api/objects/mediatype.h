/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_API_MEDIATYPE_H
#define ZABBIX_API_MEDIATYPE_H

#include "../api.h"

extern zbx_api_class_t zbx_api_class_mediatype;

int	zbx_api_mediatype_get(const zbx_api_user_t *user, const struct zbx_json_parse *jp_request,
		struct zbx_json *result);

int	zbx_api_mediatype_create(const zbx_api_user_t *user, const struct zbx_json_parse *jp_request,
		struct zbx_json *output);

int	zbx_api_mediatype_delete(const zbx_api_user_t *user, const struct zbx_json_parse *jp_request,
		struct zbx_json *output);

int	zbx_api_mediatype_update(const zbx_api_user_t *user, const struct zbx_json_parse *jp_request,
		struct zbx_json *output);

#endif
