/*
** Copyright (C) 2001-2026 Zabbix SIA
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

/* zbx_got_thresh_reading_cb(), zbx_got_discrete_states_cb() and zbx_got_thresholds_cb() are OpenIPMI      */
/* callbacks; checks_ipmi.c is included directly to reach them and the 'hostset' RETURN_IF_CB_DATA_INVALID */
/* needs.                                                                                                  */
/*                                                                                                          */
/* ipmi_states_t is a real (not mocked) OpenIPMI object - ipmi_states_size()/ipmi_init_states() and the    */
/* ipmi_set_*()/ipmi_is_*() accessors are plain, connection-independent utility functions operating on a   */
/* caller-owned buffer, so a real, valid states object is used throughout, never a fabricated one.         */
/*                                                                                                          */
/* The sensor argument, by contrast, is never dereferenced in any branch covered here - only compared by   */
/* identity via zbx_get_ipmi_sensor(), or (for zbx_got_discrete_states_cb()'s err!=0 branch) not used at    */
/* all once the sensor has been identified, since the error message is built purely from the already-owned */
/* s->id/id_type/id_sz. So a fabricated address stands in for it. Branches that do call a real              */
/* ipmi_sensor_*() getter on it (zbx_got_thresh_reading_cb()'s IPMI_BOTH_VALUES_PRESENT success path,       */
/* zbx_got_thresholds_cb()'s "found" path, zbx_got_discrete_states_cb()'s state-processing loop) are out of */
/* scope here. Also deliberately out of scope: every "sensor not found" branch, since all three call        */
/* THIS_SHOULD_NEVER_HAPPEN, which this test harness wires to _exit(1) (see zbxmocktest.c) - triggering it  */
/* would kill the test process rather than fail one case, and bypasses atexit() so it wouldn't even earn    */
/* coverage credit.                                                                                          */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

static int	dummy_sensor;

static ipmi_states_t	*make_states(int scanning_enabled, int initial_update)
{
	ipmi_states_t	*states = (ipmi_states_t *)zbx_malloc(NULL, ipmi_states_size());

	ipmi_init_states(states);
	ipmi_set_sensor_scanning_enabled(states, scanning_enabled);
	ipmi_set_initial_update_in_progress(states, initial_update);

	return states;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t		*h;
	zbx_ipmi_sensor_t	*s;
	const char		*cb = zbx_mock_get_parameter_string("in.cb");
	const char		*scenario = zbx_mock_get_parameter_string("in.scenario");
	ipmi_states_t		*states = NULL;

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	h = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");

	h->sensor_count = 1;
	h->sensors = (zbx_ipmi_sensor_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_sensor_t));
	s = &h->sensors[0];
	memset(s, 0, sizeof(*s));
	s->sensor = (ipmi_sensor_t *)&dummy_sensor;
	zbx_strlcpy(s->id, "s1", sizeof(s->id));

	h->done = 0;
	h->ret = SUCCEED;

	if (0 == strcmp(cb, "thresh_reading"))
	{
		int	err = 0;
		enum ipmi_value_present_e	vp = IPMI_NO_VALUES_PRESENT;
		ipmi_sensor_t			*sensor = s->sensor;

		if (0 == strcmp(scenario, "err"))
			err = 5;
		else if (0 == strcmp(scenario, "scanning_disabled"))
			states = make_states(0, 0);
		else if (0 == strcmp(scenario, "initial_update"))
			states = make_states(1, 1);
		else if (0 == strcmp(scenario, "no_values_present"))
			states = make_states(1, 0);
		else
			fail_msg("unknown 'in.scenario' value for thresh_reading: %s", scenario);

		zbx_got_thresh_reading_cb(sensor, err, vp, 0, 0.0, states, h);
	}
	else if (0 == strcmp(cb, "discrete_states"))
	{
		int	err = 0;
		ipmi_sensor_t	*sensor = s->sensor;

		if (0 == strcmp(scenario, "scanning_disabled"))
			states = make_states(0, 0);
		else if (0 == strcmp(scenario, "initial_update"))
			states = make_states(1, 1);
		else if (0 == strcmp(scenario, "err"))
		{
			states = make_states(1, 0);
			err = 5;
		}
		else
			fail_msg("unknown 'in.scenario' value for discrete_states: %s", scenario);

		zbx_got_discrete_states_cb(sensor, err, states, h);
	}
	else if (0 == strcmp(cb, "thresholds"))
	{
		if (0 != strcmp(scenario, "err"))
			fail_msg("only the 'err' scenario is covered for zbx_got_thresholds_cb() here");

		zbx_got_thresholds_cb(NULL, 5, NULL, h);
	}
	else
		fail_msg("unknown 'in.cb' value: %s", cb);

	zbx_mock_assert_int_eq("h->done", 1, h->done);
	zbx_mock_assert_int_eq("h->ret", zbx_mock_get_parameter_int("out.ret"), h->ret);

	if (SUCCEED != h->ret && (NULL == h->err || '\0' == *h->err))
		fail_msg("h->err should be set when h->ret indicates failure");

	zbx_free(states);
	zbx_free(h->err);
	zbx_free(h->sensors);
	zbx_hashset_remove(&hostset, &h);
	zbx_free(h->ip);
	zbx_free(h->username);
	zbx_free(h->password);
	zbx_free(h);
	zbx_hashset_destroy(&hostset);
}
