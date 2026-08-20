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

#include <pthread.h>

#include "zbxmocktest.h"
#include "zbxmockutil.h"

#include "zbxxml.h"
#include "zbxstr.h"
#include "zbxthreads.h"

#define ZBX_XML_HEADER_SIZE	22
#define ZBX_THREAD_STACK_SIZE	(128 * 1024)   /* stack size in B */

typedef struct
{
	const char *json;
	const char *expected_xml;
	int expected_result;

	int actual_result;
	char *xml;
	char *error;
} zbx_mock_thread_args_t;

static void *zbx_mock_test_thread(void *arg)
{
	zbx_mock_thread_args_t *t = (zbx_mock_thread_args_t *)arg;

	char	*json_copy = strdup(t->json);
	if (!json_copy)
	{
		return NULL;
	}

	t->actual_result = zbx_json_to_xml(json_copy, &t->xml, &t->error);

	free(json_copy);

	return NULL;
}

void zbx_mock_test_entry(void **state)
{
	pthread_t thread;
	pthread_attr_t attr;
	int err;

	ZBX_UNUSED(state);

	zbx_mock_thread_args_t args = {
		.json = zbx_mock_get_parameter_string("in.json"),
		.expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return")),
		.expected_xml = zbx_mock_get_parameter_string("out.xml"),
		.actual_result = 0,
		.xml = NULL,
		.error = NULL
	};

	zbx_pthread_init_attr(&attr);

	err = pthread_attr_setstacksize(&attr, ZBX_THREAD_STACK_SIZE);
	if (0 != err)
	{
		fail_msg("pthread_attr_setstacksize() failed: %s", zbx_strerror(err));
		return;
	}

	err = pthread_create(&thread, &attr, zbx_mock_test_thread, (void *)&args);
	if (0 != err)
	{
		fail_msg("cannot create thread: %s", zbx_strerror(err));
		return;
	}

	err = pthread_join(thread, NULL);
	if (0 != err)
	{
		fail_msg("pthread_join() failed: %s", zbx_strerror(err));
		return;
	}

	char *xml_content = args.xml;

	if (NULL != xml_content)
	{
		xml_content += ZBX_XML_HEADER_SIZE;
		zbx_rtrim(xml_content, "\r\n ");
	}

	if (args.actual_result != args.expected_result ||
		(NULL != args.xml && 0 != strcmp(args.expected_xml, xml_content)))
	{
#ifdef HAVE_LIBXML2
		fail_msg("Actual: %d \"%s\" != expected: %d \"%s\"",
			args.actual_result,
			xml_content,
			args.expected_result,
			args.expected_xml);
#else
		skip();
#endif
	}

	zbx_free(args.xml);
	zbx_free(args.error);
}
