/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "log.h"

#include "zbxmedia.h"

/* the callback code is the same as in httptest.c and httptest.h, would be nice to abstract it */

typedef struct
{
	char	*data;
	int	allocated;
	int	offset;
}
ZBX_HTTPPAGE;

static ZBX_HTTPPAGE	page;

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	/* first piece of data */
	if (NULL == page.data)
	{
		page.allocated = MAX(8096, r_size);
		page.offset = 0;
		page.data = zbx_malloc(page.data, page.allocated);
	}

	zbx_snprintf_alloc(&page.data, &page.allocated, &page.offset, MAX(8096, r_size), "%s", ptr);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	return size * nmemb;
}

#define EZ_TEXTING_VALID_CHARS	"~=+\\/@#%.,:;!?()-_$&"	/* also a-z, A-Z, and 0-9 */
#define EZ_TEXTING_DOUBLE_CHARS	"~=+\\/@#%\r\n"		/* chars that count as two */

#define EZ_TEXTING_TIMEOUT	15
#define EZ_TEXTING_MAX_LEN	136
#define EZ_TEXTING_API_URL	"https://app.eztexting.com/api/sending"

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
			const char *subject, const char *message, char *error, int max_error_len)
{
#ifdef HAVE_LIBCURL

	const char	*__function_name = "send_ez_texting";

	int		ret = FAIL;
	int		i, len, err;
	char		*postfields = NULL;
	char		*message_esc = NULL;
	char		postcontents[MAX_STRING_LEN];
	CURL		*easy_handle = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): sendto [%s] text [%s|%s]", __function_name, sendto, subject, message);

	assert(error);
	*error = '\0';

	memset(&page, 0, sizeof(page));

	/* replace UTF-8 and invalid ASCII characters, and make sure the message is not too long */

	if (NULL == (message_esc = zbx_replace_utf8(message, '?')))
	{
		zbx_snprintf(error, max_error_len, "Could not replace UTF-8 characters: [%s]", message);
		goto clean;
	}

	for (i = 0, len = 0; '\0' != message_esc[i] && len < EZ_TEXTING_MAX_LEN; i++, len++)
	{
		if ('a' <= message_esc[i] && message_esc[i] <= 'z')
			continue;
		if ('A' <= message_esc[i] && message_esc[i] <= 'Z')
			continue;
		if ('0' <= message_esc[i] && message_esc[i] <= '9')
			continue;
		if (NULL == (strchr(EZ_TEXTING_VALID_CHARS, message_esc[i])))
			message_esc[i] = '?';
		else if (NULL != (strchr(EZ_TEXTING_DOUBLE_CHARS, message_esc[i])))
			len++;
	}

	if (len > EZ_TEXTING_MAX_LEN)
		i--;

	message_esc[i] = '\0';

	/* prepare and make cURL request to Ez Texting API */

	zbx_snprintf(postcontents, sizeof(postcontents), "user=%s&pass=%s&phonenumber=%s&subject=&message=%s",
			username, password, sendto, message_esc);

	if (NULL == (easy_handle = curl_easy_init()))
	{
		zbx_snprintf(error, max_error_len, "Could not initialize cURL");
		goto clean;
	}

	if (NULL == (postfields = curl_easy_escape(easy_handle, postcontents, strlen(postcontents))))
	{
		zbx_snprintf(error, max_error_len, "Could not URL encode POST contents");
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_USERAGENT, "Zabbix " ZABBIX_VERSION)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_FOLLOWLOCATION, 1)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_SSL_VERIFYPEER, 0)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_SSL_VERIFYHOST, 0)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_POSTFIELDS, postfields)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_POST, 1)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_URL, EZ_TEXTING_API_URL)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_TIMEOUT, EZ_TEXTING_TIMEOUT)) ||
			CURLE_OK != (err = curl_easy_setopt(easy_handle, CURLOPT_CONNECTTIMEOUT, EZ_TEXTING_TIMEOUT)))
	{
		zbx_snprintf(error, max_error_len, "Could not set one of cURL options: [%s]", curl_easy_strerror(err));
		goto clean;
	}


	if (CURLE_OK != (err = curl_easy_perform(easy_handle)))
	{
		zbx_snprintf(error, max_error_len, "Error doing curl_easy_perform(): [%s]", curl_easy_strerror(err));
		goto clean;
	}

	/* parse the response */

	if (NULL == page.data || FAIL == is_int_prefix(page.data))
	{
		zbx_snprintf(error, max_error_len, "Did not receive a proper response: [%s]",
				NULL == page.data ? "(null)" : page.data);
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
	if (NULL != message_esc)
		zbx_free(message_esc);
	if (NULL != page.data)
		zbx_free(page.data);
	if (NULL != postfields)
		curl_free(postfields);
	if (NULL != easy_handle)
		curl_easy_cleanup(easy_handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __function_name, zbx_result_string(ret));

	return ret;

#else
	zbx_snprintf(error, max_error_len, "cURL library is required for Ez Texting support");
	return FAIL;

#endif	/* HAVE_LIBCURL */
}
