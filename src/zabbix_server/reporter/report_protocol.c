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

static int	json_id_by_tag(const struct zbx_json_parse *jp, const char *tag, zbx_uint64_t *id, char **error)
{
	char	buf[MAX_ID_LEN + 1];

	if (SUCCEED != zbx_json_value_by_name(jp, tag, buf, sizeof(buf), NULL))
	{
		*error = zbx_dsprintf(*error, "cannot find tag: %s", tag);
		return FAIL;
	}

	if (SUCCEED != is_uint64(buf, id))
	{
		*error = zbx_dsprintf(*error, "invalid tag %s value: %s", tag, buf);
		return FAIL;
	}

	return SUCCEED;

}

static zbx_uint32_t	report_serialize_test_request(unsigned char **data, zbx_uint64_t dashboardid, zbx_uint64_t userid,
		zbx_uint64_t writer_userid, const zbx_vector_ptr_pair_t *params)
{
	zbx_uint32_t	data_len = 0, *len;
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, dashboardid);
	zbx_serialize_prepare_value(data_len, userid);
	zbx_serialize_prepare_value(data_len, writer_userid);
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
	ptr += zbx_serialize_value(ptr, writer_userid);
	ptr += zbx_serialize_value(ptr, params->values_num);

	for (i = 0; i < params->values_num; i++)
	{
		ptr += zbx_serialize_str(ptr, params->values[i].first, len[i * 2]);
		ptr += zbx_serialize_str(ptr, params->values[i].second, len[i * 2 + 1]);
	}

	zbx_free(len);

	return data_len;
}

void	report_deserialize_test_request(const unsigned char *data, zbx_uint64_t *dashboardid, zbx_uint64_t *userid,
		zbx_uint64_t *writer_userid, zbx_vector_ptr_pair_t *params)
{
	int	params_num, i;

	data += zbx_deserialize_value(data, dashboardid);
	data += zbx_deserialize_value(data, userid);
	data += zbx_deserialize_value(data, writer_userid);
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

zbx_uint32_t	report_serialize_test_response(unsigned char **data, int status, const char *error)
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


static void	report_deserialize_test_response(const unsigned char *data, int *status, char **error)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_int(data, status);
	(void)zbx_deserialize_str(data, error, len);
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
	zbx_uint64_t		dashboardid, writer_userid;
	int			ret = FAIL;
	struct zbx_json_parse	jp_params;
	zbx_vector_ptr_pair_t	params;
	zbx_uint32_t		size;
	unsigned char		*data = NULL, *response = NULL;

	zbx_vector_ptr_pair_create(&params);

	if (SUCCEED != json_id_by_tag(jp, ZBX_PROTO_TAG_DASHBOARDID, &dashboardid, error))
		goto out;

	if (SUCCEED != json_id_by_tag(jp, ZBX_PROTO_TAG_USERID, &writer_userid, error))
		goto out;

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

	size = report_serialize_test_request(&data, dashboardid, userid, writer_userid, &params);

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_REPORTER, ZBX_IPC_REPORTER_TEST_REPORT,
			SEC_PER_MIN, data, size, &response, error))
	{
		goto out;
	}

	report_deserialize_test_response(response, &ret, error);
out:
	zbx_free(response);
	zbx_free(data);

	report_destroy_params(&params);

	return ret;
}
