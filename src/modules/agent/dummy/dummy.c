#include "modules.h"
#include "sysinfo.h"

int zbx_module_version()
{
	return 1;
}

char **zbx_module_list()
{
	static char *keys[]={"dummy.ping", "dummy.echo[*]", "dummy.random[*]"};
	return keys;
}

static int dummy_ping(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, 1);

	return	SYSINFO_RET_OK;
}

static int dummy_echo(AGENT_REQUEST *request, AGENT_RESULT *result)
{
/* TODO nparam return 1 event in case if there are no parameters, it should be fixed */
	if(request->nparam != 1)
	{
		return SYSINFO_RET_FAIL;
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, get_rparam(request,0)));

	return	SYSINFO_RET_OK;
}

static int dummy_random(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	from, to;

	if(request->nparam != 2)
	{
		return SYSINFO_RET_FAIL;
	}

	from = atoi(get_rparam(request, 0));
	to = atoi(get_rparam(request, 1));

	SET_UI64_RESULT(result, from + rand() % (to - from+1));

	return	SYSINFO_RET_OK;
}


int	zbx_module_process(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int ret = SYSINFO_RET_FAIL;

	if(0 == strcmp(request->key,"dummy.ping"))
	{
		ret = dummy_ping(request, result);
	}
	else if(0 == strcmp(request->key,"dummy.echo"))
	{
		ret = dummy_echo(request, result);
	}
	else if(0 == strcmp(request->key,"dummy.random"))
	{
		ret = dummy_random(request, result);
	}

	return ret;
}

/* It should return ZBX_MODULE_FAIL in case of initialization failure */
int zbx_module_init()
{
	srand(time(NULL));

	return ZBX_MODULE_OK;
}

int zbx_module_uninit()
{
	return ZBX_MODULE_OK;
}
