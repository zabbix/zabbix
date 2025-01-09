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
#include "zbxcacheconfig/user_macro.h"
#include "zbxalgo.h"
#include "um_cache_mock.h"

typedef struct
{
	zbx_uint32_t		refs;
	zbx_um_mock_cache_t	mock_cache;
	zbx_um_cache_t		*cache;
}
zbx_mock_step_t;

ZBX_PTR_VECTOR_DECL(mock_step, zbx_mock_step_t *)
ZBX_PTR_VECTOR_IMPL(mock_step, zbx_mock_step_t *)

static void	mock_step_free(zbx_mock_step_t *step)
{
	zbx_uint32_t	i;

	um_mock_cache_clear(&step->mock_cache);

	for (i = 0; i < step->refs; i++)
		um_cache_release(step->cache);

	zbx_free(step);
}

static void	dbsync_check_empty(const char *entity, zbx_dbsync_t *sync)
{
	unsigned char	tag;
	zbx_uint64_t	rowid;
	char		**row;
	char		*errors[] = {"unknown", "added", "updated", "removed"};

	if (SUCCEED == zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		char	*msg = NULL;
		size_t	msg_alloc = 0, msg_offset = 0;
		int	i;

		for (i = 0; i < sync->columns_num; i++)
			zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "'%s',", row[i]);

		fail_msg("unexpected %s change detected: %s row: %s", entity, errors[tag], msg);
	}
}

static void	mock_step_validate(zbx_mock_step_t *step)
{
	zbx_um_mock_cache_t	mock_cache;
	zbx_dbsync_t		gmacros, hmacros, htmpls;

	um_mock_cache_init_from_config(&mock_cache, step->cache);

	zbx_dbsync_init(&gmacros, NULL, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&hmacros, NULL, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&htmpls, NULL, ZBX_DBSYNC_UPDATE);

	printf("MOCK:\n");
	um_mock_cache_dump(&step->mock_cache);
	printf("CONFIG:\n");
	um_mock_cache_dump(&mock_cache);

	um_mock_cache_diff(&step->mock_cache, &mock_cache, &gmacros, &hmacros, &htmpls);

	dbsync_check_empty("global macro", &gmacros);
	dbsync_check_empty("host macro", &hmacros);
	dbsync_check_empty("host template", &htmpls);

	mock_dbsync_clear(&gmacros);
	mock_dbsync_clear(&hmacros);
	mock_dbsync_clear(&htmpls);

	um_mock_cache_clear(&mock_cache);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_um_mock_cache_t	mock_cache0, *mock_cache = &mock_cache0;
	zbx_um_cache_t		*umc;
	zbx_vector_mock_step_t	steps;
	zbx_mock_step_t		*step;
	zbx_mock_handle_t	hsteps, hstep;
	zbx_mock_error_t	err;
	int			i, j;
	zbx_config_vault_t	config_vault = {NULL, NULL, NULL, NULL, NULL, NULL, NULL};

	ZBX_UNUSED(state);

	zbx_vector_mock_step_create(&steps);

	hsteps = zbx_mock_get_parameter_handle("in.steps");

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hsteps, &hstep))))
	{
		step = (zbx_mock_step_t *)zbx_malloc(NULL, sizeof(zbx_mock_step_t));
		step->refs = (zbx_uint32_t)zbx_mock_get_object_member_int(hstep, "refs");
		step->cache = NULL;
		um_mock_cache_init(&step->mock_cache, zbx_mock_get_object_member_handle(hstep, "config"));
		zbx_vector_mock_step_append(&steps, step);
	}

	um_mock_config_init();

	um_mock_cache_init(&mock_cache0, -1);
	umc = um_cache_create();

	for (i = 0; i < steps.values_num; i++)
	{
		zbx_dbsync_t	gmacros, hmacros, htmpls;

		printf("=== STEP %d ===\n", i + 1);

		zbx_dbsync_init(&gmacros, NULL, ZBX_DBSYNC_UPDATE);
		zbx_dbsync_init(&hmacros, NULL, ZBX_DBSYNC_UPDATE);
		zbx_dbsync_init(&htmpls, NULL, ZBX_DBSYNC_UPDATE);

		um_mock_cache_diff(mock_cache, &steps.values[i]->mock_cache, &gmacros, &hmacros, &htmpls);
		umc = steps.values[i]->cache = um_cache_sync(umc, 0, &gmacros, &hmacros, &htmpls, &config_vault,
				get_program_type());
		umc->refcount += steps.values[i]->refs;

		mock_dbsync_clear(&gmacros);
		mock_dbsync_clear(&hmacros);
		mock_dbsync_clear(&htmpls);

		for (j = 0; j <= i; j++)
		{
			if (0 != steps.values[j]->refs)
			{
				mock_step_validate(steps.values[j]);
				um_cache_release(steps.values[j]->cache);
				steps.values[j]->refs--;
			}
		}

		mock_cache = &steps.values[i]->mock_cache;
	}

	do
	{
		i = 0;
		for (j = 0; j < steps.values_num; j++)
		{
			if (0 != steps.values[j]->refs)
			{
				mock_step_validate(steps.values[j]);
				um_cache_release(steps.values[j]->cache);
				steps.values[j]->refs--;
				i++;
			}
		}
	}
	while (i != 0);

	um_cache_release(umc);
	um_mock_cache_clear(&mock_cache0);

	um_mock_config_destroy();

	zbx_vector_mock_step_clear_ext(&steps, mock_step_free);
	zbx_vector_mock_step_destroy(&steps);
}
