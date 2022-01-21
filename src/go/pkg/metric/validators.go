/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

// Package metric provides an interface for describing a schema of metric's parameters.
package metric

import (
	"fmt"
	"regexp"
	"strconv"
	"strings"
)

type Validator interface {
	Validate(value *string) error
}

type SetValidator struct {
	Set             []string
	CaseInsensitive bool
}

func (v SetValidator) Validate(value *string) error {
	if v.Set != nil && len(v.Set) == 0 {
		panic("set cannot be empty")
	}

	if value == nil {
		return nil
	}

	for _, s := range v.Set {
		if (v.CaseInsensitive && strings.ToLower(*value) == strings.ToLower(s)) || (!v.CaseInsensitive && *value == s) {
			return nil
		}
	}

	return fmt.Errorf("allowed values: %s", strings.Join(v.Set, ", "))
}

type PatternValidator struct {
	Pattern string
}

func (v PatternValidator) Validate(value *string) error {
	if value == nil {
		return nil
	}

	b, err := regexp.MatchString(v.Pattern, *value)
	if err != nil {
		return err
	}

	if !b {
		return fmt.Errorf("value does not match pattern %q", v.Pattern)
	}

	return nil
}

type LenValidator struct {
	Min *int
	Max *int
}

func (v LenValidator) Validate(value *string) error {
	if value == nil || (v.Min == nil && v.Max == nil) {
		return nil
	}

	if v.Min != nil && len(*value) < *v.Min {
		return fmt.Errorf("value cannot be shorter than %d characters", v.Min)
	}

	if v.Max != nil && len(*value) > *v.Max {
		return fmt.Errorf("value cannot be longer than %d characters", v.Max)
	}

	return nil
}

type RangeValidator struct {
	Min int
	Max int
}

func (v RangeValidator) Validate(value *string) error {
	if value == nil {
		return nil
	}

	intVal, err := strconv.Atoi(*value)
	if err != nil {
		return err
	}

	if intVal < v.Min || intVal > v.Max {
		return fmt.Errorf("value is out of range [%d..%d]", v.Min, v.Max)
	}

	return nil
}

type NumberValidator struct{}

func (v NumberValidator) Validate(value *string) error {
	if value == nil {
		return nil
	}

	_, err := strconv.Atoi(*value)

	return err
}
