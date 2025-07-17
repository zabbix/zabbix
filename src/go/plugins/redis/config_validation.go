package redis

import (
	"fmt"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin/comms"
)

const (
	required fieldRequirement = iota
	forbidden
	optional
)

type fieldRequirement int

// gives a map which describes what field combinations are allowed depending on TLSConnect value.
func getTLSValidationRules(tlsConnect string) map[comms.TLSConfigSetting]fieldRequirement {
	switch comms.TLSConnectionType(tlsConnect) {
	case comms.NoTLS, comms.Insecure:
		return map[comms.TLSConfigSetting]fieldRequirement{
			comms.TLSCAFile:     forbidden,
			comms.TLSServerName: forbidden,
			comms.TLSCertFile:   forbidden,
			comms.TLSKeyFile:    forbidden,
		}
	case comms.VerifyCA:
		return map[comms.TLSConfigSetting]fieldRequirement{
			comms.TLSCAFile:     required,
			comms.TLSServerName: required,
			comms.TLSCertFile:   forbidden,
			comms.TLSKeyFile:    forbidden,
		}
	case comms.VerifyFull:
		return map[comms.TLSConfigSetting]fieldRequirement{
			comms.TLSCAFile:     required,
			comms.TLSServerName: required,
			comms.TLSCertFile:   required,
			comms.TLSKeyFile:    required,
		}
	default:
		return nil
	}
}

func validateRequiredFields(session *session) error {
	requiredFields := map[string]string{
		"URI":      session.URI,
		"Password": session.Password,
	}

	for fieldName, fieldValue := range requiredFields {
		if fieldValue == "" {
			return errs.New(fmt.Sprintf("session.%s is required", fieldName))
		}
	}

	return nil
}

func validateSession(session *session) error {
	err := validateRequiredFields(session)
	if err != nil {
		return errs.Wrap(err, "base fields validation failed")
	}

	err = validateTLSConfiguration(session)
	if err != nil {
		return errs.Wrap(err, "TLS validation failed")
	}

	return nil
}

func validateTLSConfiguration(session *session) error {
	if session.TLSConnect == "" {
		session.TLSConnect = string(comms.NoTLS)
	}

	validationRules := getTLSValidationRules(session.TLSConnect)
	if validationRules == nil {
		return errs.New("invalid TLSConnect value: " + session.TLSConnect)
	}

	return validateTLSFieldsByRules(session, validationRules)
}

func validateTLSFieldsByRules(session *session, validationRules map[comms.TLSConfigSetting]fieldRequirement) error {
	tlsFields := getTLSFieldValues(session)

	for fieldName, fieldRequirement := range validationRules {
		fieldValue := tlsFields[fieldName]

		switch fieldRequirement {
		case required:
			if fieldValue == "" {
				return errs.New(fmt.Sprintf("%s is required", fieldName))
			}
		case forbidden:
			if fieldValue != "" {
				return errs.New(fmt.Sprintf("%s is forbidden", fieldName))
			}
		case optional: // no action.
		default:
		}
	}

	return nil
}

func getTLSFieldValues(session *session) map[comms.TLSConfigSetting]string {
	return map[comms.TLSConfigSetting]string{
		comms.TLSCAFile:     session.TLSCAFile,
		comms.TLSServerName: session.TLSServerName,
		comms.TLSCertFile:   session.TLSCertFile,
		comms.TLSKeyFile:    session.TLSKeyFile,
	}
}
