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

/* zbx_read_ipmi_thresholds() is static, so the source file is included directly to reach it. See         */
/* zbx_read_ipmi_sensor.c for the pointer-lifetime rationale - this covers the same class of regression    */
/* for the threshold-reading path: ipmi_sensor_id_get_thresholds() must reject an IPMI_SENSOR_ID_INVALID   */
/* sensor_id (a vanished sensor) without ever invoking its callback, exercised here against the real       */
/* library. thr_access is a cached, static field (see zbx_ipmi_sensor_t.thr_access), so it's just set      */
/* directly here rather than needing revalidation.                                                         */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t		h;
	zbx_ipmi_sensor_t	s;

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	memset(&h, 0, sizeof(h));
	h.ip = "127.0.0.1";
	h.port = 623;

	memset(&s, 0, sizeof(s));
	s.thr_access = zbx_mock_get_parameter_int("in.thr_access");
	/* stands in for a sensor OpenIPMI has already destroyed - see zbx_read_ipmi_sensor.c */
	s.sensor_id = (ipmi_sensor_id_t)IPMI_SENSOR_ID_INVALID;

	zbx_read_ipmi_thresholds(&h, &s);

	/* zbx_read_ipmi_thresholds() never touches h->ret; h->done stays 0 on every failure path taken here */
	zbx_mock_assert_int_eq("h.done", 0, h.done);

	zbx_free(h.err);
	zbx_hashset_destroy(&hostset);
}
