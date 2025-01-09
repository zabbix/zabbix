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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxjson.h"
#include "zbxcacheconfig/user_macro.h"
#include "zbxalgo.h"
#include "um_cache_mock.h"

ZBX_VECTOR_DECL(kv, zbx_dc_kv_t)
ZBX_VECTOR_IMPL(kv, zbx_dc_kv_t)

typedef struct
{
	zbx_um_cache_t		*cache;
	zbx_vector_kv_t		macros;
	zbx_vector_uint64_t	hostids;
	zbx_um_mock_cache_t	mock_cache;
}
zbx_mock_step_t;

ZBX_PTR_VECTOR_DECL(mock_step, zbx_mock_step_t *)
ZBX_PTR_VECTOR_IMPL(mock_step, zbx_mock_step_t *)

static void	mock_step_free(zbx_mock_step_t *step)
{
	int	i;

	for (i = step->cache->refcount; i != 0; i--)
		um_cache_release(step->cache);

	zbx_vector_kv_destroy(&step->macros);
	zbx_vector_uint64_destroy(&step->hostids);

	zbx_free(step);
}

static void	mock_step_validate(zbx_mock_step_t *step)
{
	int	i;

	for (i = 0; i < step->macros.values_num; i++)
	{
		const char	*value = NULL;

		um_cache_resolve_const(step->cache, step->hostids.values, step->hostids.values_num,
			step->macros.values[i].key, ZBX_MACRO_ENV_SECURE, &value);

		if (NULL == step->macros.values[i].value)
		{
			if (NULL == value)
				continue;
			fail_msg("Expected unresolved macro while got '%s'", value);
		}

		if (NULL == value)
			fail_msg("Expected '%s' value while got unresolved", step->macros.values[i].value);

		if (0 != strcmp(step->macros.values[i].value, value))
		{
			fail_msg("Expected macro '%s' value '%s' while got '%s'", step->macros.values[i].key,
					step->macros.values[i].value, value);
		}
	}
}

static char	*mock_get_vault(zbx_mock_handle_t hvault)
{
	zbx_mock_handle_t	hkvset, hkvs, hkv;
	struct zbx_json		j;
	char			*vault;

	zbx_json_init(&j, 1024);

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hvault, &hkvset))
	{
		const char	*path;

		path = zbx_mock_get_object_member_string(hkvset, "path");
		zbx_json_addobject(&j, path);

		hkvs = zbx_mock_get_object_member_handle(hkvset, "values");

		while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hkvs, &hkv))
		{
			zbx_json_addstring(&j, zbx_mock_get_object_member_string(hkv, "key"),
					zbx_mock_get_object_member_string(hkv, "value"), ZBX_JSON_TYPE_STRING);
		}

		zbx_json_close(&j);

	}

	vault = zbx_strdup(NULL, j.buffer);
	zbx_json_free(&j);

	return vault;
}

static void	mock_read_macros(zbx_vector_kv_t *macros, zbx_mock_handle_t hmacros)
{
	zbx_mock_handle_t	hmacro;

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hmacros, &hmacro))
	{
		zbx_dc_kv_t	kv;

		kv.key = zbx_mock_get_object_member_string(hmacro, "name");
		kv.value = zbx_mock_get_object_member_string(hmacro, "value");

		zbx_vector_kv_append(macros, kv);
	}
}

static void	mock_read_hostids(zbx_vector_uint64_t *hostids, zbx_mock_handle_t hhostids)
{
	zbx_mock_handle_t	hhostid;

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hhostids, &hhostid))
	{
		zbx_uint64_t 	hostid;

		if (ZBX_MOCK_SUCCESS  != zbx_mock_uint64(hhostid, &hostid))
			fail_msg("invalid macro hostid");

		zbx_vector_uint64_append(hostids, hostid);
	}
}

static void	mock_read_steps(zbx_vector_mock_step_t *steps, zbx_mock_handle_t hsteps)
{
	zbx_mock_handle_t	hstep, hconfig;
	zbx_um_mock_cache_t	mock_cache0, *mock_cache_last = &mock_cache0;
	char			*vault;
	zbx_dc_config_t		*config = get_dc_config();

	um_mock_cache_init(&mock_cache0, -1);

	config->um_cache = um_cache_create();

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hsteps, &hstep))
	{
		zbx_mock_step_t		*step;
		zbx_dbsync_t		gmacros, hmacros, htmpls;
		struct zbx_json_parse	jp;
		zbx_config_vault_t	config_vault = {NULL, NULL, NULL, NULL, NULL, NULL, NULL};
		const char		*config_source_ip = "";
		char			*config_ssl_ca_location = NULL, *config_ssl_cert_location = NULL,
					*config_ssl_key_location = NULL;

		step = (zbx_mock_step_t *)zbx_malloc(NULL, sizeof(zbx_mock_step_t));

		zbx_dbsync_init(&gmacros, NULL, ZBX_DBSYNC_UPDATE);
		zbx_dbsync_init(&hmacros, NULL, ZBX_DBSYNC_UPDATE);
		zbx_dbsync_init(&htmpls, NULL, ZBX_DBSYNC_UPDATE);

		hconfig = zbx_mock_get_object_member_handle(hstep, "config");
		um_mock_cache_init(&step->mock_cache, hconfig);
		um_mock_cache_diff(mock_cache_last, &step->mock_cache, &gmacros, &hmacros, &htmpls);
		config->um_cache = step->cache = um_cache_sync(config->um_cache, 0, &gmacros, &hmacros, &htmpls,
				&config_vault, get_program_type());

		mock_dbsync_clear(&gmacros);
		mock_dbsync_clear(&hmacros);
		mock_dbsync_clear(&htmpls);

		vault = mock_get_vault(zbx_mock_get_object_member_handle(hconfig, "vault"));

		if (FAIL == zbx_json_open(vault, &jp))
			fail_msg("invalid vault json");

		zbx_dc_sync_kvs_paths(&jp, &config_vault, config_source_ip, config_ssl_ca_location,
				config_ssl_cert_location, config_ssl_key_location);
		step->cache->refcount++;

		zbx_free(vault);

		zbx_vector_kv_create(&step->macros);
		mock_read_macros(&step->macros, zbx_mock_get_object_member_handle(hstep, "macros"));

		zbx_vector_uint64_create(&step->hostids);
		mock_read_hostids(&step->hostids, zbx_mock_get_object_member_handle(hstep, "hostids"));

		zbx_vector_mock_step_append(steps, step);

		um_mock_cache_clear(mock_cache_last);
		mock_cache_last = &step->mock_cache;
	}

	um_mock_cache_clear(mock_cache_last);

}

void	zbx_mock_test_entry(void **state)
{
	zbx_vector_mock_step_t	steps;
	int			i;

	ZBX_UNUSED(state);

	zbx_vector_mock_step_create(&steps);

	um_mock_config_init();

	mock_read_steps(&steps, zbx_mock_get_parameter_handle("in.steps"));

	for (i = 0; i < steps.values_num; i++)
	{
		printf("=== STEP %d ===\n", i + 1);
		mock_step_validate(steps.values[i]);
	}

	zbx_vector_mock_step_clear_ext(&steps, mock_step_free);
	zbx_vector_mock_step_destroy(&steps);

	um_mock_config_destroy();
}
