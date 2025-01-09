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

#include "mock_service.h"

static void	mock_read_eventids(const char *path, zbx_vector_uint64_t *eventids)
{
	zbx_mock_handle_t	hevents, hevent;
	zbx_mock_error_t	err;
	zbx_uint64_t		eventid;

	hevents = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hevents, &hevent))))
	{
		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != zbx_mock_uint64(hevent, &eventid))
			fail_msg("cannot read eventids from %s", path);

		zbx_vector_uint64_append(eventids, eventid);
	}
}

void	zbx_mock_test_entry(void **state)
{
	zbx_service_t		*service;
	int			i	;
	const char		*service_name;
	zbx_vector_uint64_t	eventids_ret, eventids_exp;

	ZBX_UNUSED(state);

	zbx_vector_uint64_create(&eventids_ret);
	zbx_vector_uint64_create(&eventids_exp);

	mock_init_service_cache("in.services");

	service_name = zbx_mock_get_parameter_string("in.service");
	if (NULL == (service = mock_get_service(service_name)))
		fail_msg("cannot find service '%s'", service_name);

	service_get_rootcause_eventids(service, &eventids_ret);
	mock_destroy_service_cache();

	mock_read_eventids("out.events", &eventids_exp);

	printf("Expected eventids:\n");
	for (i = 0; i < eventids_exp.values_num; i++)
		printf("\t" ZBX_FS_UI64 "\n", eventids_exp.values[i]);

	printf("Returned eventids:\n");
	for (i = 0; i < eventids_ret.values_num; i++)
		printf("\t" ZBX_FS_UI64 "\n", eventids_ret.values[i]);

	zbx_mock_assert_int_eq("number of root cause events", eventids_exp.values_num, eventids_ret.values_num);
	for (i = 0; i < eventids_exp.values_num; i++)
		zbx_mock_assert_uint64_eq("eventid", eventids_exp.values[i], eventids_ret.values[i]);

	zbx_vector_uint64_destroy(&eventids_exp);
	zbx_vector_uint64_destroy(&eventids_ret);
}
