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

#include "api.h"

typedef struct
{
	zbx_vector_uint64_t	mediatypeids;

	zbx_vector_uint64_t	mediaids;

	zbx_vector_uint64_t	userids;

	zbx_api_query_t		select_users;

	zbx_api_get_t		options;
}
zbx_api_mediatype_get_t;


int	zbx_api_mediatype_get_init(zbx_api_mediatype_get_t *self, struct zbx_json_parse *json, char **error);
void	zbx_api_mediatype_get_free(zbx_api_mediatype_get_t *self);

#endif
