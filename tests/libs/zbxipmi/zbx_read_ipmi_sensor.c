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

/* zbx_read_ipmi_sensor() is static, so the source file is included directly to reach it. */
/*                                                                                                        */
/* Regression coverage for the OpenIPMI pointer-lifetime rule documented in <OpenIPMI/ipmiif.h> and in    */
/* IPMI.pdf section 2.2.4: "The pointer is only valid inside a callback... the id is good outside         */
/* callbacks." zbx_ipmi_sensor_t caches a raw ipmi_sensor_t* (needed only for identity matching inside    */
/* real OpenIPMI callbacks) alongside an ipmi_sensor_id_t, and zbx_read_ipmi_sensor() must only ever      */
/* issue a *new* read through the latter - via ipmi_sensor_id_get_reading()/get_states(), which           */
/* internally revalidate the id through ipmi_sensor_pointer_cb() - never via the former from outside a    */
/* callback. The sensor's static properties (reading_type_str and friends) are a separate matter: per the */
/* same section of IPMI.pdf ("collect all the static information that you need from an object when it is */
/* reported to you"), those are captured once in zbx_allocate_ipmi_sensor() and simply read back here, no */
/* revalidation needed - case 3 below exercises that a cached string is used as-is.                       */
/*                                                                                                         */
/* Case 1 and 2 simulate a sensor OpenIPMI has since destroyed (e.g. entity/sensor removed, or its domain  */
/* closed) via IPMI_SENSOR_ID_INVALID, the same way the ZBX-25759 cb_data scenario is simulated in         */
/* zbx_domain_closed_cb.c. Real OpenIPMI (not mocked) is exercised - ipmi_sensor_id_get_reading()/         */
/* get_states() reject the invalid id synchronously with an error, without ever invoking their callback    */
/* (verified against the real library, not assumed).                                                       */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t		h;
	zbx_ipmi_sensor_t	s;
	int			reading_type = zbx_mock_get_parameter_int("in.reading_type");

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	memset(&h, 0, sizeof(h));
	h.ip = "127.0.0.1";
	h.port = 623;

	memset(&s, 0, sizeof(s));
	s.reading_type = reading_type;
	s.reading_type_str = "unassigned";	/* as if cached at allocation time - see the file header comment */
	/* stands in for a sensor OpenIPMI has already destroyed - see the file header comment */
	s.sensor_id = (ipmi_sensor_id_t)IPMI_SENSOR_ID_INVALID;

	zbx_read_ipmi_sensor(&h, &s);

	zbx_mock_assert_int_eq("h.ret", NOTSUPPORTED, h.ret);

	if (NULL == h.err || '\0' == *h.err)
		fail_msg("h.err should describe why the sensor could not be read, got %s",
				NULL == h.err ? "NULL" : "an empty string");

	zbx_free(h.err);
	zbx_hashset_destroy(&hostset);
}
