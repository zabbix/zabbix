/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxwin32.h"

#include "zbxstr.h"
#include "zbxlog.h"

#include <excpt.h>
#include <DbgHelp.h>

#pragma comment(lib, "DbgHelp.lib")

typedef BOOL (WINAPI *SymGetLineFromAddrW64_func_t)(HANDLE, DWORD64, PDWORD, PIMAGEHLP_LINE64);
typedef BOOL (WINAPI *SymFromAddr_func_t)(HANDLE a, DWORD64 b , PDWORD64 c, PSYMBOL_INFO d);

#ifdef _M_X64
static void	print_register(const char *name, unsigned __int64 value)
{
	zabbix_log(LOG_LEVEL_CRIT, "%-7s = %16I64x = %20I64u = %20I64d", name, value, value, value);
}
#else
static void	print_register(const char *name, unsigned __int32 value)
{
	zabbix_log(LOG_LEVEL_CRIT, "%-7s = %16lx = %20lu = %20ld", name, value, value, value);
}
#endif

static void	print_fatal_info(CONTEXT *pctx)
{
	zabbix_log(LOG_LEVEL_CRIT, "====== Fatal information: ======");

#ifdef _M_X64
	zabbix_log(LOG_LEVEL_CRIT, "Program counter: 0x%08lx", pctx->Rip);
#else
	zabbix_log(LOG_LEVEL_CRIT, "Program counter: 0x%04x", pctx->Eip);
#endif
	zabbix_log(LOG_LEVEL_CRIT, "=== Registers: ===");

#define ZBX_LSHIFT(value, bits)	(((unsigned __int64)value) << bits)

#ifdef _M_X64
	print_register("r8", pctx->R8);
	print_register("r9", pctx->R9);
	print_register("r10", pctx->R10);
	print_register("r11", pctx->R11);
	print_register("r12", pctx->R12);
	print_register("r13", pctx->R13);
	print_register("r14", pctx->R14);
	print_register("r15", pctx->R15);

	print_register("rdi", pctx->Rdi);
	print_register("rsi", pctx->Rsi);
	print_register("rbp", pctx->Rbp);

	print_register("rbx", pctx->Rbx);
	print_register("rdx", pctx->Rdx);
	print_register("rax", pctx->Rax);
	print_register("rcx", pctx->Rcx);

	print_register("rsp", pctx->Rsp);
	print_register("efl", pctx->EFlags);
	print_register("csgsfs", ZBX_LSHIFT(pctx->SegCs, 24) | ZBX_LSHIFT(pctx->SegGs, 16) | ZBX_LSHIFT(pctx->SegFs, 8));
#else
	print_register("edi", pctx->Edi);
	print_register("esi", pctx->Esi);
	print_register("ebp", pctx->Ebp);

	print_register("ebx", pctx->Ebx);
	print_register("edx", pctx->Edx);
	print_register("eax", pctx->Eax);
	print_register("ecx", pctx->Ecx);

	print_register("esp", pctx->Esp);
	print_register("efl", pctx->EFlags);
	print_register("csgsfs", ZBX_LSHIFT(pctx->SegCs, 24) | ZBX_LSHIFT(pctx->SegGs, 16) | ZBX_LSHIFT(pctx->SegFs, 8));
#endif

#undef ZBX_LSHIFT
}

static zbx_get_progname_f	get_progname_cb = NULL;

void	zbx_backtrace(void)
{
}

static void	print_backtrace(CONTEXT *pctx)
{
	SymGetLineFromAddrW64_func_t	zbx_SymGetLineFromAddrW64 = NULL;
	SymFromAddr_func_t		zbx_SymFromAddr	= NULL;

	CONTEXT		ctx, ctxcount;
	STACKFRAME64	s, scount;
	PSYMBOL_INFO	pSym = NULL;
	HMODULE		hModule;
	HANDLE		hProcess, hThread;
	DWORD64		offset;
	wchar_t		szProcessName[MAX_PATH];
	char		*process_name = NULL, *process_path = NULL, *frame = NULL;
	size_t		frame_alloc = 0, frame_offset;
	int		nframes = 0;
	char		*file_name;
	char		path[MAX_PATH];
	HMODULE		hm = NULL;

	ctx = *pctx;

	zabbix_log(LOG_LEVEL_CRIT, "=== Backtrace: ===");

	memset(&s, 0, sizeof(s));

	s.AddrPC.Mode = AddrModeFlat;
	s.AddrFrame.Mode = AddrModeFlat;
	s.AddrStack.Mode = AddrModeFlat;

#ifdef _M_X64
	s.AddrPC.Offset = ctx.Rip;
	s.AddrFrame.Offset = ctx.Rbp;
	s.AddrStack.Offset = ctx.Rsp;
#else
	s.AddrPC.Offset = ctx.Eip;
	s.AddrFrame.Offset = ctx.Ebp;
	s.AddrStack.Offset = ctx.Esp;
#endif
	hProcess = GetCurrentProcess();
	hThread = GetCurrentThread();

	if (0 != GetModuleFileNameEx(hProcess, NULL, szProcessName, ARRSIZE(szProcessName)))
	{
		char	*ptr;
		size_t	path_alloc = 0, path_offset = 0;

		process_name = zbx_unicode_to_utf8(szProcessName);

		if (NULL != (ptr = strstr(process_name, get_progname_cb())))
			zbx_strncpy_alloc(&process_path, &path_alloc, &path_offset, process_name, ptr - process_name);
	}

	if (NULL != (hModule = GetModuleHandle(TEXT("DbgHelp.DLL"))))
	{
		zbx_SymGetLineFromAddrW64 = (SymGetLineFromAddrW64_func_t)GetProcAddress(hModule,
				"SymGetLineFromAddr64");
		zbx_SymFromAddr = (SymFromAddr_func_t)GetProcAddress(hModule, "SymFromAddr");
	}

	if (NULL != zbx_SymFromAddr || NULL != zbx_SymGetLineFromAddrW64)
	{
		SymSetOptions(SymGetOptions() | SYMOPT_LOAD_LINES);

		if (FALSE != SymInitialize(hProcess, process_path, TRUE))
		{
			pSym = (PSYMBOL_INFO) zbx_malloc(NULL, sizeof(SYMBOL_INFO) + MAX_SYM_NAME);
			memset(pSym, 0, sizeof(SYMBOL_INFO) + MAX_SYM_NAME);
			pSym->SizeOfStruct = sizeof(SYMBOL_INFO);
			pSym->MaxNameLen = MAX_SYM_NAME;
		}
	}

	scount = s;
	ctxcount = ctx;

#ifdef _M_X64
#define ZBX_IMAGE_FILE_MACHINE	IMAGE_FILE_MACHINE_AMD64
#else
#define ZBX_IMAGE_FILE_MACHINE	IMAGE_FILE_MACHINE_I386
#endif

	/* get number of frames, ctxcount may be modified during StackWalk64() calls */
	while (TRUE == StackWalk64(ZBX_IMAGE_FILE_MACHINE, hProcess, hThread, &scount, &ctxcount, NULL, NULL, NULL,
			NULL))
	{
		if (0 == scount.AddrReturn.Offset)
			break;
		nframes++;
	}

	while (TRUE == StackWalk64(ZBX_IMAGE_FILE_MACHINE, hProcess, hThread, &s, &ctx, NULL, NULL, NULL, NULL))
	{
		file_name = process_name;
		frame_offset = 0;
		*path = '\0';

		if (NULL != pSym)
		{
			DWORD		dwDisplacement;
			IMAGEHLP_LINE64	line = {sizeof(IMAGEHLP_LINE64)};

			zbx_chrcpy_alloc(&frame, &frame_alloc, &frame_offset, '(');
			if (NULL != zbx_SymFromAddr &&
					TRUE == zbx_SymFromAddr(hProcess, s.AddrPC.Offset, &offset, pSym))
			{
#ifdef _M_X64
				zbx_uint64_t address = s.AddrPC.Offset;
#else
				zbx_uint32_t address = s.AddrPC.Offset;
#endif

				if (0 != GetModuleHandleExA(
						GET_MODULE_HANDLE_EX_FLAG_FROM_ADDRESS |
						GET_MODULE_HANDLE_EX_FLAG_UNCHANGED_REFCOUNT,
						(LPCSTR) address, &hm))
				{
					if (0 != GetModuleFileNameA(hm, path, sizeof(path)))
						file_name = path;
				}
				zbx_snprintf_alloc(&frame, &frame_alloc, &frame_offset, "%s+0x%lx",
						pSym->Name, offset);
			}

			if (NULL != zbx_SymGetLineFromAddrW64 && TRUE == zbx_SymGetLineFromAddrW64(hProcess,
					s.AddrPC.Offset, &dwDisplacement, &line))
			{
				zbx_snprintf_alloc(&frame, &frame_alloc, &frame_offset, " %s:%d", line.FileName,
						line.LineNumber);
			}
			zbx_chrcpy_alloc(&frame, &frame_alloc, &frame_offset, ')');
		}

		zabbix_log(LOG_LEVEL_CRIT, "%d: %s%s [0x%lx]",
				nframes--, NULL == file_name ? "(unknown)" : file_name, frame, s.AddrPC.Offset);

		if (0 == s.AddrReturn.Offset)
			break;
	}

#undef ZBX_IMAGE_FILE_MACHINE

	SymCleanup(hProcess);

	zbx_free(frame);
	zbx_free(process_path);
	zbx_free(process_name);
	zbx_free(pSym);
}

void	zbx_init_library_win32(zbx_get_progname_f get_progname)
{
	get_progname_cb = get_progname;
}

static void	zbx_win_exception_filter(struct _EXCEPTION_POINTERS *ep, const char *msg)
{
	zabbix_log(LOG_LEVEL_CRIT, msg, ep->ExceptionRecord->ExceptionCode, ep->ExceptionRecord->ExceptionAddress);

	print_fatal_info(ep->ContextRecord);
	print_backtrace(ep->ContextRecord);

	zabbix_log(LOG_LEVEL_CRIT, "================================");
}

LONG	zbx_win_seh_handler(struct _EXCEPTION_POINTERS *ep)
{
	zbx_win_exception_filter(ep, "Unhandled exception %x detected at 0x%p. Crashing ...");

	return EXCEPTION_CONTINUE_SEARCH;
}

#ifdef _M_X64
LONG	zbx_win_veh_handler(struct _EXCEPTION_POINTERS *ep)
{
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		zbx_win_exception_filter(ep, "VEH Trap detected exception %x at 0x%p. Exception information:");

	return EXCEPTION_CONTINUE_SEARCH;
}
#endif /* _M_X64 */
