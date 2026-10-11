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

/* zbx_read_ipmi_sensor() is static, so the source file is included directly to reach it.                   */
/*                                                                                                          */
/* Regression coverage for the OpenIPMI pointer-lifetime rule documented in <OpenIPMI/ipmiif.h> and in      */
/* IPMI.pdf section 2.2.4: "The pointer is only valid inside a callback... the id is good outside           */
/* callbacks." zbx_ipmi_sensor_t therefore keeps only an ipmi_sensor_id_t (sensor_id), never an             */
/* ipmi_sensor_t pointer, and zbx_read_ipmi_sensor() issues a new read through it via                       */
/* ipmi_sensor_id_get_reading()/ipmi_sensor_id_get_states(), which internally revalidate the id through     */
/* ipmi_sensor_pointer_cb(). The sensor's static properties (reading_type_str and friends) are a separate   */
/* matter: per the same section of IPMI.pdf ("collect all the static information that you need from an      */
/* object when it is reported to you"), those are captured once in zbx_allocate_ipmi_sensor() and simply    */
/* read back here, no revalidation needed - case 3 below exercises that a cached string is used as-is.      */
/*                                                                                                          */
/* Real OpenIPMI (not mocked) is exercised, but this test process never calls ipmi_init(), so               */
/* ipmi_sensor_id_get_reading()/ipmi_sensor_id_get_states() fail synchronously with ECANCELED (0x7d)        */
/* before even looking at the id, without ever invoking their callback - sensor_id is set to                */
/* IPMI_SENSOR_ID_INVALID only so it is never mistaken for a usable one. 'out.err_contains' pins down the   */
/* exact wording each branch produces (naming the specific OpenIPMI call that failed, or - for case 3 -     */
/* the cached reading_type_str), so a case can only pass if the matching switch branch actually ran, not    */
/* just any branch that happens to also set h->ret to NOTSUPPORTED.                                         */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t		h;
	zbx_ipmi_sensor_t	s;
	char			reading_type_str[] = "unassigned";
	int			reading_type = zbx_mock_get_parameter_int("in.reading_type");
	const char		*err_contains = zbx_mock_get_parameter_string("out.err_contains");

	ZBX_UNUSED(state);

	memset(&h, 0, sizeof(h));
	h.ip = "127.0.0.1";
	h.port = 623;

	memset(&s, 0, sizeof(s));
	s.reading_type = reading_type;
	s.reading_type_str = reading_type_str;	/* as if cached at allocation time - see the file header comment */

	/* never a usable id - see the file header comment */
	s.sensor_id = (ipmi_sensor_id_t)IPMI_SENSOR_ID_INVALID;

	zbx_read_ipmi_sensor(&h, &s);

	zbx_mock_assert_int_eq("h.ret", NOTSUPPORTED, h.ret);

	if (NULL == h.err || NULL == strstr(h.err, err_contains))
	{
		fail_msg("h.err should mention '%s' (proving the matching switch branch actually ran), got: %s",
				err_contains, NULL == h.err ? "NULL" : h.err);
	}

	zbx_free(h.err);
}
