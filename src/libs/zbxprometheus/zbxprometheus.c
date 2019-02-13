/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "zbxprometheus.h"

#define SKIP_LEADING_WS_HTAB(s)						\
	do {								\
		while (*(s) && (*(s) == ' ' || *(s) == '\t')) (s)++;	\
	} while(0)

#define CUT_TRAILING_WS_HTAB(s)						\
	do {								\
		char *eol = (s) + strlen(s);				\
		while (eol > (s))					\
		{							\
			if (*(eol - 1) != ' ' && *(eol - 1) != '\t')	\
				break;					\
			eol--;						\
		}							\
		*eol = '\0';						\
	} while(0)

#define ZBX_PROMETH_RE_OVECCOUNT	30

struct zbx_prometh_list_elem
{
	struct zbx_prometh_list_elem *next;
	void *data;
};

typedef struct
{
	struct zbx_prometh_list_elem *head;
}
zbx_prometh_list_t;

typedef enum
{
	zbx_prometh_item_metric_name = 1,
	zbx_prometh_item_metric_value,
	zbx_prometh_item_label_name
}
zbx_prometh_item_t;

struct zbx_prometh_filter_node
{
	zbx_prometh_item_t			item;
	char					*name;
	int	(*name_check)(char *pattern, char *data);
	char					*value;
	int	(*value_check)(char *pattern, char *data);
};

struct zbx_prometh_label
{
	char *label_name;
	char *label_value;
};

struct zbx_prometh_metric
{
	char *name;
	char *help;
	char *type;
	char *value;
	char *line_raw;
	zbx_prometh_list_t	labels;
};

static void	zbx_prometh_list_init(zbx_prometh_list_t *l)
{
	memset(l, 0, sizeof(*l));
	l->head = NULL;
}

static void	zbx_prometh_list_append_elem(zbx_prometh_list_t *l, void *data)
{
	struct zbx_prometh_list_elem *pelem = (struct zbx_prometh_list_elem *)zbx_malloc(NULL, sizeof(*pelem));

	pelem->data = data;
	pelem->next = NULL;

	if (NULL == l->head)
	{
		l->head = pelem;
	}
	else
	{
		struct zbx_prometh_list_elem *p = l->head;

		while (NULL != p->next)
			p = p->next;

		p->next = pelem;
	}
}

static int	zbx_prometh_list_remove_elem(zbx_prometh_list_t *l, struct zbx_prometh_list_elem *elem)
{
	if (l->head == elem)
	{
		l->head = l->head->next;
	}
	else
	{
		struct zbx_prometh_list_elem *p = l->head;
		int found = 0;

		while (NULL != p)
		{
			if (elem == p->next)
			{
				found = 1;
				break;
			}

			p = p->next;
		}

		if (!found)
			return FAIL;

		p->next = elem->next;
		elem->next = NULL;
	}

	return SUCCEED;
}

static int	zbx_prometh_list_remove_first_elem(zbx_prometh_list_t *l, struct zbx_prometh_list_elem **elem)
{
	struct zbx_prometh_list_elem *p;

	if (NULL == l->head)
	{
		*elem = NULL;
		return FAIL;
	}

	p = l->head;
	l->head = l->head->next;
	*elem = p;

	return SUCCEED;
}

static int	zbx_prometh_list_remove_last_elem(zbx_prometh_list_t *l, struct zbx_prometh_list_elem **elem)
{
	struct zbx_prometh_list_elem *p, *pprev = NULL;

	if (NULL == l->head)
	{
		*elem = NULL;
		return FAIL;
	}

	p = l->head;

	while (NULL != p->next)
	{
		pprev = p;
		p = p->next;
	}

	if (NULL != pprev)
		pprev->next = NULL;
	else
		l->head = NULL;

	*elem = p;

	return SUCCEED;
}

static struct zbx_prometh_metric	*zbx_prometh_get_last_metric (zbx_prometh_list_t *l)
{
	struct zbx_prometh_list_elem *p;

	if (NULL == l || NULL == l->head)
		return NULL;

	for (p = l->head; NULL != p->next; p = p->next);

	return (struct zbx_prometh_metric *)p->data;
}

static void	zbx_prometh_filter_node_add (zbx_prometh_list_t *list, struct zbx_prometh_filter_node *pnode)
{
	struct zbx_prometh_filter_node *p;

	p = (struct zbx_prometh_filter_node *)zbx_malloc(NULL, sizeof(*p));

	*p = *pnode;

	zbx_prometh_list_append_elem(list, (void *)p);
}

static struct zbx_prometh_list_elem	*zbx_prometh_list_seek_head(zbx_prometh_list_t *l)
{
	return l->head;
}

static struct zbx_prometh_list_elem	*zbx_prometh_list_seek_next(struct zbx_prometh_list_elem *elem)
{
	return elem->next;
}

static void	*zbx_prometh_list_peek(struct zbx_prometh_list_elem *elem)
{
	return elem->data;
}

static int	zbx_prometh_list_get_elem_count (zbx_prometh_list_t *l)
{
	struct zbx_prometh_list_elem *p = l->head;
	int count = 0;

	while (NULL != p)
	{
		++count;
		p = p->next;
	}

	return count;
}

static void	zbx_prometh_list_copy (zbx_prometh_list_t *dst, zbx_prometh_list_t *src)
{
	struct zbx_prometh_list_elem *p;

	while (SUCCEED == zbx_prometh_list_remove_first_elem(src, &p))
	{
		zbx_prometh_list_append_elem(dst, p->data);
		zbx_free(p);
	}
}

static int	zbx_prometh_compare_regex(char *pattern, char *s)
{
	pcre	*zbx_prometh_re;
	const char	*errmsg;
	int	erroffset, ovector[ZBX_PROMETH_RE_OVECCOUNT], ret;

	if (*pattern == '~')
		++pattern;

	if (*pattern == '\"')
		++pattern;

	if (pattern[strlen(pattern)-1] == '\"')
		pattern[strlen(pattern)-1] = '\0';

	if (NULL == (zbx_prometh_re = pcre_compile(pattern, 0, &errmsg, &erroffset, NULL)))
		return 0;

	ret = pcre_exec(zbx_prometh_re, NULL, s, (int)strlen(s), 0, 0, ovector, ZBX_PROMETH_RE_OVECCOUNT);

	ret = (0 > ret) ? 0 : 1;

	pcre_free(zbx_prometh_re);

	return ret;
}

static int	zbx_prometh_compare_strings(char *pattern, char *s)
{
	if (!strcmp(pattern, s))
		return 1;

	return 0;
}

static void zbx_prometh_clear_leading_trailing_ws(char **dst, char *src)
{
	size_t len;

	SKIP_LEADING_WS_HTAB(src);

	CUT_TRAILING_WS_HTAB(src);

	len = strlen(src);

	*dst = (char *)zbx_malloc(NULL, (len + 1));
	zbx_strlcpy(*dst, src, (len + 1));
	zbx_free(src);
}

static void	zbx_prometh_get_separated(char *str, char separator,  char **left, char **right)
{
	char *p, *s = str, *tmp;

	p = strchr(s, (int)separator);

	if (NULL != p)
	{
		tmp = (char *)zbx_malloc(NULL, (p - s + 1));
		zbx_strlcpy(tmp, s, (p - s + 1));
		zbx_prometh_clear_leading_trailing_ws(left, tmp);

		++p;

		if (strlen(p))
		{
			tmp = (char *)zbx_malloc(NULL, (strlen(p) + 1));
			zbx_strlcpy(tmp, p, (strlen(p) + 1));
			zbx_prometh_clear_leading_trailing_ws(right, tmp);
		}
		else
		{
			/* place an empty string */
			*right = (char *)zbx_malloc(NULL, 1);
			**right = '\0';
		}
	}
	else
	{
		tmp = (char *)zbx_malloc(NULL, (strlen(s) + 1));
		zbx_strlcpy(tmp, s, (strlen(s) + 1));
		zbx_prometh_clear_leading_trailing_ws(left, tmp);
		*right = NULL;
	}
	zbx_free(str);
}

static void	zbx_prometh_process_label_pair (struct zbx_prometh_metric *metric, char *s)
{
	char *left, *right;
	struct zbx_prometh_label *label;

	zbx_prometh_get_separated(s, '=', &left, &right);

	label = (struct zbx_prometh_label *)zbx_malloc(NULL, sizeof(*label));
	label->label_name = left;
	label->label_value = right;

	zbx_prometh_list_append_elem(&metric->labels, (void *)label);
}

static void	zbx_prometh_parse_labels (struct zbx_prometh_metric *metric, char *s)
{
	char *left, *right;

	zbx_prometh_list_init(&metric->labels);

	do
	{
		zbx_prometh_get_separated(s, ',', &left, &right);
		zbx_prometh_process_label_pair(metric, left);
		s = right;
	} while (NULL != right);
}

static struct zbx_prometh_metric	*zbx_prometh_allocate_metric (void)
{
	struct zbx_prometh_metric *p;

	if (NULL != (p = (struct zbx_prometh_metric *)zbx_malloc(NULL, (sizeof(*p)))))
		memset(p, 0, sizeof(*p));

	return p;
}

static void	zbx_prometh_parse_metric(struct zbx_prometh_metric *metric, char *str)
{
	char *name=NULL, *help=NULL, *type=NULL, *value=NULL, *line_raw=NULL;

	SKIP_LEADING_WS_HTAB(str);

	if (*str == '#')
	{
		++str;

		SKIP_LEADING_WS_HTAB(str);

		if (!memcmp(str, "HELP", strlen("HELP")))
		{
			char *p;

			str += strlen("HELP");

			SKIP_LEADING_WS_HTAB(str);

			p = str;

			while (*str != ' ' && *str != '\t') str++;

			name = (char *)zbx_malloc(NULL, (str - p + 1));
			zbx_strlcpy(name, p, (str - p + 1));

			SKIP_LEADING_WS_HTAB(str);

			p = str;

			while (*str++);

			help = (char *)zbx_malloc(NULL, (str - p + 1));
			zbx_strlcpy(help, p, (str - p + 1));
		}
		else if (!memcmp(str, "TYPE", strlen("TYPE")))
		{
			char *p;

			str += strlen("TYPE");

			SKIP_LEADING_WS_HTAB(str);

			p = str;

			while (*str != ' ' && *str != '\t') str++;

			name = (char *)zbx_malloc(NULL, (str - p + 1));
			zbx_strlcpy(name, p, (str - p + 1));

			SKIP_LEADING_WS_HTAB(str);

			p = str;

			while (*str++);

			type = (char *)zbx_malloc(NULL, (str - p + 1));
			zbx_strlcpy(type, p, (str - p + 1));
		}
	}
	else
	{
		char *p = str, *b = str, *substr;

		while (*str != ' ' && *str != '\t' && *str != '{') str++;

		name = (char *)zbx_malloc(NULL, (str - p + 1));
		zbx_strlcpy(name, p, (str - p + 1));

		SKIP_LEADING_WS_HTAB(str);

		if (*str == '{')
		{
			p = ++str;

			while (*str++ != '}');

			substr = (char *)zbx_malloc(NULL, (str - p));
			zbx_strlcpy(substr, p, (str - p));

			zbx_prometh_parse_labels(metric, substr);
		}

		SKIP_LEADING_WS_HTAB(str);

		p = str;

		/* getting only the value, not yet interested in timestamp */
		while (*str && *str != ' ' && *str != '\t') str++;

		value = (char *)zbx_malloc(NULL, (str - p + 1));
		zbx_strlcpy(value, p, (str - p + 1));

		p = str = b;

		while (*str++);

		line_raw = (char *)zbx_malloc(NULL, (str - p));
		zbx_strlcpy(line_raw, p, (str - p));
	}

	/* update metric */
	metric->name = name;
	metric->help = help;
	metric->type = type;
	metric->value = value;
	metric->line_raw = line_raw;
}

static void	zbx_prometh_data_prepare(zbx_prometh_list_t *in_data, char *str)
{
	zbx_prometh_list_t	metrics;
	char *p = str;

	/* TODO: skip empty strings */

	zbx_prometh_list_init(in_data);
	zbx_prometh_list_init(&metrics);

	while (*str)
	{
		struct zbx_prometh_metric	*new_metric, *last_metric;
		char *c;

		/* get metric substring */
		while (*p++ != '\n');
		c = (char *)zbx_malloc(NULL, (p - str));
		zbx_strlcpy(c, str, (p - str));
		/* now c is a new metric string */

		/* allocate new metric structure */
		new_metric = zbx_prometh_allocate_metric();

		zbx_prometh_parse_metric(new_metric, c);

		if (NULL != (last_metric = zbx_prometh_get_last_metric(&metrics)))
		{
			if (strcmp(last_metric->name, new_metric->name))
			{
				zbx_prometh_list_copy(in_data, &metrics);
				zbx_prometh_list_init(&metrics);
			}
			else
			{
					/* *** *** SIGSEGV workaround *** *** */
					if (NULL != new_metric->type && !strcmp(new_metric->type, "histogram"))
					{
						char *tmp = malloc(1);
						*tmp = '\0';
						new_metric->value = tmp;
					}
					/* *** end of SIGSEGV workaround *** */

				if (NULL != last_metric->help)
					new_metric->help = last_metric->help;

				if (NULL != last_metric->type)
					new_metric->type = last_metric->type;

				if (NULL == last_metric->value)
				{
					struct zbx_prometh_list_elem *elem;

					if (SUCCEED == zbx_prometh_list_remove_last_elem(&metrics, &elem))
					{
						if (NULL != elem->data)
							zbx_free(elem->data);

						zbx_free(elem);
					}
				}
			}
		}

		zbx_prometh_list_append_elem(&metrics, (void *)new_metric);

		free(c);
		str = p; /* next metric string */
	}

	/* don't let the last metric bucket remain in metrics list */
	zbx_prometh_list_copy(in_data, &metrics);
}

static void	zbx_prometh_parse_filter_substring (zbx_prometh_list_t *pf, char *s)
{
	struct zbx_prometh_filter_node fn;
	char *left, *right, *l, *r;

	do
	{
		zbx_prometh_get_separated(s, ',', &left, &right);

		zbx_prometh_get_separated(left, '=', &l, &r);

		if (strcmp(l, "__name__"))
		{
			memset(&fn, 0, sizeof(fn));
			fn.item = zbx_prometh_item_label_name;
			fn.name = l;
			fn.name_check = zbx_prometh_compare_strings;
			fn.value = r;
			fn.value_check = (*r == '~') ? zbx_prometh_compare_regex : zbx_prometh_compare_strings;
			zbx_prometh_filter_node_add(pf, &fn);
		}
		else
		{
			memset(&fn, 0, sizeof(fn));
			fn.item = zbx_prometh_item_metric_name;
			fn.name = r;
			fn.name_check =
				(*r == '~') ? zbx_prometh_compare_regex : zbx_prometh_compare_strings;
			fn.value = NULL;
			fn.value_check = NULL;
			zbx_prometh_filter_node_add(pf, &fn);
			zbx_free(l);
		}

		s = right;
	} while (NULL != right);
}

static int zbx_prometh_filter_create (zbx_prometh_list_t *pf, char *str)
{
	char *p, *metric_value, *metric_name, *substr;
	struct zbx_prometh_filter_node fn;
	int count;

	zbx_prometh_list_init(pf);

	/* prepare value */
	p = strstr(str, "==");

	if (NULL != p)
	{
		p += strlen("==");

		SKIP_LEADING_WS_HTAB(p);

		CUT_TRAILING_WS_HTAB(p);

		metric_value = (char *)zbx_malloc(NULL, (strlen(p) + 1));
		zbx_strlcpy(metric_value, p, (strlen(p) + 1));

		memset(&fn, 0, sizeof(fn));
		fn.item = zbx_prometh_item_metric_value;
		fn.name = metric_value;
		fn.name_check = zbx_prometh_compare_strings;
		fn.value = NULL;
		fn.value_check = NULL;
		zbx_prometh_filter_node_add(pf, &fn);
	}

	/* prepare name */
	SKIP_LEADING_WS_HTAB(str);

	p = str;

	if (*p != '{')
	{
		count = 0;

		while (*p && *p != '{' && *p != ' ' && *p != '\t')
		{
			p++;
			count++;
		}

		metric_name = (char *)zbx_malloc(NULL, (count + 1));
		zbx_strlcpy(metric_name, str, (count + 1));
		/* TODO: prometheus format restrictions! */
		memset(&fn, 0, sizeof(fn));
		fn.item = zbx_prometh_item_metric_name;
		fn.name = metric_name;
		fn.name_check = zbx_prometh_compare_strings;
		fn.value = NULL;
		fn.value_check = NULL;
		zbx_prometh_filter_node_add(pf, &fn);
	}

	SKIP_LEADING_WS_HTAB(p);

	if (*p == '{')
	{
		str = ++p;
		count = 0;

		while (*p && *p != '}')
		{
			p++;
			count++;
		}

		if (*p != '}')
		{
			/* ERROR: format */
		}

		substr = (char *)zbx_malloc(NULL, (count + 1));
		zbx_strlcpy(substr, str, (count + 1));

		zbx_prometh_parse_filter_substring(pf, substr);
	}

	return SUCCEED;
}


static void zbx_prometh_process_data (zbx_prometh_list_t *in_data, zbx_prometh_list_t *filter,
			zbx_prometh_list_t *results)
{
	struct zbx_prometh_list_elem *delem = zbx_prometh_list_seek_head(in_data);

	zbx_prometh_list_init(results);

	while (NULL != delem)
	{
		struct zbx_prometh_list_elem	*felem, *pm;
		struct zbx_prometh_metric	*pdata;
		int no_match = 0;

		pdata = (struct zbx_prometh_metric *)zbx_prometh_list_peek(delem);

		felem = zbx_prometh_list_seek_head(filter);

		pm = NULL;

		while (felem)
		{
			struct zbx_prometh_filter_node	*pf =
				(struct zbx_prometh_filter_node *)zbx_prometh_list_peek(felem);

			if (pf->item == zbx_prometh_item_metric_name)
			{
				if (!(pf->name_check(pf->name, pdata->name)))
					++no_match;
			}
			else if (pf->item == zbx_prometh_item_metric_value)
			{
				if (!(pf->name_check(pf->name, pdata->value)))
					++no_match;
			}
			else if (pf->item == zbx_prometh_item_label_name)
			{
				int match = 0, fullmatch = 0;
				struct zbx_prometh_list_elem *lelem = zbx_prometh_list_seek_head(&pdata->labels);

				while (lelem)
				{
					struct zbx_prometh_label *label =
						(struct zbx_prometh_label *)zbx_prometh_list_peek(lelem);

					match = pf->name_check(pf->name, label->label_name);

					if (match)
					{
						match = pf->value_check(pf->value, label->label_value);
					}

					if (match)
						fullmatch++;

					lelem = zbx_prometh_list_seek_next(lelem);
				}

				if (!fullmatch)
					++no_match;
			}

			felem = zbx_prometh_list_seek_next(felem);
		}

		if (!no_match)
		{
			pm = zbx_prometh_list_seek_next(delem);

			zbx_prometh_list_remove_elem(in_data, delem);
			zbx_prometh_list_append_elem(results, delem->data);
		}

		delem = (pm == NULL) ? zbx_prometh_list_seek_next(delem) : pm;
	}
}

static void	zbx_prometh_free_filter(zbx_prometh_list_t *l)
{
	struct zbx_prometh_list_elem *p;

	while (SUCCEED == zbx_prometh_list_remove_first_elem(l, &p))
	{
		struct zbx_prometh_filter_node *f = (struct zbx_prometh_filter_node *)p->data;

		if (NULL != f->name)
			zbx_free(f->name);

		if (NULL != f->value)
			zbx_free(f->value);

		zbx_free(f);

		zbx_free(p);
	}
}

#if 0
static int	zbx_prometh_is_item_present (zbx_prometh_list_t *l, void *item)
{
	struct zbx_prometh_list_elem *p = zbx_prometh_list_seek_head(l);

	while (NULL != p)
	{
		if (zbx_prometh_list_peek(p) == item)
			return 1;

		p = zbx_prometh_list_seek_next(p);
	}
	return 0;
}
#endif

static void	zbx_prometh_free_metrics(zbx_prometh_list_t *l)
{
	struct zbx_prometh_list_elem *p;
#if 0
	zbx_prometh_list_t to_free;

	zbx_prometh_list_init(&to_free);
#endif

	while (SUCCEED == zbx_prometh_list_remove_first_elem(l, &p))
	{
		struct zbx_prometh_list_elem *pl;
		struct zbx_prometh_metric *m = (struct zbx_prometh_metric *)p->data;

		if (NULL != m->value)
			zbx_free(m->value);

		if (NULL != m->line_raw)
			zbx_free(m->line_raw);

		while (SUCCEED == zbx_prometh_list_remove_first_elem(&m->labels, &pl))
		{
			struct zbx_prometh_label *label = (struct zbx_prometh_label *)pl->data;

			if (NULL != label->label_name)
				zbx_free(label->label_name);

			if (NULL != label->label_value)
				zbx_free(label->label_value);

			zbx_free(label);

			zbx_free(pl);
		}

		/* the same "name", "help" and "type" strings may appear in different metrics */
#if 0
		if (!zbx_prometh_is_item_present(&to_free, (void *)m->name))
			zbx_prometh_list_append_elem(&to_free, (void *)m->name);

		if (!zbx_prometh_is_item_present(&to_free, (void *)m->help))
			zbx_prometh_list_append_elem(&to_free, (void *)m->help);

		if (!zbx_prometh_is_item_present(&to_free, (void *)m->type))
			zbx_prometh_list_append_elem(&to_free, (void *)m->type);
#endif
		zbx_free(m);

		zbx_free(p);
	}

#if 0
	while (SUCCEED == zbx_prometh_list_remove_first_elem(&to_free, &p))
	{
		char *data = (char *)p->data;

		if (NULL != data)
			zbx_free(data);

		free(p);
	}
#endif
}

int	zbx_prometheus_pattern (char *data, char *params, char *value_type, char **output, char **err)
{
	int ret, result_count;
	zbx_prometh_list_t	filter, in_data, results;

	/* build filter */
	ret = zbx_prometh_filter_create(&filter, params);

	if (SUCCEED != ret)
	{
		/* TODO - syntax errors from user */
	}

	/* build structured data */
	zbx_prometh_data_prepare(&in_data, data);

	/* process structured data */
	zbx_prometh_process_data(&in_data, &filter, &results);

	/* check results */
	result_count = zbx_prometh_list_get_elem_count(&results);

	if (0 == result_count)
	{
		/* ERROR: no metrics are found for the given filtering rule */

		*err = zbx_dsprintf(*err, "metric not found");
	}
	else if (1 < result_count)
	{
		/* ERROR: multiple metrics result */

		int i = 0;
		struct zbx_prometh_list_elem *relem = zbx_prometh_list_seek_head(&results);
		char metrics[1024], *p = metrics;

		while (relem)
		{
			struct zbx_prometh_metric *pdata =
				(struct zbx_prometh_metric *)zbx_prometh_list_peek(relem);

			if (++i == 10)
				break;

			memcpy(p, pdata->line_raw, strlen(pdata->line_raw));
			p += strlen(pdata->line_raw);
			*p++ = '\n';

			relem = zbx_prometh_list_seek_next(relem);
		}
		*p = '\0';

		*err = zbx_dsprintf(*err, "multiple metric result:\n%s", metrics);
	}
	else
	{
		/* SUCCESS: output metric value */

		struct zbx_prometh_list_elem *relem = zbx_prometh_list_seek_head(&results);
		struct zbx_prometh_metric *pdata = (struct zbx_prometh_metric *)zbx_prometh_list_peek(relem);

		if (!strcmp(value_type, "\\value"))
		{
			/* output metric value as a string */
			*output = zbx_dsprintf(*output, "%s", pdata->value);
		}
		else
		{
			struct zbx_prometh_list_elem *lelem = zbx_prometh_list_seek_head(&pdata->labels);

			while (lelem)
			{
				struct zbx_prometh_label *label =
					(struct zbx_prometh_label *)zbx_prometh_list_peek(lelem);

				if (!strcmp(label->label_name, value_type))
				{
					/* output label value as a string */
					*output = zbx_dsprintf(*output, "%s", label->label_value);
					break;
				}

				lelem = zbx_prometh_list_seek_next(lelem);
			}

			if (NULL == *output)
			{
				/* label name not found */
				*err = zbx_dsprintf(*err, "label name not found");
			}
		}
	}

	/* free all data */
	zbx_prometh_free_filter(&filter);
	zbx_prometh_free_metrics(&in_data);
	zbx_prometh_free_metrics(&results);

	if (NULL != *err && strlen(*err))
		return FAIL;

	return SUCCEED;
}

int zbx_prometheus_to_json (char *data, char *params, char **output, char **err)
{
	int ret, result_count;
	zbx_prometh_list_t	filter, in_data, results;

	/* build filter */
	ret = zbx_prometh_filter_create(&filter, params);

	if (SUCCEED != ret)
	{
		/* TODO - syntax errors from user */
	}

	/* build structured data */
	zbx_prometh_data_prepare(&in_data, data);

	/* process structured data */
	zbx_prometh_process_data(&in_data, &filter, &results);

	/* check results */
	result_count = zbx_prometh_list_get_elem_count(&results);

	if (0 == result_count)
	{
		/* ERROR: no metrics are found for the given filtering rule */

		*err = zbx_dsprintf(*err, "no metrics");
	}
	else if (0 < result_count)
	{
		/* TODO - output the entire results list */

		*output = zbx_dsprintf(*output, "OUTPUT SOMETHING");
	}

	/* free all data */
	zbx_prometh_free_filter(&filter);
	zbx_prometh_free_metrics(&in_data);
	zbx_prometh_free_metrics(&results);

	if (NULL != *err && strlen(*err))
		return FAIL;

	return SUCCEED;
}
