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

package redis

import (
	"fmt"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin/comms"
)

type session struct {
	URI      string `conf:"name=Uri,optional"`
	Password string `conf:"optional"`
	User     string `conf:"optional"`

	TLSConnect  string `conf:"name=TLSConnect,optional"`
	TLSCAFile   string `conf:"name=TLSCAFile,optional"`
	TLSCertFile string `conf:"name=TLSCertFile,optional"`
	TLSKeyFile  string `conf:"name=TLSKeyFile,optional"`
}

func (s *session) getTLSFieldValues() map[comms.TLSConfigSetting]string {
	return map[comms.TLSConfigSetting]string{
		comms.TLSCAFile:   s.TLSCAFile,
		comms.TLSCertFile: s.TLSCertFile,
		comms.TLSKeyFile:  s.TLSKeyFile,
	}
}

func (s *session) validateSession(defaults *session) error {
	err := s.validateRequiredFields()
	if err != nil {
		return errs.Wrap(err, "base fields validation failed")
	}

	err = s.validateTLSConfiguration(defaults)
	if err != nil {
		return errs.Wrap(err, "TLS validation failed")
	}

	return nil
}

func (s *session) validateRequiredFields() error {
	requiredFields := map[string]string{
		"URI":      s.URI,
		"Password": s.Password,
	}

	for fieldName, fieldValue := range requiredFields {
		if fieldValue == "" {
			return errs.New(fmt.Sprintf("s.%s is required", fieldName))
		}
	}

	return nil
}

func (s *session) validateTLSConfiguration(defaults *session) error {
	tlsConnect := s.TLSConnect // to keep the original session value.

	if tlsConnect == "" {
		tlsConnect = defaults.TLSConnect
	}

	if tlsConnect == "" {
		tlsConnect = string(comms.Disabled)
	}

	validationRules := getTLSValidationRules(tlsConnect)
	tlsFields := s.getTLSFieldValues()
	defaultsFields := defaults.getTLSFieldValues()

	return s.runValidationRules(validationRules, tlsFields, defaultsFields)
}

func (*session) runValidationRules(
	rules map[comms.TLSConfigSetting]fieldRequirement,
	values, defaults map[comms.TLSConfigSetting]string,
) error {
	for fieldName, requirement := range rules {
		fieldValue := values[fieldName]
		defaultValue := defaults[fieldName]

		var err error

		switch requirement {
		case required:
			err = validateRequiredField(fieldName, fieldValue, defaultValue)
		case forbidden:
			err = validateForbiddenField(fieldName, fieldValue)
		case optional: // No action needed for optional fields.
		}

		if err != nil {
			return err
		}
	}

	return nil
}

func validateRequiredField(name comms.TLSConfigSetting, value, defaultValue string) error {
	if value == "" && defaultValue == "" {
		return errs.New(fmt.Sprintf("%s is required", name))
	}

	return nil
}

func validateForbiddenField(name comms.TLSConfigSetting, value string) error {
	if value != "" {
		return errs.New(fmt.Sprintf("%s is forbidden", name))
	}

	return nil
}
