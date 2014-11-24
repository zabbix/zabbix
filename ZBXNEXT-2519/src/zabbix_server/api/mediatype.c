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

#include "common.h"
#include "zbxalgo.h"
#include "zbxjson.h"
#include "log.h"
#include "dbschema.h"

#include "api.h"
#include "mediatype.h"


#define ZBX_API_MEDIATYPE_GET_TAG_MEDIATYPEIDS	"mediatypeids"
#define ZBX_API_MEDIATYPE_GET_TAG_MEDIAIDS	"mediaids"
#define ZBX_API_MEDIATYPE_GET_TAG_USERIDS	"userids"
#define ZBX_API_MEDIATYPE_GET_TAG_SELECTUSERS	"selectUsers"

/* TODO: investigate if it's possible to reuse dbschema definition */
static const zbx_api_field_t object_fields[] = {
		{"mediatypeid", ZBX_TYPE_ID, ZBX_API_FIELD_FLAG_SORTABLE},
		{"type", ZBX_TYPE_INT, ZBX_API_FIELD_FLAG_REQUIRED},
		{"description", ZBX_TYPE_CHAR,  ZBX_API_FIELD_FLAG_REQUIRED},
		{"smtp_server", ZBX_TYPE_CHAR, 0},
		{"smtp_email", ZBX_TYPE_CHAR, 0},
		{"exec_path", ZBX_TYPE_CHAR, 0},
		{"gsm_modem", ZBX_TYPE_CHAR, 0},
		{"username", ZBX_TYPE_CHAR, 0},
		{"passwd", ZBX_TYPE_CHAR, 0},
		{"status", ZBX_TYPE_INT, 0},
		{NULL}
};

int	zbx_api_mediatype_get_init(zbx_api_mediatype_get_t *self, struct zbx_json_parse *jp, char **error)
{
	char		name[ZBX_API_PARAM_NAME_SIZE], *value = NULL;
	const char	*p = NULL;
	int		ret = FAIL, i;

	zbx_vector_uint64_create(&self->mediatypeids);
	zbx_vector_uint64_create(&self->mediaids);
	zbx_vector_uint64_create(&self->userids);
	zbx_api_query_init(&self->select_users);

	zbx_api_get_init(&self->options);

	while (NULL != (p = zbx_json_pair_next(jp, p, name, sizeof(name))))
	{
		const char	*next = p;

		if (SUCCEED != zbx_api_get_parse(&self->options, name, jp, &next, error))
			goto out;

		if (next != p)
		{
			/* the parameter was successfully parsed by common get options parser */
			p = next;
			continue;
		}

		if (0 == strcmp(name, ZBX_API_MEDIATYPE_GET_TAG_MEDIATYPEIDS))
		{
			if (SUCCEED != zbx_api_get_param_idarray(ZBX_API_MEDIATYPE_GET_TAG_MEDIATYPEIDS, &next,
					&self->mediatypeids, error))
			{
				goto out;
			}
		}
		else if (0 == strcmp(name, ZBX_API_MEDIATYPE_GET_TAG_MEDIAIDS))
		{
			if (SUCCEED != zbx_api_get_param_idarray(ZBX_API_MEDIATYPE_GET_TAG_MEDIAIDS, &next,
					&self->mediaids, error))
			{
				goto out;
			}
		}
		else if (0 == strcmp(name, ZBX_API_MEDIATYPE_GET_TAG_USERIDS))
		{
			if (SUCCEED != zbx_api_get_param_idarray(ZBX_API_MEDIATYPE_GET_TAG_USERIDS, &next,
					&self->userids, error))
			{
				goto out;
			}
		}
		else if (0 == strcmp(name, ZBX_API_MEDIATYPE_GET_TAG_SELECTUSERS))
		{
			if (SUCCEED != zbx_api_get_param_query(ZBX_API_MEDIATYPE_GET_TAG_USERIDS, &next,
					&self->select_users, error))
			{
				goto out;
			}
		}
		else
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\"", name);
			goto out;

		}
	}

	if (SUCCEED != zbx_api_get_validate(&self->options, error))
		goto out;

	for (i = 0; i < self->options.sort.values_num; i++)
	{
		zbx_api_sort_t		*sort = (zbx_api_sort_t *)self->options.sort.values[i];
		const zbx_api_field_t	*field;

		if (NULL == (field = zbx_api_field_get(object_fields, sort->field)) ||
				0 == (field->flags & ZBX_API_FIELD_FLAG_SORTABLE))
		{
			*error = zbx_dsprintf(*error, "invalid sort field \"%s\"", sort->field);
			goto out;
		}
	}

	if (ZBX_API_QUERY_FIELDS == self->options.output.type)
	{
		for (i = 0; i < self->options.output.fields.values_num; i++)
		{
			if (NULL == zbx_api_field_get(object_fields, self->options.output.fields.values[i]))
			{
				*error = zbx_dsprintf(*error, "invalid output field \"%s\"",
						self->options.output.fields.values[i]);
				goto out;
			}
		}
	}

	ret = SUCCEED;

out:
	zbx_free(value);

	if (SUCCEED != ret)
		zbx_api_mediatype_get_free(self);

	return ret;
}

void	zbx_api_mediatype_get_free(zbx_api_mediatype_get_t *self)
{
	zbx_api_get_free(&self->options);

	zbx_api_query_free(&self->select_users);

	zbx_vector_uint64_destroy(&self->userids);
	zbx_vector_uint64_destroy(&self->mediaids);
	zbx_vector_uint64_destroy(&self->mediatypeids);
}

