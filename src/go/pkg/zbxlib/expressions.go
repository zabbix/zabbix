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

package zbxlib

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxregexp.h"

typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;
typedef zbx_vector_expression_t * zbx_vector_expression_lp_t;

static void *new_global_regexp()
{
	zbx_vector_ptr_t *regexps;
	regexps = malloc(sizeof(zbx_vector_ptr_t));
	zbx_vector_ptr_create(regexps);
	return (void *)regexps;
}

static void	free_global_regexp(zbx_vector_expression_t *regexps)
{
	zbx_regexp_clean_expressions(regexps);
	zbx_vector_expression_destroy(regexps);
	free(regexps);
}

*/
import "C"

import (
	"errors"
	"unsafe"

	"golang.zabbix.com/sdk/log"
)

func NewGlobalRegexp() (grxp unsafe.Pointer) {
	log.Tracef("Calling C function \"new_global_regexp()\"")
	return unsafe.Pointer(C.new_global_regexp())
}

func DestroyGlobalRegexp(grxp unsafe.Pointer) {
	log.Tracef("Calling C function \"free_global_regexp()\"")
	C.free_global_regexp(C.zbx_vector_expression_lp_t(grxp))
}

func AddGlobalRegexp(grxp unsafe.Pointer, name, body string, expr_type int, delim byte, mode int) {
	cname := C.CString(name)
	cbody := C.CString(body)
	log.Tracef("Calling C function \"zbx_add_regexp_ex()\"")
	C.zbx_add_regexp_ex(C.zbx_vector_expression_lp_t(grxp), cname, cbody, C.int(expr_type), C.char(delim),
		C.int(mode))
	log.Tracef("Calling C function \"free()\"")
	C.free(unsafe.Pointer(cname))
	log.Tracef("Calling C function \"free()\"")
	C.free(unsafe.Pointer(cbody))
}

func MatchGlobalRegexp(
	grxp unsafe.Pointer,
	value, pattern string,
	mode int,
	output_template *string) (match bool, output string, err error) {

	cvalue := C.CString(value)
	cpattern := C.CString(pattern)

	defer func() {
		log.Tracef("Calling C function \"free(cvalue)\"")
		defer C.free(unsafe.Pointer(cvalue))
		log.Tracef("Calling C function \"free(cpattern)\"")
		defer C.free(unsafe.Pointer(cpattern))
	}()

	var ctemplate, coutput *C.char
	if output_template != nil {
		ctemplate = C.CString(*output_template)
		log.Tracef("Calling C function \"free()\"")
		defer C.free(unsafe.Pointer(ctemplate))
	}

	log.Tracef("Calling C function \"zbx_regexp_sub_ex()\"")
	ret := C.zbx_regexp_sub_ex(C.zbx_vector_expression_lp_t(grxp), cvalue, cpattern, C.int(mode), ctemplate,
		&coutput)
	switch ret {
	case C.ZBX_REGEXP_MATCH:
		match = true
		if coutput != nil {
			output = C.GoString(coutput)
		}
	case C.ZBX_REGEXP_NO_MATCH:
		match = false
	default:
		err = errors.New("invalid global regular expression")
	}

	if coutput != nil {
		log.Tracef("Calling C function \"free()\"")
		C.free(unsafe.Pointer(coutput))
	}
	return
}
