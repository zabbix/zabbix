/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "log.h"

#include "zbxmedia.h"

/* the callback code is the same as in httptest.c and httptest.h, would be nice to abstract it */

#ifdef HAVE_LIBCURL

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
ZBX_HTTPPAGE;

static ZBX_HTTPPAGE	page;

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	ZBX_UNUSED(userdata);

	/* first piece of data */
	if (NULL == page.data)
	{
		page.allocated = MAX(64, r_size);
		page.offset = 0;
		page.data = zbx_malloc(page.data, page.allocated);
	}

	zbx_strncpy_alloc(&page.data, &page.allocated, &page.offset, ptr, r_size);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

#define EZ_TEXTING_VALID_CHARS		"~=+\\/@#%.,:;!?()-_$&"	/* also " \r\n", a-z, A-Z, 0-9 */
#define EZ_TEXTING_DOUBLE_CHARS		"~=+\\/@#%"		/* these characters count as two */

#define EZ_TEXTING_LIMIT_USA		0
#define EZ_TEXTING_LIMIT_CANADA		1

#define EZ_TEXTING_LENGTH_USA		160
#define EZ_TEXTING_LENGTH_CANADA	136

#define EZ_TEXTING_TIMEOUT		15
#define EZ_TEXTING_API_URL		"https://app.eztexting.com/api/sending"

#endif	/* HAVE_LIBCURL */

/******************************************************************************
 *                                                                            *
 * Function: send_ez_texting                                                  *
 *                                                                            *
 * Purpose: send SMS using Ez Texting API                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED if message has been sent successfully                *
 *               FAIL otherwise                                               *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_ez_texting(const char *username, const char *password, const char *sendto,
		const char *message, const char *limit, char *error, int max_error_len)
{
#ifdef HAVE_LIBCURL

	const char	*__function_name = "send_ez_texting";

	int		ret = FAIL;
	int		max_message_len;
	int		i, len, opt, err;
	char		*sendto_digits = NULL, *message_ascii = NULL;
	char		*username_esc = NULL, *password_esc = NULL, *sendto_esc = NULL, *message_esc = NULL;
	char		postfields[MAX_STRING_LEN];
	CURL		*easy_handle = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sendto:'%s' message:'%s'", __function_name, sendto, message);

	assert(error);
	*error = '\0';

	memset(&page, 0, sizeof(page));

	/* replace UTF-8 and invalid ASCII characters, and make sure the message is not too long */

	switch (atoi(limit))
	{
		case EZ_TEXTING_LIMIT_USA:
			max_message_len = EZ_TEXTING_LENGTH_USA;
			break;
		case EZ_TEXTING_LIMIT_CANADA:
			max_message_len = EZ_TEXTING_LENGTH_CANADA;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_snprintf(error, max_error_len, "Could not determine proper length limit: [%s]", limit);
			goto clean;
	}

	if (NULL == (message_ascii = zbx_replace_utf8(message)))
	{
		zbx_snprintf(error, max_error_len, "Could not replace UTF-8 characters: [%s]", message);
		goto clean;
	}

	for (i = 0, len = 0; '\0' != message_ascii[i] && len < max_message_len; i++, len++)
	{
		if (' ' == message_ascii[i])
			continue;
		if ('a' <= message_ascii[i] && message_ascii[i] <= 'z')
			continue;
		if ('A' <= message_ascii[i] && message_ascii[i] <= 'Z')
			continue;
		if ('0' <= message_ascii[i] && message_ascii[i] <= '9')
			continue;

		if ('\t' == message_ascii[i])	/* \t is not part of GSM character set */
		{
			message_ascii[i] = ' ';
			continue;
		}
		if ('\r' == message_ascii[i])	/* line end counts as two, regardless of... */
		{
			if ('\n' != message_ascii[i + 1])
				len++;
			continue;
		}
		if ('\n' == message_ascii[i])	/* ... how it is specified: \r, \n, or \r\n */
		{
			if (0 < i && '\r' != message_ascii[i - 1])
				len++;
			continue;
		}
		if (NULL == (strchr(EZ_TEXTING_VALID_CHARS, message_ascii[i])))
		{
			message_ascii[i] = '?';
			continue;
		}
		if (NULL != (strchr(EZ_TEXTING_DOUBLE_CHARS, message_ascii[i])))
		{
			len++;
			continue;
		}
	}

	if (len > max_message_len)
		i--;

	message_ascii[i] = '\0';

	/* prepare and make cURL request to Ez Texting API */

	if (NULL == (easy_handle = curl_easy_init()))
	{
		zbx_snprintf(error, max_error_len, "Could not initialize cURL");
		goto clean;
	}

	sendto_digits = strdup(sendto);
	zbx_remove_chars(sendto_digits, "() -"); /* allow phone numbers to be specified like "(123) 456-7890" */

	if (NULL == (username_esc = curl_easy_escape(easy_handle, username, strlen(username))) ||
			NULL == (password_esc = curl_easy_escape(easy_handle, password, strlen(password))) ||
			NULL == (sendto_esc = curl_easy_escape(easy_handle, sendto_digits, strlen(sendto_digits))) ||
			NULL == (message_esc = curl_easy_escape(easy_handle, message_ascii, strlen(message_ascii))))
	{
		zbx_snprintf(error, max_error_len, "Could not URL encode POST fields");
		goto clean;
	}

	zbx_snprintf(postfields, sizeof(postfields), "user=%s&pass=%s&phonenumber=%s&subject=&message=%s",
			username_esc, password_esc, sendto_esc, message_esc);

	if (CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_USERAGENT, "Zabbix " ZABBIX_VERSION)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_SSL_VERIFYPEER, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_SSL_VERIFYHOST, 2L)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_POSTFIELDS, postfields)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_URL, EZ_TEXTING_API_URL)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_TIMEOUT, (long)EZ_TEXTING_TIMEOUT)))
	{
		zbx_snprintf(error, max_error_len, "Could not set cURL option %d: [%s]", opt, curl_easy_strerror(err));
		goto clean;
	}

	if (NULL != CONFIG_SOURCE_IP)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easy_handle, opt = CURLOPT_INTERFACE, CONFIG_SOURCE_IP)))
		{
			zbx_snprintf(error, max_error_len, "Could not set cURL option %d: [%s]",
					opt, curl_easy_strerror(err));
			goto clean;
		}
	}

	if (CURLE_OK != (err = curl_easy_perform(easy_handle)))
	{
		zbx_snprintf(error, max_error_len, "Error doing curl_easy_perform(): [%s]", curl_easy_strerror(err));
		goto clean;
	}

	/* parse the response */

	if (NULL == page.data || FAIL == is_int_prefix(page.data))
	{
		zbx_snprintf(error, max_error_len, "Did not receive a proper response: [%s]", ZBX_NULL2STR(page.data));
		goto clean;
	}

	switch (atoi(page.data))
	{
		case 1:
			ret = SUCCEED;
			break;
		case -1:
			zbx_snprintf(error, max_error_len, "Invalid user and/or password or API is not allowed");
			break;
		case -2:
			zbx_snprintf(error, max_error_len, "Credit limit reached");
			break;
		case -5:
			zbx_snprintf(error, max_error_len, "Locally opted out phone number");
			break;
		case -7:
			zbx_snprintf(error, max_error_len, "Message too long or contains invalid characters");
			break;
		case -104:
			zbx_snprintf(error, max_error_len, "Globally opted out phone number");
			break;
		case -106:
			zbx_snprintf(error, max_error_len, "Incorrectly formatted phone number");
			break;
		case -10:
			zbx_snprintf(error, max_error_len, "Unknown error (please contact Ez Texting)");
			break;
		default:
			zbx_snprintf(error, max_error_len, "Unknown return value: [%s]", page.data);
			break;
	}
clean:
	if (NULL != message_ascii)
		zbx_free(message_ascii);
	if (NULL != sendto_digits)
		zbx_free(sendto_digits);
	if (NULL != username_esc)
		zbx_free(username_esc);
	if (NULL != password_esc)
		zbx_free(password_esc);
	if (NULL != sendto_esc)
		zbx_free(sendto_esc);
	if (NULL != message_esc)
		zbx_free(message_esc);
	if (NULL != page.data)
		zbx_free(page.data);
	if (NULL != easy_handle)
		curl_easy_cleanup(easy_handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;

#else
	zbx_snprintf(error, max_error_len, "cURL library is required for Ez Texting support");
	return FAIL;

#endif	/* HAVE_LIBCURL */
}
