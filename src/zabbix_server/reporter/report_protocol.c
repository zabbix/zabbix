/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "zbxreport.h"
#include "report_protocol.h"
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "zbxalgo.h"
#include "db.h"

static int	json_uint_by_tag(const struct zbx_json_parse *jp, const char *tag, zbx_uint64_t *value, char **error)
{
	char	buf[MAX_ID_LEN + 1];

	if (SUCCEED != zbx_json_value_by_name(jp, tag, buf, sizeof(buf), NULL))
	{
		*error = zbx_dsprintf(*error, "cannot find tag: %s", tag);
		return FAIL;
	}

	if (SUCCEED != is_uint64(buf, value))
	{
		*error = zbx_dsprintf(*error, "invalid tag %s value: %s", tag, buf);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * ZBX_IPC_REPORTER_TEST_REPORT message serialization/deserialization         *
 *                                                                            *
 ******************************************************************************/

static zbx_uint32_t	report_serialize_test_report(unsigned char **data, zbx_uint64_t dashboardid,
		zbx_uint64_t userid, zbx_uint64_t viewer_userid, int report_time, int period,
		const zbx_vector_ptr_pair_t *params)
{
	zbx_uint32_t	data_len = 0, *len;
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, dashboardid);
	zbx_serialize_prepare_value(data_len, userid);
	zbx_serialize_prepare_value(data_len, viewer_userid);
	zbx_serialize_prepare_value(data_len, report_time);
	zbx_serialize_prepare_value(data_len, period);
	zbx_serialize_prepare_value(data_len, params->values_num);

	len = (zbx_uint32_t *)zbx_malloc(NULL, params->values_num * 2 * sizeof(zbx_uint32_t));

	for (i = 0; i < params->values_num; i++)
	{
		zbx_serialize_prepare_str_len(data_len, params->values[i].first, len[i * 2]);
		zbx_serialize_prepare_str_len(data_len, params->values[i].second, len[i * 2 + 1]);
	}

	*data = (unsigned char *)zbx_malloc(NULL, data_len);
	ptr = *data;

	ptr += zbx_serialize_value(ptr, dashboardid);
	ptr += zbx_serialize_value(ptr, userid);
	ptr += zbx_serialize_value(ptr, viewer_userid);
	ptr += zbx_serialize_value(ptr, report_time);
	ptr += zbx_serialize_value(ptr, period);
	ptr += zbx_serialize_value(ptr, params->values_num);

	for (i = 0; i < params->values_num; i++)
	{
		ptr += zbx_serialize_str(ptr, params->values[i].first, len[i * 2]);
		ptr += zbx_serialize_str(ptr, params->values[i].second, len[i * 2 + 1]);
	}

	zbx_free(len);

	return data_len;
}

void	report_deserialize_test_report(const unsigned char *data, zbx_uint64_t *dashboardid, zbx_uint64_t *userid,
		zbx_uint64_t *viewer_userid, int *report_time, int *period, zbx_vector_ptr_pair_t *params)
{
	int	params_num, i;

	data += zbx_deserialize_value(data, dashboardid);
	data += zbx_deserialize_value(data, userid);
	data += zbx_deserialize_value(data, viewer_userid);
	data += zbx_deserialize_value(data, report_time);
	data += zbx_deserialize_value(data, period);
	data += zbx_deserialize_value(data, &params_num);

	zbx_vector_ptr_pair_reserve(params, params_num);
	for (i = 0; i < params_num; i++)
	{
		zbx_ptr_pair_t	pair;
		zbx_uint32_t	len;

		data += zbx_deserialize_str(data, (char **)&pair.first, len);
		data += zbx_deserialize_str(data, (char **)&pair.second, len);
		zbx_vector_ptr_pair_append(params, pair);
	}
}

/******************************************************************************
 *                                                                            *
 * ZBX_IPC_REPORTER_TEST_REPORT_RESULT, ZBX_IPC_REPORTER_REPORT_RESULT        *
 * message serialization/deserialization                                      *
 *                                                                            *
 ******************************************************************************/

zbx_uint32_t	report_serialize_response(unsigned char **data, int status, const char *error)
{
	zbx_uint32_t	data_len = 0, error_len;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, status);
	zbx_serialize_prepare_str(data_len, error);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);
	ptr = *data;

	ptr += zbx_serialize_value(ptr, status);
	(void)zbx_serialize_str(ptr, error, error_len);

	return data_len;
}

void	report_deserialize_response(const unsigned char *data, int *status, char **error)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_int(data, status);
	(void)zbx_deserialize_str(data, error, len);
}

/******************************************************************************
 *                                                                            *
 * ZBX_IPC_REPORTER_BEGIN_REPORT message serialization/deserialization        *
 *                                                                            *
 ******************************************************************************/

zbx_uint32_t	report_serialize_begin_report(unsigned char **data, const char *url, const char *cookie,
		const zbx_vector_ptr_pair_t *params)
{
	zbx_uint32_t	data_len = 0, *params_len, url_len, cookie_len;
	unsigned char	*ptr;
	int		i;

	zbx_serialize_prepare_str(data_len, url);
	zbx_serialize_prepare_str(data_len, cookie);
	zbx_serialize_prepare_value(data_len, params->values_num);

	params_len = (zbx_uint32_t *)zbx_malloc(NULL, params->values_num * 2 * sizeof(zbx_uint32_t));
	for (i = 0; i < params->values_num; i++)
	{
		zbx_serialize_prepare_str_len(data_len, params->values[i].first, params_len[i * 2]);
		zbx_serialize_prepare_str_len(data_len, params->values[i].second, params_len[i * 2 + 1]);
	}

	*data = (unsigned char *)zbx_malloc(NULL, data_len);
	ptr = *data;

	ptr += zbx_serialize_str(ptr, url, url_len);
	ptr += zbx_serialize_str(ptr, cookie, cookie_len);

	ptr += zbx_serialize_value(ptr, params->values_num);

	for (i = 0; i < params->values_num; i++)
	{
		ptr += zbx_serialize_str(ptr, params->values[i].first, params_len[i * 2]);
		ptr += zbx_serialize_str(ptr, params->values[i].second, params_len[i * 2 + 1]);
	}

	zbx_free(params_len);

	return data_len;
}

void	report_deserialize_begin_report(const unsigned char *data, char **url, char **cookie,
		zbx_vector_ptr_pair_t *params)
{
	zbx_uint32_t	len;
	int		i, params_num;

	data += zbx_deserialize_str(data, url, len);
	data += zbx_deserialize_str(data, cookie, len);

	data += zbx_deserialize_value(data, &params_num);
	zbx_vector_ptr_pair_reserve(params, params_num);
	for (i = 0; i < params_num; i++)
	{
		zbx_ptr_pair_t	pair;

		data += zbx_deserialize_str(data, (char **)&pair.first, len);
		data += zbx_deserialize_str(data, (char **)&pair.second, len);
		zbx_vector_ptr_pair_append(params, pair);
	}
}

/******************************************************************************
 *                                                                            *
 * ZBX_IPC_REPORTER_SEND_REPORT message serialization/deserialization        *
 *                                                                            *
 ******************************************************************************/

zbx_uint32_t	report_serialize_send_report(unsigned char **data, const DB_MEDIATYPE *mt,
		const zbx_vector_str_t *emails)
{
	zbx_uint32_t	data_len = 0, data_alloc = 1024, data_offset = 0, *params_len;
	unsigned char	*ptr;
	int		i;

	*data = zbx_malloc(NULL, data_alloc);
	zbx_serialize_mediatype(data, &data_alloc, &data_offset, mt);

	zbx_serialize_prepare_value(data_len, emails->values_num);
	params_len = (zbx_uint32_t *)zbx_malloc(NULL, emails->values_num * sizeof(zbx_uint32_t));
	for (i = 0; i < emails->values_num; i++)
	{
		zbx_serialize_prepare_str_len(data_len, emails->values[i], params_len[i]);
	}

	if (data_alloc - data_offset < data_len)
	{
		data_alloc = data_offset + data_len;
		*data = (unsigned char *)zbx_realloc(*data, data_alloc);
	}

	ptr = *data + data_offset;
	ptr += zbx_serialize_value(ptr, emails->values_num);
	for (i = 0; i < emails->values_num; i++)
	{
		ptr += zbx_serialize_str(ptr, emails->values[i], params_len[i]);
	}

	zbx_free(params_len);

	return data_offset + data_len;
}

void	report_deserialize_send_report(const unsigned char *data, DB_MEDIATYPE *mt, zbx_vector_str_t *sendtos)
{
	zbx_uint32_t	len;
	int		i, sendto_num;

	data += zbx_deserialize_mediatype(data, mt);

	data += zbx_deserialize_value(data, &sendto_num);
	zbx_vector_str_reserve(sendtos, sendto_num);
	for (i = 0; i < sendto_num; i++)
	{
		char	*sendto;

		data += zbx_deserialize_str(data, &sendto, len);
		zbx_vector_str_append(sendtos, sendto);
	}
}

void	report_destroy_params(zbx_vector_ptr_pair_t *params)
{
	int	i;

	for (i = 0; i < params->values_num; i++)
	{
		zbx_free(params->values[i].first);
		zbx_free(params->values[i].second);
	}
	zbx_vector_ptr_pair_destroy(params);
}

int	zbx_report_test(const struct zbx_json_parse *jp, zbx_uint64_t userid, char **error)
{
	zbx_uint64_t		dashboardid, viewer_userid, ui64;
	int			ret = FAIL, period, report_time;
	struct zbx_json_parse	jp_params;
	zbx_vector_ptr_pair_t	params;
	zbx_uint32_t		size;
	unsigned char		*data = NULL, *response = NULL;

	zbx_vector_ptr_pair_create(&params);

	if (SUCCEED != json_uint_by_tag(jp, ZBX_PROTO_TAG_DASHBOARDID, &dashboardid, error))
		goto out;

	if (SUCCEED != json_uint_by_tag(jp, ZBX_PROTO_TAG_USERID, &viewer_userid, error))
		goto out;

	if (SUCCEED != json_uint_by_tag(jp, ZBX_PROTO_TAG_PERIOD, &ui64, error))
		goto out;
	period = (int)ui64;

	if (SUCCEED != json_uint_by_tag(jp, ZBX_PROTO_TAG_NOW, &ui64, error))
		goto out;
	report_time = (int)ui64;

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_PARAMS, &jp_params))
	{
		const char		*pnext = NULL;
		char			key[MAX_STRING_LEN];

		while (NULL != (pnext = zbx_json_pair_next(&jp_params, pnext, key, sizeof(key))))
		{
			char		*value = NULL;
			size_t		value_alloc = 0;
			zbx_ptr_pair_t	pair;

			zbx_json_decodevalue_dyn(pnext, &value, &value_alloc, NULL);
			pair.first = zbx_strdup(NULL, key);
			pair.second = value;
			zbx_vector_ptr_pair_append(&params, pair);
		}
	}

	size = report_serialize_test_report(&data, dashboardid, userid, viewer_userid, report_time, period, &params);

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_REPORTER, ZBX_IPC_REPORTER_TEST,
			SEC_PER_MIN, data, size, &response, error))
	{
		goto out;
	}

	report_deserialize_response(response, &ret, error);
out:
	zbx_free(response);
	zbx_free(data);

	report_destroy_params(&params);

	return ret;
}
