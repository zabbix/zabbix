/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_WEBDRIVER_H
#define ZABBIX_WEBDRIVER_H

#include "zbxembed.h"
#include "embed.h"
#include "browser_perf.h"
#include "zbxalgo.h"
#include "zbxtime.h"

typedef struct
{
	int	http_code;
	char	*error;
	char	*message;

}
zbx_wd_error_t;

typedef struct
{
	CURL			*handle;
	struct curl_slist	*headers;
	char			*data;
	char			*headers_in;
	size_t			data_alloc;
	size_t			data_offset;
	size_t			headers_in_alloc;
	size_t			headers_in_offset;

	char			*endpoint;
	char			*session;

	int			screen_width;
	int			screen_height;

	zbx_wd_perf_t		perf;
	int			refcount;
	char			*bookmark;

	double			create_time;

	char			*last_error_message;
	zbx_wd_error_t		*error;

	void			*browser;
}
zbx_webdriver_t;

zbx_webdriver_t	*webdriver_create(const char *endpoint, const char *sourceip, char **error);
void	webdriver_destroy(zbx_webdriver_t *wd);
void	webdriver_release(zbx_webdriver_t *wd);
zbx_webdriver_t	*webdriver_addref(zbx_webdriver_t *wd);

int	webdriver_open_session(zbx_webdriver_t *wd, const char *capabilities, char **error);
int	webdriver_url(zbx_webdriver_t *wd, const char *url, char **error);
int	webdriver_get_url(zbx_webdriver_t *wd, char **url, char **error);

int	webdriver_find_element(zbx_webdriver_t *wd, const char *strategy, const char *selector, char **element,
		char **error);
int	webdriver_find_elements(zbx_webdriver_t *wd, const char *strategy, const char *selector,
		zbx_vector_str_t *elements, char **error);

int	webdriver_send_keys_to_element(zbx_webdriver_t *wd, const char *element, const char *keys, char **error);
int	webdriver_click_element(zbx_webdriver_t *wd, const char *element, char **error);
int	webdriver_clear_element(zbx_webdriver_t *wd, const char *element, char **error);
int	webdriver_get_element_info(zbx_webdriver_t *wd, const char *element, const char *info, const char *name,
		char **value, char **error);

int	webdriver_set_timeouts(zbx_webdriver_t *wd, int script_timeout, int page_load_timeout, int implicit_timeot,
		char **error);

int	webdriver_get_cookies(zbx_webdriver_t *wd, char **cookies, char **error);
int	webdriver_add_cookie(zbx_webdriver_t *wd, const char *cookie, char **error);

int	webdriver_get_screenshot(zbx_webdriver_t *wd, char **screenhost, char **error);
int	webdriver_set_screen_size(zbx_webdriver_t *wd, int width, int height, char **error);

void	webdriver_discard_error(zbx_webdriver_t *wd);

int	webdriver_execute_script(zbx_webdriver_t *wd, const char *script, char **error);

int	webdriver_get_page_source(zbx_webdriver_t *wd, char **source, char **error);
int	webdriver_has_error(zbx_webdriver_t *wd);
void	webdriver_set_error(zbx_webdriver_t *wd, char *message);

#endif
