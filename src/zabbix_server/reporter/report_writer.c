/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "common.h"
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "zbxjson.h"

#include "report_writer.h"
#include "report_protocol.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

extern char *CONFIG_WEBSERVICE_URL;

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
zbx_string_t;

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t		r_size = size * nmemb;
	zbx_string_t	*str = (zbx_string_t *)userdata;

	zbx_strncpy_alloc(&str->data, &str->alloc, &str->offset, (const char *)ptr, r_size);

	return r_size;
}

static int	rw_get_report(const char *url, const char *cookie, const char *width, const char *height, char **report,
		size_t *report_size, char **error)
{
#if !defined(HAVE_LIBCURL)
	ZBX_UNUSED(url);
	ZBX_UNUSED(cookie);
	ZBX_UNUSED(width);
	ZBX_UNUSED(height);
	ZBX_UNUSED(report);
	ZBX_UNUSED(report_size);

	*error = zbx_strdup(NULL, "application compiled without cURL library");
	return FAIL;

#else
	struct zbx_json		j;
	char			*cookie_value;
	int			ret = FAIL;
	long			httpret;
	zbx_string_t		response = {NULL, 0, 0};
	CURL			*curl = NULL;
	CURLcode		err;
	CURLoption		opt;
	struct curl_slist	*headers = NULL;

	cookie_value = zbx_dsprintf(NULL, "zbx_session=%s", cookie);

	zbx_json_init(&j, 1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_URL, url, ZBX_JSON_TYPE_STRING);

	zbx_json_addobject(&j, ZBX_PROTO_TAG_HTTP_HEADERS);
	zbx_json_addstring(&j, "Cookie", cookie_value, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&j);

	zbx_json_addobject(&j, ZBX_PROTO_TAG_PARAMETERS);
	zbx_json_addstring(&j, "width", width, ZBX_JSON_TYPE_INT);
	zbx_json_addstring(&j, "height", height, ZBX_JSON_TYPE_INT);
	zbx_json_close(&j);

	if (NULL == (curl = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "cannot initialize cURL library");
		goto out;
	}

	headers = curl_slist_append(headers, "Content-Type:application/json");

	if (CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_WRITEFUNCTION, curl_write_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_WRITEDATA, &response)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_TIMEOUT, 60)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_URL, CONFIG_WEBSERVICE_URL)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_HTTPHEADER, headers)) ||
			CURLE_OK != (err = curl_easy_setopt(curl, opt = CURLOPT_POSTFIELDS, j.buffer)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(curl)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &httpret)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "REPORT %s -> http: %d", CONFIG_WEBSERVICE_URL, httpret);
	if (200 != httpret)
	{
		struct zbx_json_parse	jp;

		zbx_json_open(response.data, &jp);

		*error = response.data;
		response.data = NULL;
		goto out;
	}

	*report = response.data;
	*report_size = response.offset;
	response.data = NULL;

	ret = SUCCEED;
out:
	zbx_free(response.data);

	curl_slist_free_all(headers);

	if (NULL != curl)
		curl_easy_cleanup(curl);

	zbx_json_clean(&j);
	zbx_free(cookie_value);

	return ret;
#endif
}

static int	rw_send_emails(const zbx_vector_str_t *emails, const char *subject, const char *message,
		const char *format, const char *report, size_t report_size, char **error)
{
	FILE	*fp;

	if (NULL != (fp = fopen("/tmp/page.pdf", "w")))
	{
		fwrite(report, 1, report_size, fp);
		fclose(fp);
	}

	// TODO: send emails
	*error = zbx_strdup(NULL, "Failed to send emails: not implemented (writer).");
	return FAIL;
}

static void	rw_send_report(zbx_ipc_socket_t *socket, zbx_ipc_message_t *msg)
{
	char			*url, *cookie, *error = NULL, *report = NULL;
	const char		*subject = NULL, *message = NULL, *format = NULL, *width = NULL, *height = NULL;
	unsigned char		*data;
	size_t			size, report_size;
	int			i, ret;
	zbx_vector_str_t	emails;
	zbx_vector_ptr_pair_t	params;

	zbx_vector_str_create(&emails);
	zbx_vector_ptr_pair_create(&params);

	report_deserialize_send_request(msg->data, &url, &cookie, &emails, &params);

	for (i = 0; i < params.values_num; i++)
	{
		if (0 == strcmp(params.values[i].first, "subject"))
		{
			subject = params.values[i].second;
		}
		else if (0 == strcmp(params.values[i].first, "message"))
		{
			message = params.values[i].second;
		}
		else if (0 == strcmp(params.values[i].first, "format"))
		{
			format = params.values[i].second;
		}
		else if (0 == strcmp(params.values[i].first, "width"))
		{
			width = params.values[i].second;
		}
		else if (0 == strcmp(params.values[i].first, "height"))
		{
			height = params.values[i].second;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "unsupported paramter: %s=%s", (char *)params.values[i].first,
					(char *)params.values[i].second);
		}
	}

	if (SUCCEED != (ret = rw_get_report(url, cookie, width, height, &report, &report_size, &error)))
		goto out;

	ret = rw_send_emails(&emails, subject, message, format, report, report_size, &error);

out:
	size = report_serialize_response(&data, ret, error);
	zbx_ipc_socket_write(socket, ZBX_IPC_REPORTER_SEND_REPORT_RESULT, data, size);

	zbx_free(report);
	zbx_free(data);
	zbx_free(error);
	zbx_free(report);
	zbx_free(cookie);
	zbx_free(url);
	report_destroy_params(&params);
	zbx_vector_str_clear_ext(&emails, zbx_str_free);
	zbx_vector_str_destroy(&emails);
}

ZBX_THREAD_ENTRY(report_writer_thread, args)
{
	pid_t			ppid;
	char			*error = NULL;
	zbx_ipc_socket_t	socket;
	zbx_ipc_message_t	message;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_REPORTER, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to reporting service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ppid = getppid();
	zbx_ipc_socket_write(&socket, ZBX_IPC_REPORTER_REGISTER, (unsigned char *)&ppid, sizeof(ppid));

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED != zbx_ipc_socket_read(&socket, &message))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot read reporter service request");
			exit(EXIT_FAILURE);
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		zbx_update_env(zbx_time());

		switch (message.code)
		{
			case ZBX_IPC_REPORTER_SEND_REPORT:
				rw_send_report(&socket, &message);
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
