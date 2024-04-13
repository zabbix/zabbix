

/******************************************************************************
 *                                                                            *
 * Purpose: prints application parameters on stdout with layout suitable for  *
 *          80-column terminal                                                *
 *                                                                            *
 * Parameters:  usage_message - [IN]                                          *
 *              progname      - [IN]                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_print_usage(const char **usage_message, const char *progname)
{
#define ZBX_MAXCOL	79
#define ZBX_SPACE1	"  "			/* left margin for the first line */
#define ZBX_SPACE2	"               "	/* left margin for subsequent lines */
	const char	**p = usage_message;

	if (NULL != *p)
		printf("Usage:\n");

	while (NULL != *p)
	{
		size_t	pos;

		printf("%s%s", ZBX_SPACE1, progname);
		pos = ZBX_CONST_STRLEN(ZBX_SPACE1) + strlen(progname);

		while (NULL != *p)
		{
			size_t	len;

			len = strlen(*p);

			if (ZBX_MAXCOL > pos + len)
			{
				pos += len + 1;
				printf(" %s", *p);
			}
			else
			{
				pos = ZBX_CONST_STRLEN(ZBX_SPACE2) + len + 1;
				printf("\n%s %s", ZBX_SPACE2, *p);
			}

			p++;
		}

		printf("\n");
		p++;
	}
#undef ZBX_MAXCOL
#undef ZBX_SPACE1
#undef ZBX_SPACE2
}

static const char	help_message_footer[] =
	"Report bugs to: <https://support.zabbix.com>\n"
	"Zabbix home page: <https://www.zabbix.com>\n"
	"Documentation: <https://www.zabbix.com/documentation>";


/******************************************************************************
 *                                                                            *
 * Purpose: prints help of application parameters on stdout by application    *
 *          request with parameter '-h'                                       *
 *                                                                            *
 * Parameters: param         - [IN] pointer to modification parameter         *
 *             help_message  - [IN]                                           *
 *             usage_message - [IN]                                           *
 *             progname      - [IN]                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_print_help(const char *param, const char **help_message, const char **usage_message, const char *progname)
{
	const char	**p = help_message;

	zbx_print_usage(usage_message, progname);
	printf("\n");

	while (NULL != *p)
	{
		if (NULL != param && NULL != strstr(*p, "{DEFAULT_CONFIG_FILE}"))
		{
			char	*ptr;

			ptr = zbx_string_replace(*p++, "{DEFAULT_CONFIG_FILE}", param);
			printf("%s\n", ptr);
			zbx_free(ptr);
			continue;
		}

		printf("%s\n", *p++);
	}

	printf("\n");
	puts(help_message_footer);
}
