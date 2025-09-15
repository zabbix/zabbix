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
	"golang.zabbix.com/sdk/tlsconfig"
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

func (s *session) getFieldValues() map[comms.ConfigSetting]string {
	return map[comms.ConfigSetting]string{
		comms.URI:      s.URI,
		comms.User:     s.User,
		comms.Password: s.Password,

		comms.TLSConnect:  s.TLSConnect,
		comms.TLSCAFile:   s.TLSCAFile,
		comms.TLSCertFile: s.TLSCertFile,
		comms.TLSKeyFile:  s.TLSKeyFile,
	}
}

func (s *session) validateSession(defaults *session) error {
	tlsConnect, err := s.resolveTLSConnect(defaults)
	if err != nil {
		return err
	}

	err = s.runValidationRules(tlsConnect, defaults)
	if err != nil {
		return err
	}

	err = s.runSourceConsistencyValidation()
	if err != nil {
		return errs.Wrap(err, "source-consistency validation failed")
	}

	return nil
}

func (s *session) resolveTLSConnect(defaults *session) (tlsconfig.TLSConnectionType, error) {
	if s.TLSConnect != "" {
		connectionType, err := tlsconfig.NewTLSConnectionType(s.TLSConnect)
		if err != nil {
			return "", errs.Wrap(err, "invalid 'TLSConnect'")
		}

		return connectionType, nil
	}

	if defaults.TLSConnect != "" {
		connectionType, err := tlsconfig.NewTLSConnectionType(defaults.TLSConnect)
		if err != nil {
			return "", errs.Wrap(err, "default invalid 'TLSConnect'")
		}

		return connectionType, nil
	}

	return tlsconfig.Disabled, nil
}

func (s *session) runValidationRules(
	tlsConnect tlsconfig.TLSConnectionType,
	defaultSession *session,
) error {
	values := s.getFieldValues()
	defaults := defaultSession.getFieldValues()

	rules := getValidationRules(tlsConnect)

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
		default:
		}

		if err != nil {
			return err
		}
	}

	return nil
}

func (s *session) runSourceConsistencyValidation() error {
	values := s.getFieldValues()
	rules := getSourceConsistencyRules()

	for _, fields := range rules {
		var (
			setCount   int
			emptyCount int
		)

		for _, field := range fields {
			value := values[field]
			if value != "" {
				setCount++
			} else {
				emptyCount++
			}
		}

		// Both counters > 0 = inconsistency.
		if setCount > 0 && emptyCount > 0 {
			return errs.New(fmt.Sprintf(
				"all fields %s are required to be filled in the same source but not mixed. "+
					"Either Session or Default",
				fields))
		}
	}

	return nil
}

func validateRequiredField(name comms.ConfigSetting, value, defaultValue string) error {
	if value == "" && defaultValue == "" {
		return errs.New(fmt.Sprintf("%s is required but missing", name))
	}

	return nil
}

func validateForbiddenField(name comms.ConfigSetting, value string) error {
	if value != "" {
		return errs.New(fmt.Sprintf("%s is forbidden but was provided", name))
	}

	return nil
}
