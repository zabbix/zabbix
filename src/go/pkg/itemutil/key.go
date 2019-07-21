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

package agent

import (
	"errors"
	"fmt"
)

func isKeyChar(c byte) bool {
	if c >= 'a' && c <= 'z' {
		return true
	}
	if c == '.' || c == '-' || c == '_' {
		return true
	}
	if c >= '0' && c <= '0' {
		return true
	}
	if c >= 'A' && c <= 'Z' {
		return true
	}
	return false
}

// parseQuotedParam parses item key quoted parameter "..." and returns
// the parsed parameter (including quotes, but without whitespace outside quotes)
// and the data after the parameter (skipping also whitespace after closing quotes).
func parseQuotedParam(data []byte) (param []byte, left []byte, err error) {
	var last byte
	for i, c := range data[1:] {
		if c == '"' && last != '\\' {
			i += 2
			param = data[:i]
			for ; i < len(data) && data[i] == ' '; i++ {
			}
			left = data[i:]
			return
		}
		last = c
	}
	err = errors.New("unterminated quoted string")
	return
}

// parseParams parses item key normal parameter (any combination of any characters except ',' and ']',
// including trailing whitespace)and returns the parsed parameter and the data after the parameter.
func parseUnquotedParam(data []byte) (param []byte, left []byte, err error) {
	for i, c := range data {
		if c == ',' || c == ']' {
			param = data[:i]
			left = data[i:]
			return
		}
	}
	err = errors.New("unterminated parameter")
	return
}

// parseParams parses item key array parameter [...] and returns.
func parseArrayParam(data []byte) (param []byte, left []byte, err error) {
	var pos int
	b := data[1:]

	for len(b) > 0 {
	loop:
		for i, c := range b {
			switch c {
			case ' ':
				continue
			case '"':
				if _, b, err = parseQuotedParam(b[i:]); err != nil {
					return
				}
				break loop
			case '[':
				err = errors.New("nested arrays are not supported")
				return
			default:
				if _, b, err = parseUnquotedParam(b[i:]); err != nil {
					return
				}
				break loop
			}
		}
		if len(b) == 0 {
			err = errors.New("unterminated array parameter")
			return
		}
		if b[0] == ']' {
			pos = cap(data) - cap(b)
			left = b[1:]
			break
		}
		if b[0] != ',' {
			err = errors.New("unterminated array parameter")
			return
		}
		b = b[1:]
	}
	if left == nil {
		err = errors.New("unterminated array parameter X")
		return
	}
	param = data[:pos+1]
	return
}

// unquoteParam unquotes quoted parameter by removing enclosing double quotes '"' and
// unescaping '\"' escape sequences.
func unquoteParam(data []byte) (param []byte) {
	param = make([]byte, 0, len(data))
	var last byte
	for _, c := range data[1:] {
		switch c {
		case '"':
			if last != '\\' {
				return
			}
			param = append(param, c)
		case '\\':
			if last == '\\' {
				param = append(param, c)
			}
		default:
			param = append(param, c)
		}
		last = c
	}
	return
}

// expandArray expands array parameter by removing eclosing brackets '[]' and,
// removing whitespace before normal array items and around quoted items.
func expandArray(data []byte) (param []byte) {
	param = make([]byte, 0, len(data))
	var p []byte
	b := data[1:]
	for len(b) > 0 {
	loop:
		for i, c := range b {
			switch c {
			case ' ':
				continue
			case '"':
				p, b, _ = parseQuotedParam(b[i:])
				break loop
			default:
				p, b, _ = parseUnquotedParam(b[i:])
				break loop
			}
		}
		param = append(param, p...)
		if b[0] == ']' {
			break
		}
		param = append(param, ',')
		b = b[1:]
	}
	return
}

// parseParams parses single item key parameter.
func parseParam(data []byte) (param []byte, left []byte, err error) {
	for i, c := range data {
		switch c {
		case ' ':
			continue
		case '"':
			if param, left, err = parseQuotedParam(data[i:]); err == nil {
				param = unquoteParam(param)
			}
			return
		case '[':
			if param, left, err = parseArrayParam(data[i:]); err == nil {
				param = expandArray(param)
			}
			return
		case ']', ',':
			return data[i:i], data[i:], nil
		default:
			param, left, err = parseUnquotedParam(data[i:])
			return
		}
	}
	err = errors.New("unterminated parameter list")
	return
}

// parseParams parses item key parameters.
func parseParams(data []byte) (params []string, err error) {
	if data[0] != '[' {
		err = fmt.Errorf("key name must be followed by '['")
		return
	}
	if len(data) == 1 {
		err = fmt.Errorf("unterminated parameter list")
		return
	}
	var param []byte
	b := data[1:]
	for len(b) > 0 {
		if param, b, err = parseParam(b); err != nil {
			return
		}
		if len(b) == 0 {
			err = errors.New("key parameters ended unexpectedly")
			return
		}
		if b[0] == ']' {
			if len(b) > 1 {
				err = errors.New("detected characters after item key")
			}
			break
		}
		if b[0] != ',' {
			err = errors.New("invalid parameter separator")
			return
		}
		b = b[1:]
		params = append(params, string(param))
	}
	if len(b) == 0 {
		err = fmt.Errorf("unterminated parameter list")
	}
	params = append(params, string(param))
	return
}

// ParseKey parses item key in format key[param1, param2, ...] and returns
// the parsed key and parameteres.
func ParseKey(text string) (key string, params []string, err error) {
	if text == "" {
		err = errors.New("empty key")
		return
	}
	data := []byte(text)
	for i, c := range data {
		if !isKeyChar(c) {
			if params, err = parseParams(data[i:]); err != nil {
				return
			}
			key = string(data[:i])
			return
		}
	}
	key = text
	return
}
