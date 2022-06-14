/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockutil.h"

#define ZBX_XML_HEADER_SIZE	22

void	zbx_mock_test_entry(void **state)
{
	char	*json, *expected_xml, *xml_content, *xml = NULL, *error = NULL;
	int	actual_result, expected_result;

	ZBX_UNUSED(state);

	json = (char *)zbx_mock_get_parameter_string("in.json");
	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	expected_xml = (char *)zbx_mock_get_parameter_string("out.xml");
	actual_result = zbx_json_to_xml(json, &xml, &error);
	xml_content = xml;
	if (NULL != xml)
	{
		xml_content += ZBX_XML_HEADER_SIZE;
		zbx_rtrim(xml_content, "\r\n ");
	}

	if (actual_result != expected_result || ( NULL != xml && 0 != strcmp(expected_xml, xml_content)))
	{
#ifdef HAVE_LIBXML2
		fail_msg("Actual: %d \"%s\" != expected: %d \"%s\"", actual_result, xml_content, expected_result,
				expected_xml);
#else
		skip();
#endif
	}
	zbx_free(xml);
	zbx_free(error);
}
