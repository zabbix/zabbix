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

package serverlistener

import (
	"fmt"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/agent2/internal/agent"
	mockscheduler "golang.zabbix.com/agent2/internal/agent/scheduler/mocks"
	"golang.zabbix.com/agent2/pkg/version"
	mockcomms "golang.zabbix.com/agent2/pkg/zbxcomms/mocks"
	"golang.zabbix.com/sdk/errs"
)

func TestFormatError(t *testing.T) {
	t.Parallel()

	const (
		notsupported = "ZBX_NOTSUPPORTED"
		message      = "error message"
	)

	result := formatError(message)

	if string(result[:len(notsupported)]) != notsupported {
		t.Errorf("Expected error message to start with '%s' while got '%s'", notsupported,
			string(result[:len(notsupported)]))

		return
	}

	if result[len(notsupported)] != 0 {
		t.Errorf("Expected terminating zero after ZBX_NOTSUPPORTED error prefix")

		return
	}

	if string(result[len(notsupported)+1:]) != message {
		t.Errorf("Expected error description '%s' while got '%s'", message, string(result[len(notsupported)+1:]))

		return
	}
}

func Test_parsePassiveCheckJSONRequest(t *testing.T) {
	t.Parallel()

	type expectations struct {
		key      string
		timeout  time.Duration
		hasError bool
	}

	tests := []struct {
		name       string
		givenInput string
		expect     expectations
	}{
		{
			name:       "+timeout",
			givenInput: `{"request":"passive checks","data":[{"key":"system.cpu.load","timeout":"3"}]}`,
			expect: expectations{
				key:     "system.cpu.load",
				timeout: 3 * time.Second,
			},
		},
		{
			name:       "-invalidTimeout",
			givenInput: `{"request":"passive checks","data":[{"key":"system.cpu.load","timeout":"0"}]}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-invalidJSONFormat",
			givenInput: `{"request": invalid json}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-missingDataArray",
			givenInput: `{"request":"passive checks"}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-emptyDataArray",
			givenInput: `{"request":"passive checks","data":[]}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-unknownRequestType",
			givenInput: `{"request":"unknown","data":[{"key":"system.cpu.load","timeout":"3"}]}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-invalidTimeoutValue",
			givenInput: `{"request":"passive checks","data":[{"key":"system.cpu.load","timeout":"abc"}]}`,
			expect: expectations{
				hasError: true,
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			rawInput := []byte(tt.givenInput)

			gotKey, gotTimeout, err := parsePassiveCheckJSONRequest(rawInput)

			if (err != nil) != tt.expect.hasError {
				t.Fatalf("parsePassiveCheckJSONRequest() unexpected error: got %v, want=%v", err, tt.expect.hasError)
			}

			if tt.expect.key != gotKey {
				t.Errorf("parsePassiveCheckJSONRequest() key mismatch: want %q, got %q", tt.expect.key, gotKey)
			}

			if tt.expect.timeout != gotTimeout {
				t.Errorf(
					"parsePassiveCheckJSONRequest() timeout mismatch: want %v, got %v",
					tt.expect.timeout,
					gotTimeout,
				)
			}
		})
	}
}

func Test_formatJSONCheckDataPayload(t *testing.T) {
	t.Parallel()

	type args struct {
		checkInput string
	}

	type expectations struct {
		expectedOutput []byte
		expectError    bool
	}

	expectedJSON := func(value string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"data":[{"value":"%s"}]}`,
			version.Long(),
			agent.Variant,
			value,
		))
	}

	tests := []struct {
		name   string
		args   args
		expect expectations
	}{
		{
			name: "+valid",
			args: args{
				checkInput: "cpu_load: 1.5",
			},
			expect: expectations{
				expectedOutput: expectedJSON("cpu_load: 1.5"),
			},
		},
		{
			name: "+emptyResponse",
			args: args{
				checkInput: "",
			},
			expect: expectations{
				expectedOutput: expectedJSON(""),
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			output, err := formatJSONCheckDataPayload(tt.args.checkInput)

			if (err != nil) != tt.expect.expectError {
				t.Fatalf(
					"formatCheckDataPayload(): unexpected error state: got err=%v, want error=%v",
					err,
					tt.expect.expectError,
				)
			}

			if diff := cmp.Diff(tt.expect.expectedOutput, output); diff != "" {
				t.Errorf("formatCheckDataPayload(): output mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Test_formatCheckErrorPayload(t *testing.T) {
	t.Parallel()

	type args struct {
		errText string
		isJSON  bool
	}

	type expectations struct {
		expectedOutput []byte
		expectError    bool
	}

	expectedJSON := func(errorMsg string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"data":[{"error":"%s"}]}`,
			version.Long(),
			agent.Variant,
			errorMsg,
		))
	}

	tests := []struct {
		name   string
		args   args
		expect expectations
	}{
		{
			name: "+plainTextError",
			args: args{
				errText: "Timeout occurred",
				isJSON:  false,
			},
			expect: expectations{
				expectedOutput: formatError("Timeout occurred"),
			},
		},
		{
			name: "+JSONError",
			args: args{
				errText: "Timeout occurred",
				isJSON:  true,
			},
			expect: expectations{
				expectedOutput: expectedJSON("Timeout occurred"),
			},
		},
		{
			name: "+emptyPlainTextError",
			args: args{
				errText: "",
				isJSON:  false,
			},
			expect: expectations{
				expectedOutput: formatError(""),
			},
		},
		{
			name: "+emptyJSONError",
			args: args{
				errText: "",
				isJSON:  true,
			},
			expect: expectations{
				expectedOutput: expectedJSON(""),
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			output, err := formatCheckErrorPayload(tt.args.errText, tt.args.isJSON)

			if (err != nil) != tt.expect.expectError {
				t.Fatalf("formatCheckErrorPayload(): unexpected error state: got err=%v, want error=%v",
					err,
					tt.expect.expectError,
				)
			}

			if diff := cmp.Diff(tt.expect.expectedOutput, output); diff != "" {
				t.Errorf("formatCheckErrorPayload(): output mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Test_formatJSONParsingError(t *testing.T) {
	t.Parallel()

	type args struct {
		errText string
	}

	type expectations struct {
		expectedOutput []byte
		expectError    bool
	}

	expectedJSON := func(errorMsg string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"error":"%s"}`,
			version.Long(),
			agent.Variant,
			errorMsg,
		))
	}

	tests := []struct {
		name   string
		args   args
		expect expectations
	}{
		{
			name: "+JSONParsingError",
			args: args{
				errText: "Invalid JSON format",
			},
			expect: expectations{
				expectedOutput: expectedJSON("Invalid JSON format"),
			},
		},
		{
			name: "+emptyJSONParsingError",
			args: args{
				errText: "",
			},
			expect: expectations{
				expectedOutput: expectedJSON(""),
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			output, err := formatJSONParsingError(tt.args.errText)

			if (err != nil) != tt.expect.expectError {
				t.Fatalf(
					"formatJSONParsingError(): unexpected error state: got err=%v, want error=%v",
					err,
					tt.expect.expectError,
				)
			}

			if diff := cmp.Diff(tt.expect.expectedOutput, output); diff != "" {
				t.Errorf("formatJSONParsingError(): output mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Test_processJSONRequest(t *testing.T) {
	t.Parallel()

	type helperFunc func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler)

	type testCase struct {
		name       string
		rawRequest []byte
		setup      helperFunc
	}

	expectedJSONError := func(errorMsg string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"error":"%s"}`,
			version.Long(),
			agent.Variant,
			errorMsg,
		))
	}

	expectedJSONErrorPayload := func(errText string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"data":[{"error":"%s"}]}`,
			version.Long(),
			agent.Variant,
			errText,
		))
	}

	expectedJSONValuePayload := func(value string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"data":[{"value":"%s"}]}`,
			version.Long(),
			agent.Variant,
			value,
		))
	}

	tests := []testCase{
		{
			name:       "+successResponse",
			rawRequest: []byte(`{"request":"passive checks","data":[{"key":"system.uptime","timeout":1}]}`),
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				successResult := "some-result"

				sched.
					On("PerformTask", "system.uptime", time.Second, uint64(agent.PassiveChecksClientID)).
					Return(&successResult, nil)

				conn.On("Write", expectedJSONValuePayload(successResult)).Return(nil)
			},
		},
		{
			name:       "-invalidJSONRequest",
			rawRequest: []byte(`{"invalid`),
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				errString := errs.New("failed to unmarshall json request into passiveChecksRequest").Error()
				conn.On("Write", expectedJSONError(errString)).Return(nil)
			},
		},
		{
			name:       "-performTaskFails",
			rawRequest: []byte(`{"request":"passive checks","data":[{"key":"system.uptime","timeout":1}]}`),
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				errReturn := errs.New("task failure")

				sched.
					On("PerformTask", "system.uptime", time.Second, uint64(agent.PassiveChecksClientID)).
					Return(nil, errReturn)

				conn.On("Write", expectedJSONErrorPayload(errReturn.Error())).Return(nil)
			},
		},
		{
			name:       "-nilResult",
			rawRequest: []byte(`{"request":"passive checks","data":[{"key":"system.uptime","timeout":1}]}`),
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				sched.
					On("PerformTask", "system.uptime", time.Second, uint64(agent.PassiveChecksClientID)).
					Return(nil, nil)
			},
		},
		{
			name:       "-resultWriteFails",
			rawRequest: []byte(`{"request":"passive checks","data":[{"key":"system.uptime","timeout":1}]}`),
			setup: func(t *testing.T, conn *mockcomms.ConnectionInterface, sched *mockscheduler.Scheduler) {
				t.Helper()

				successResult := "some-result"

				sched.
					On("PerformTask", "system.uptime", time.Second, uint64(agent.PassiveChecksClientID)).
					Return(&successResult, nil)

				conn.On("Write", expectedJSONValuePayload(successResult)).Return(errs.New("write failed"))

				conn.On("RemoteIP").Return("127.0.0.1")
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			conn := mockcomms.NewConnectionInterface(t)
			sched := mockscheduler.NewScheduler(t)

			tt.setup(t, conn, sched)

			processJSONRequest(conn, sched, tt.rawRequest)

			conn.AssertExpectations(t)
			sched.AssertExpectations(t)
		})
	}
}
