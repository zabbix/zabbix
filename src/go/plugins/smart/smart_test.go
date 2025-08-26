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

package smart

import (
	"encoding/json"
	"fmt"
	"reflect"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/agent2/plugins/smart/mock"
	"golang.zabbix.com/sdk/errs"
)

const (
	nvme = `{
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/nvme0",
		  "type": "nvme"
		},
		"model_name": "INTEL SSDPEKNW512G8H",
		"serial_number": "BTNH115603K7512A",
		"firmware_version": "HPS1",
		"smart_status": {
		  "passed": true
		},
		"nvme_smart_health_information_log": {
		  "critical_warning": 0,
		  "temperature": 25,
		  "percentage_used": 0,
		  "power_on_hours": 2222,
		  "media_errors": 0
		}
	  }`

	nvmeMediaErrorOverflow = `{
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/nvme0",
		  "type": "nvme"
		},
		"model_name": "INTEL SSDPEKNW512G8H",
		"serial_number": "BTNH115603K7512A",
		"firmware_version": "HPS1",
		"smart_status": {
		  "passed": true
		},
		"nvme_smart_health_information_log": {
		  "critical_warning": 0,
		  "temperature": 25,
		  "percentage_used": 0,
		  "power_on_hours": 2222,
		  "media_errors": 12345678901234567890
		}
	  }`

	hdd = `{
		"json_format_version": [
			1,
			0
		],
		"smartctl": {
			"version": [
				7,
				2
			],
			"svn_revision": "5155",
			"platform_info": "x86_64-linux-5.13.0-30-generic",
			"build_info": "(local build)",
			"argv": [
				"smartctl",
				"-a",
				"-j",
				"/dev/sda"
			],
			"exit_status": 0
		},
		"device": {
			"name": "/dev/sda",
			"info_name": "/dev/sda [SAT]",
			"type": "sat",
			"protocol": "ATA"
		},
		"model_family": "Seagate Surveillance",
		"model_name": "ST1000VX000-1ES162",
		"serial_number": "Z4Y7SJBD",
		"wwn": {
			"naa": 5,
			"oui": 3152,
			"id": 2071267458
		},
		"firmware_version": "CV26",
		"rotation_rate": 7200,
		"ata_smart_data": {
			"self_test": {
				"status": {
					"value": 0,
					"string": "completed without error",
					"passed": true
				}
			},
			"capabilities": {
				"self_tests_supported": true
			}
		},
		"ata_smart_attributes": {
 			"table": [
				{
					"id": 1,
					"name": "Raw_Read_Error_Rate",
					"raw": {
						"value": 182786912,
						"string": "182786912"
					}
				},
				{
					"id": 3,
					"name": "Spin_Up_Time",
					"raw": {
						"value": 0,
						"string": "0"
					}
				}
			]
		},
		"power_on_time": {
			"hours": 39153
		},
		 "temperature": {
			"current": 30
		}
	}`

	ssd = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "self_test": {
			"status": {
			  "passed": true
			}
		  },
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": true
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}			
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`

	ssdUnknown = `
	  {
		  "json_format_version": [
			1,
			0
		  ],
		  "smartctl": {
			"exit_status": 0
		  },
		  "device": {
			"name": "/dev/sda",
			"info_name": "/dev/sda",
			"type": "ata",
			"protocol": "ATA"
		  },
		  "model_name": "TS128GMTS800",
		  "serial_number": "D486530350",
		  "firmware_version": "O1225G",
		  "rotation_rate": 0,
		  "smart_status": {
			"passed": true
		  },
		  "ata_smart_data": {
			"self_test": {
			  "status": {
				"passed": true
			  }
			},
			"capabilities": {
			  "values": [
				113,
				2
			  ],
			  "self_tests_supported": true
			}
		  },
		  "ata_smart_attributes": {
			 "table": [
			  {
				"name": "Raw_Read_Error_Rate",
				"value": 100,
				"raw": {
				  "value": 0,
				  "string": "0"
				}
			  },
			  {
				"name": "Unknown_Attribute",
			   "value": 0,
			   "raw": {
				 "value": 0,
				 "string": "0"
			   }
			 },
			  {
				 "name": "Reallocated_Sector_Ct",
				"raw": {
				  "value": 10,
				  "string": "10"
				}
			  }
			]
		  },
		  "power_on_time": {
			"hours": 732
		  },
		   "temperature": {
			"current": 18
		  }
		}`
)

const (
	ataSelfTestNotCapable = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": false
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}			
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`

	//nolint:gosec
	ataSelfTestNotPassed = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "self_test": {
			"status": {
			  "value": 80,
			  "string": "completed with error (electrical test element)",
			  "passed": false
			}
		  },
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": true
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}			
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`

	ataSelfTestInProgress = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "self_test": {
			"status": {
			  "value": 248,
			   "string": "in progress, 80% remaining",
               "remaining_percent": 80
			}
		  },
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": true
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}			
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`

	ataSelfTestInterrupted = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "self_test": {
			"status": {
			  "value": 16,
			  "string": "was aborted by the host"
			}
		  },
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": true
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}			
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`
)

var (
	table1    = table{"test1", 1, 11}
	table2    = table{"test2", 2, 22}
	table3    = table{"test3", 3, 33}
	table4    = table{"test4", 4, 44}
	attrTable = table{"Spin_Up_Time", 5, 55}
	unknown   = table{"Unknown_Attribute", 0, 0}
)

func intToPtr(v int) *int {
	return &v
}

func boolToPtr(v bool) *bool {
	return &v
}

func Test_diskGetSingle(t *testing.T) {
	t.Parallel()

	type args struct {
		path     string
		raidType string
	}

	type fields struct {
		ctlOutput []byte
		ctlErr    error
	}

	type want struct {
		output  []byte
		wantErr bool
	}

	tests := []struct {
		name   string
		args   args
		fields fields
		want   want
	}{
		{
			"+normalValues",
			args{
				path:     "path",
				raidType: "rt",
			},
			fields{
				ctlOutput: []byte(nvme),
				ctlErr:    nil,
			},
			want{
				output: []byte(
					`{"critical_warning":0,"disk_type":"nvme","error":"","exit_status":0,"firmware_version":"HPS1",` +
						`"media_errors":0,"model_name":"INTEL SSDPEKNW512G8H",` +
						`"percentage_used":0,"power_on_time":2222,"self_test_in_progress":null,` +
						`"self_test_passed":null,"serial_number":"BTNH115603K7512A","temperature":25}`,
				),
				wantErr: false,
			},
		},
		{
			"+valueOverflow",
			args{
				path:     "path",
				raidType: "rt",
			},
			fields{
				ctlOutput: []byte(nvmeMediaErrorOverflow),
				ctlErr:    nil,
			},
			want{
				output: []byte(
					`{"critical_warning":0,"disk_type":"nvme","error":"","exit_status":0,"firmware_version":"HPS1",` +
						`"media_errors":12345678901234567890,"model_name":"INTEL SSDPEKNW512G8H",` +
						`"percentage_used":0,"power_on_time":2222,"self_test_in_progress":null,` +
						`"self_test_passed":null,"serial_number":"BTNH115603K7512A","temperature":25}`,
				),
				wantErr: false,
			},
		},
		{
			"-ctlError",
			args{
				path:     "path",
				raidType: "rt",
			},
			fields{
				ctlOutput: []byte{},
				ctlErr:    errs.New("test"),
			},
			want{
				output:  []byte{},
				wantErr: true,
			},
		},
		{
			"-jsonFormatError",
			args{
				path:     "path",
				raidType: "rt",
			},
			fields{
				ctlOutput: []byte(`{abc`),
				ctlErr:    nil,
			},
			want{
				output:  []byte{},
				wantErr: true,
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()
			mockCTL := mock.NewMockController(t)

			mockCTL.
				ExpectExecute().
				WillReturnOutput(tt.fields.ctlOutput).
				WillReturnError(tt.fields.ctlErr)

			p := &Plugin{
				ctl: mockCTL,
			}

			out, err := p.diskGetSingle(tt.args.path, tt.args.raidType)
			if (err != nil) != tt.want.wantErr {
				t.Errorf("diskGetSingle() wanted error to be %v, but got %q", tt.want.wantErr, err.Error())
			}

			if diff := cmp.Diff(string(out), string(tt.want.output)); diff != "" {
				t.Errorf("diskGetSingle() output differs from expected %s", diff)
			}

			err = mockCTL.ExpectationsWhereMet()
			if err != nil {
				t.Errorf("Mock expectations were not met %q", err.Error())
			}
		})
	}
}

func Test_setSingleDiskFields(t *testing.T) {
	var nilReference *bool

	type args struct {
		dev []byte
	}
	tests := []struct {
		name    string
		args    args
		wantOut map[string]interface{}
		wantErr bool
	}{
		{
			"nvme_device",
			args{[]byte(nvme)},
			map[string]interface{}{
				"critical_warning":      0,
				"disk_type":             "nvme",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "HPS1",
				"media_errors":          json.Number("0"),
				"model_name":            "INTEL SSDPEKNW512G8H",
				"percentage_used":       0,
				"power_on_time":         2222,
				"self_test_passed":      nilReference,
				"self_test_in_progress": nilReference,
				"serial_number":         "BTNH115603K7512A",
				"temperature":           25,
			},
			false,
		},
		{
			"mediaOverflow",
			args{[]byte(nvmeMediaErrorOverflow)},
			map[string]interface{}{
				"critical_warning":      0,
				"disk_type":             "nvme",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "HPS1",
				"media_errors":          json.Number("12345678901234567890"),
				"model_name":            "INTEL SSDPEKNW512G8H",
				"percentage_used":       0,
				"power_on_time":         2222,
				"self_test_passed":      nilReference,
				"self_test_in_progress": nilReference,
				"serial_number":         "BTNH115603K7512A",
				"temperature":           25,
			},
			false,
		},
		{
			"hdd_device",
			args{[]byte(hdd)},
			map[string]any{
				"critical_warning":      0,
				"disk_type":             "hdd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "CV26",
				"media_errors":          json.Number("0"),
				"model_name":            "ST1000VX000-1ES162",
				"percentage_used":       0,
				"power_on_time":         39153,
				"self_test_passed":      boolToPtr(true),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "Z4Y7SJBD",
				"temperature":           30,
				"raw_read_error_rate": singleRequestAttribute{
					Value: 182786912,
					Raw:   "182786912",
				},
				"spin_up_time": singleRequestAttribute{
					Value: 0,
					Raw:   "0",
				},
			},
			false,
		},
		{
			"ssd_device",
			args{[]byte(ssd)},
			map[string]any{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      boolToPtr(true),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			false,
		},
		{
			"ssd_device_with_unknown_attribute",
			args{[]byte(ssdUnknown)},
			map[string]interface{}{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      boolToPtr(true),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
			},
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotOut, err := setSingleDiskFields(tt.args.dev)
			if (err != nil) != tt.wantErr {
				t.Errorf("setSingleDiskFields() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			if diff := cmp.Diff(gotOut, tt.wantOut); diff != "" {
				t.Errorf("setSingleDiskFields() = \n%v\n, want \n%v\n", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_setSingleDiskFieldsWithSelfTest(t *testing.T) {
	t.Parallel()

	var nilReference *bool

	type args struct {
		dev []byte
	}

	tests := []struct {
		name    string
		args    args
		wantOut map[string]interface{}
		wantErr bool
	}{
		{
			"+valid", // self test in progress case
			args{[]byte(ataSelfTestInProgress)},
			map[string]interface{}{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      nilReference,
				"self_test_in_progress": boolToPtr(true),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			false,
		},
		{
			"+ataSelfTestNotCapable",
			args{[]byte(ataSelfTestNotCapable)},
			map[string]interface{}{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      nilReference,
				"self_test_in_progress": nilReference,
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			false,
		},
		{
			"+ataSelfTestNotPassed",
			args{[]byte(ataSelfTestNotPassed)},
			map[string]interface{}{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      boolToPtr(false),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			false,
		},
		{
			"+ataSelfTestInterrupted",
			args{[]byte(ataSelfTestInterrupted)},
			map[string]interface{}{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      boolToPtr(false),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			false,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotOut, err := setSingleDiskFields(tt.args.dev)
			if (err != nil) != tt.wantErr {
				t.Fatalf("setSingleDiskFields() w/ self-test error = %s, wantErr %t", err.Error(), tt.wantErr)
			}

			if diff := cmp.Diff(tt.wantOut, gotOut); diff != "" {
				t.Fatalf("setSingleDiskFields() %s", diff)
			}
		})
	}
}

func Test_setDiskFields(t *testing.T) {
	jsonSdaStr := `{
		"device": {"name": "/dev/sda","info_name": "/dev/sda [SAT]","type": "sat","protocol": "ATA"},"rotation_rate": 0
		}`
	sdaOutStr := map[string]interface{}{
		"device": map[string]interface{}{
			"name": "/dev/sda", "info_name": "/dev/sda [SAT]", "type": "sat", "protocol": "ATA",
		},
		"disk_name": "sda", "disk_type": "ssd", "rotation_rate": 0,
	}

	type args struct {
		deviceJsons map[string]jsonDevice
	}

	tests := []struct {
		name    string
		args    args
		want    []interface{}
		wantErr bool
	}{
		{"+one_drive", args{map[string]jsonDevice{"/dev/sda": {jsonData: jsonSdaStr}}}, []interface{}{sdaOutStr}, false},
		{"-failed_json", args{map[string]jsonDevice{"/dev/sda": {jsonData: `{"device":}`}}}, nil, true},
		{"-failed_device_data_json", args{map[string]jsonDevice{"/dev/sda": {jsonData: `{"device": foo,"rotation_rate": 0}`}}}, nil, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := setDiskFields(tt.args.deviceJsons)
			if (err != nil) != tt.wantErr {
				t.Errorf("setDiskFields() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if fmt.Sprint(got) != fmt.Sprint(tt.want) {
				t.Errorf("setDiskFields() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getRateFromJson(t *testing.T) {
	type args struct {
		in map[string]interface{}
	}
	tests := []struct {
		name    string
		args    args
		wantOut int
	}{
		{"rate", args{map[string]interface{}{"rotation_rate": 10}}, 10},
		{"multiple_fields", args{map[string]interface{}{"foobar": "abc", "rotation_rate": 10}}, 10},
		{"no_rate", args{map[string]interface{}{"foobar": "abc"}}, 0},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getRateFromJSON(tt.args.in); gotOut != tt.wantOut {
				t.Errorf("getRateFromJSON() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTypeFromJson(t *testing.T) {
	map1 := make(map[string]interface{})
	map1["device"] = map[string]interface{}{"type": "sat"}

	map2 := make(map[string]interface{})
	map2["device"] = map[string]interface{}{"type": "sat", "foobar": "abc"}

	map3 := make(map[string]interface{})
	map3["device"] = map[string]interface{}{"foobar": "abc"}

	type args struct {
		in map[string]interface{}
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{"type", args{map1}, "sat"},
		{"multiple_fields", args{map2}, "sat"},
		{"no_type", args{map3}, ""},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getTypeFromJSON(tt.args.in); gotOut != tt.wantOut {
				t.Errorf("getTypeFromJSON() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTablesFromJson(t *testing.T) {
	map1 := make(map[string]interface{})
	map1["table"] = []interface{}{table1, table2, attrTable}

	map2 := make(map[string]interface{})
	map2["table"] = []interface{}{table1, table2, table4}

	attrTable1 := map[string]interface{}{"ata_smart_attributes": map1}
	attrTable2 := map[string]interface{}{"ata_smart_attributes": map2}
	attrTable3 := map[string]interface{}{"ata_smart_attributes": nil}
	attrTable4 := map[string]interface{}{"ata_smart_attributes": []table{}}
	attrTable5 := map[string]interface{}{"ata_smart_attributes": map[string][]table{}}

	type args struct {
		in map[string]interface{}
	}
	tests := []struct {
		name string
		args args
		want []table
	}{
		{"attr_table", args{attrTable1}, []table{table1, table2, attrTable}},
		{"no_attr_table", args{attrTable2}, []table{table1, table2, table4}},
		{"no_table", args{attrTable3}, nil},
		{"incorrect_table_value", args{attrTable4}, nil},
		{"empty_map", args{attrTable5}, nil},
		{"no_ata_attributes", args{nil}, nil},
		{"empty_ata_attributes", args{map[string]interface{}{}}, nil},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getTablesFromJSON(tt.args.in); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("getTablesFromJSON() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getAttributeType(t *testing.T) {
	type args struct {
		devType string
		rate    int
		tables  []table
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"ssd_no_tables", args{"SAT", 0, nil}, "ssd"},
		{"ssd_tables_no_spin_up_table", args{"SAT", 0, []table{table1, table2, table4}}, "ssd"},
		{"hdd_no_tables", args{"SAT", 12, nil}, "hdd"},
		{"hdd_rate_spin_up_table", args{"SAT", 12, []table{table1, table2, table4, attrTable}}, "hdd"},
		{"hdd_no_rate_spin_up_table", args{"SAT", 0, []table{table1, table2, table4, attrTable}}, "hdd"},
		{"hdd_no_spin_up_table", args{"SAT", 12, []table{table1, table2, table4}}, "hdd"},
		{"unknown_no_attr_table", args{"unknown", 1000, []table{table1, table2, table4}}, "unknown"},
		{"unknown_value_table", args{"unknown", 1000, []table{table1, table2, table4, attrTable}}, "unknown"},
		{"unknown_no_rate_no_tables", args{"unknown", 0, nil}, "unknown"},
		{"unknown_no_rate_no_attr_table", args{"unknown", 0, []table{table1, table2, table4}}, "unknown"},
		{"unknown_no_rate_value_table", args{"unknown", 0, []table{table1, table2, table4, attrTable}}, "unknown"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getAttributeType(tt.args.devType, tt.args.rate, tt.args.tables); got != tt.want {
				t.Errorf("getAttributeType() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getAttributes(t *testing.T) {
	type args struct {
		in deviceParser
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{
			"attributes_set",
			args{deviceParser{SmartAttributes: smartAttributes{Table: []table{table1, table2}}}},
			"test1 test2",
		},
		{
			"attributes_table_empty",
			args{deviceParser{SmartAttributes: smartAttributes{Table: []table{}}}},
			"",
		},
		{
			"unknown_attributes_table_empty",
			args{deviceParser{SmartAttributes: smartAttributes{Table: []table{table1, unknown, table2}}}},
			"test1 test2",
		},
		{
			"attributes_missing",
			args{deviceParser{}},
			"",
		},
		{
			"parser_missing",
			args{},
			"",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getAttributes(tt.args.in); got != tt.want {
				t.Errorf("getAttributes() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getType(t *testing.T) {
	type args struct {
		devType string
		rate    int
		tables  []table
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{"ssd_no_tables", args{"SAT", 0, nil}, "ssd"},
		{"ssd_tables_no_spin_up_table", args{"SAT", 0, []table{table1, table2, table4}}, "ssd"},
		{"hdd_no_tables", args{"SAT", 12, nil}, "hdd"},
		{"hdd_rate_spin_up_table", args{"SAT", 12, []table{table1, table2, table4, attrTable}}, "hdd"},
		{"hdd_no_rate_spin_up_table", args{"SAT", 0, []table{table1, table2, table4, attrTable}}, "hdd"},
		{"hdd_no_spin_up_table", args{"SAT", 12, []table{table1, table2, table4}}, "hdd"},
		{"nvme_no_tables", args{"nvme", 1000, nil}, "nvme"},
		{"nvme_no_attr_table", args{"nvme", 1000, []table{table1, table2, table4}}, "nvme"},
		{"nvme_value_table", args{"nvme", 1000, []table{table1, table2, table4, attrTable}}, "nvme"},
		{"nvme_no_rate_no_tables", args{"nvme", 0, nil}, "nvme"},
		{"nvme_no_rate_no_attr_table", args{"nvme", 0, []table{table1, table2, table4}}, "nvme"},
		{"nvme_no_rate_value_table", args{"nvme", 0, []table{table1, table2, table4, attrTable}}, "nvme"},
		{"unknown_no_tables", args{"unknown", 1000, nil}, "unknown"},
		{"unknown_no_attr_table", args{"unknown", 1000, []table{table1, table2, table4}}, "unknown"},
		{"unknown_value_table", args{"unknown", 1000, []table{table1, table2, table4, attrTable}}, "unknown"},
		{"unknown_no_rate_no_tables", args{"unknown", 0, nil}, "unknown"},
		{"unknown_no_rate_no_attr_table", args{"unknown", 0, []table{table1, table2, table4}}, "unknown"},
		{"unknown_no_rate_value_table", args{"unknown", 0, []table{table1, table2, table4, attrTable}}, "unknown"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getType(tt.args.devType, tt.args.rate, tt.args.tables); gotOut != tt.wantOut {
				t.Errorf("getType() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTypeByRateAndAttr(t *testing.T) {
	type args struct {
		rate   int
		tables []table
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"zero_rate_zero_spin_up", args{0, []table{table1, table2}}, "ssd"},
		{"zero_rate_no_tables", args{0, nil}, "ssd"},
		{"negative_rate_no_tables", args{-1000, nil}, "ssd"},
		{"positive_rate_spin_up_table", args{12, []table{table1, table2, table3, attrTable}}, "hdd"},
		{"positive_rate_no_tables", args{12, nil}, "hdd"},
		{"zero_rate_spin_up_table", args{0, []table{table1, table2, table3, attrTable}}, "hdd"},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getTypeByRateAndAttr(tt.args.rate, tt.args.tables); got != tt.want {
				t.Errorf("getTypeByRate() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_validateParams(t *testing.T) {
	t.Parallel()

	type args struct {
		params []string
	}

	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{"+valid", args{[]string{"/dev/sda"}}, false},
		{"+keyNoParams", args{[]string{}}, false},
		{"+spaceHypen", args{[]string{"/dev/sda -B/some/file/path"}}, false},
		{"+manySpacesHypen", args{[]string{"/dev/sda    -B/some/file/path"}}, false},
		{"+tabHypen", args{[]string{"/dev/sda\t-B/some/file/path"}}, false},
		{"+noSpacesHypen", args{[]string{"/dev/sda-B/some/file/path"}}, false},
		{"+hypenInSpaces", args{[]string{"/dev/sda - B/some/file/path"}}, false},
		{"+hypenEnd", args{[]string{"/dev/sda-"}}, false},
		{"+empty", args{[]string{""}}, false},
		{"+twoParams", args{[]string{"/dev/sda", "megaraid"}}, false},
		{"+threeParams", args{[]string{"/dev/sda", "megaraid", "three"}}, false},
		{"-hypenStart", args{[]string{"-B/some/file/path"}}, true},
		{"-hypenStartSpace", args{[]string{"- B/some/file/path"}}, true},
		{"-hypenStartApostr", args{[]string{"'-B/some/file/path'"}}, true},
		{"-hypenStartApostrSpace", args{[]string{"'   -B/some/file/path'"}}, true},
		{"-hypenStartApostrTab", args{[]string{"'\t-B/some/file/path'"}}, true},
		{"-hypenStartApostrTabSpace", args{[]string{"'\t -B/some/file/path'"}}, true},
		{"-hypenStart2Apostr", args{[]string{"''-B/some/file/path''"}}, true},
		{"-hypenStart3Apostr", args{[]string{"'''-B/some/file/path'''"}}, true},
		{"-hypenStartApostrQuote", args{[]string{"\"-B/some/file/path\""}}, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := validateParams(tt.args.params)

			if (err != nil) != tt.wantErr {
				t.Fatalf("validateParams() error = %s, wantErr %t", err.Error(), tt.wantErr)
			}
		})
	}
}

//nolint:paralleltest
func Test_validateExport(t *testing.T) {
	type expect struct {
		exec bool
	}

	type fields struct {
		execErr      error
		execOut      []byte
		lastVerCheck time.Time
	}

	type args struct {
		params []string
	}

	tests := []struct {
		name    string
		expect  expect
		fields  fields
		args    args
		wantErr bool
	}{
		{
			"+valid",
			expect{true},
			fields{execOut: mock.OutputVersionValid},
			args{nil},
			false,
		},
		{
			"+nothingToValidate",
			expect{true},
			fields{execOut: mock.OutputVersionValid},
			args{nil},
			false,
		},
		{
			"+paramOk",
			expect{true},
			fields{execOut: mock.OutputVersionValid},
			args{[]string{"smth"}},
			false,
		},
		{
			"-badParam",
			expect{true},
			fields{execOut: mock.OutputVersionValid},
			args{[]string{"-Bsmth"}},
			true,
		},
		{
			"-badVersion",
			expect{true},
			fields{execOut: mock.OutputVersionInvalid},
			args{[]string{"smth"}},
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			lastVerCheck = tt.fields.lastVerCheck

			m := mock.NewMockController(t)
			if tt.expect.exec {
				m.ExpectExecute().
					WithArgs("-j", "-V").
					WillReturnOutput(tt.fields.execOut).
					WillReturnError(tt.fields.execErr)
			}

			p := &Plugin{ctl: m}
			err := p.validateExport(tt.args.params)

			if (err != nil) != tt.wantErr {
				t.Fatalf("validateExport(key, params) error = %s, wantErr %t", err.Error(), tt.wantErr)
			}
		})
	}
}
