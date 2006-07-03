/*
 * ** ZABBIX
 * ** Copyright (C) 2000-2005 SIA Zabbix
 * **
 * ** This program is free software; you can redistribute it and/or modify
 * ** it under the terms of the GNU General Public License as published by
 * ** the Free Software Foundation; either version 2 of the License, or
 * ** (at your option) any later version.
 * **
 * ** This program is distributed in the hope that it will be useful,
 * ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * ** GNU General Public License for more details.
 * **
 * ** You should have received a copy of the GNU General Public License
 * ** along with this program; if not, write to the Free Software
 * ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * **/

#include "config.h"

#include "common.h"
#include "sysinfo.h"



static int GetProcessUsername(HANDLE hProcess, char *userName, int userNameLen) {
	HANDLE		tok = 0;
	TOKEN_USER	*ptu;

	DWORD
		nlen, 
		dlen;

	char
		name[300],
		dom[300],
		tubuf[300];

	int iUse;

	assert(userName);
	
	//clean result;
	*userName = '\0';

	//open the processes token
	if (!OpenProcessToken(hProcess,TOKEN_QUERY,&tok)) goto lbl_err;;

	//get the SID of the token
	ptu = (TOKEN_USER*)tubuf;
	if (!GetTokenInformation(tok,(TOKEN_INFORMATION_CLASS)1,ptu,300,&nlen)) goto lbl_err;

	//get the account/domain name of the SID
	dlen = 300;	nlen = 300;
	if (!LookupAccountSid(0, ptu->User.Sid, name, &nlen, dom, &dlen, (PSID_NAME_USE)&iUse)) goto lbl_err;

	nlen = min(userNameLen-1,(int)nlen);

	strncpy(userName, name, nlen);
	userName[nlen] = 0;

	return 1;

lbl_err:
	if (tok) CloseHandle(tok);
	return 0;
}

				    
int     PROC_MEMORY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{ /* usage: <function name>[ <process name>, <user name>, <mode>, <command> ] */
	#ifdef TODO
	#	error Realize function!!!
	#endif /* todo */

	return SYSINFO_RET_FAIL;
}

#define MAX_PROCESSES         4096

int	    PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{ /* usage: <function name>[ <process name>, <user name>] */
	HANDLE	hProcess;
	HMODULE	hMod;
	DWORD	procList[MAX_PROCESSES];

	DWORD dwSize=0;

	int 
		i = 0,
		proccount = 0,
		max_proc_cnt = 0,
		proc_ok = 0,
		user_ok = 0;

	char 
		procName[MAX_PATH],
		userName[MAX_PATH],
		baseName[MAX_PATH], 
		uname[300];

	if(num_param(param) > 2)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, procName, MAX_PATH) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, userName, MAX_PATH) != 0)
	{
		userName[0] = '\0';
	}


	EnumProcesses(procList,sizeof(DWORD)*MAX_PROCESSES,&dwSize);

	for(i=0,proccount=0,max_proc_cnt=dwSize/sizeof(DWORD); i < max_proc_cnt; i++)
	{
		proc_ok = 0;
		user_ok = 0;

		hProcess=OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ,FALSE,procList[i]);
		if (hProcess!=NULL)
		{
			if (procName[0] != 0) 
			{
				if (EnumProcessModules(hProcess,&hMod,sizeof(hMod),&dwSize))
				{
					GetModuleBaseName(hProcess,hMod,baseName,sizeof(baseName));
					if (stricmp(baseName,procName) == 0)
					{
						proc_ok = 1;
					}
				}
			} else {
				proc_ok = 1;
			}

			if(userName[0] != '\0')
			{
				if(GetProcessUsername(hProcess, uname, 300))
				{
					if (stricmp(uname,userName) == 0)
						user_ok = 1;
				}
			} else {
				user_ok = 1;
			}

			if(user_ok && proc_ok)		proccount++;

			CloseHandle(hProcess);
		}
	}

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
