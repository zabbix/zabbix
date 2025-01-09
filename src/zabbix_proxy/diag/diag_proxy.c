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

#include "zbxdiag.h"
#include "diag_proxy.h"

#include "zbxshmem.h"
#include "zbxtime.h"
#include "zbxproxybuffer.h"
#include "zbxpreproc.h"
#include "zbxjson.h"

#define ZBX_DIAG_PROXYBUFFER_MEMORY	0x00000001

/******************************************************************************
 *                                                                            *
 * Purpose: add requested proxy buffer diagnostic information to json data    *
 *                                                                            *
 * Parameters: jp    - [IN] the request                                       *
 *             json  - [IN/OUT] the json to update                            *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - the information was added successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	diag_add_proxybuffer_info(const struct zbx_json_parse *jp, struct zbx_json *json, char **error)
{
	zbx_vector_diag_map_ptr_t	tops;
	int				ret;
	double				time1, time2, time_total = 0;
	zbx_uint64_t			fields;
	zbx_diag_map_t			field_map[] = {
							{"memory", ZBX_DIAG_PROXYBUFFER_MEMORY},
							{NULL, 0}
						};

	zbx_vector_diag_map_ptr_create(&tops);

	if (SUCCEED == (ret = zbx_diag_parse_request(jp, field_map, &fields, &tops, error)))
	{
		zbx_json_addobject(json, ZBX_DIAG_PROXYBUFFER);

		if (0 != (fields & ZBX_DIAG_PROXYBUFFER_MEMORY))
		{
			zbx_shmem_stats_t	stats;

			time1 = zbx_time();
			zbx_pb_get_mem_stats(&stats);
			time2 = zbx_time();
			time_total += time2 - time1;

			zbx_diag_add_mem_stats(json, "memory", &stats);
		}

		zbx_json_addfloat(json, "time", time_total);
		zbx_json_close(json);
	}

	zbx_vector_diag_map_ptr_clear_ext(&tops, zbx_diag_map_free);
	zbx_vector_diag_map_ptr_destroy(&tops);

	return ret;
}


/******************************************************************************
 *                                                                            *
 * Purpose: add requested section diagnostic information                      *
 *                                                                            *
 * Parameters: section - [IN] the section name                                *
 *             jp      - [IN] the request                                     *
 *             json    - [IN/OUT] the json to update                          *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the information was retrieved successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	diag_add_section_info(const char *section, const struct zbx_json_parse *jp, struct zbx_json *json,
		char **error)
{
	int	ret = FAIL;

	if (0 == strcmp(section, ZBX_DIAG_HISTORYCACHE))
		ret = zbx_diag_add_historycache_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_PROXYBUFFER))
		ret = diag_add_proxybuffer_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_PREPROCESSING))
		ret = zbx_diag_add_preproc_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_LOCKS))
	{
		zbx_diag_add_locks_info(json);
		ret = SUCCEED;
	}
	else
		*error = zbx_dsprintf(*error, "Unsupported diagnostics section: %s", section);

	return ret;
}
