<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	global $TRANSLATION;

	$TRANSLATION=array(

	"S_DATE_FORMAT_YMDHMS"=>		"d M H:i:s",
	"S_DATE_FORMAT_YMD"=>			"d M Y",
	"S_HTML_CHARSET"=>			"UTF-8",

//	acknow.php
	"S_ACKNOWLEDGES"=>			"Подтверждения", //FIXME?
	"S_ACKNOWLEDGE"=>			"Подтверждение", //FIXME?
	"S_ACKNOWLEDGE_ALARM_BY"=>		"Подтвердить сигнализацию как", //FIXME?
	"S_ADD_COMMENT_BY"=>			"Добавить комментарий как", //FIXME?
	"S_COMMENT_ADDED"=>			"Комментарий добавлен",
	"S_CANNOT_ADD_COMMENT"=>		"Невозможно добавить комментарий",
	"S_ALARM_ACKNOWLEDGES_BIG"=>		"ПОДТВЕРЖДЕНИЯ СИГНАЛИЗАЦИИ", //FIXME?

//	actionconf.php
	"S_CONFIGURATION_OF_ACTIONS"=>		"Настройка действий",
	"S_CONFIGURATION_OF_ACTIONS_BIG"=>	"НАСТРОЙКА ДЕЙСТВИЙ",
	"S_FILTER_HOST_GROUP"=>			"Фильтр: Группа узлов сети",
	"S_FILTER_HOST"=>			"Фильтр: Узел сети",
	"S_FILTER_TRIGGER"=>			"Фильтр: Триггер",
	"S_FILTER_TRIGGER_NAME"=>		"Фильтр: Название триггера",
	"S_FILTER_TRIGGER_SEVERITY"=>		"Фильтр: Важность триггера",
	"S_FILTER_WHEN_TRIGGER_BECOMES"=>	"Фильтр: Когда состояние триггера", //FIXME
	"S_ACTION_TYPE"=>			"Тип действия",
	"S_SEND_MESSAGE"=>			"Отправить сообщение",
	"S_REMOTE_COMMAND"=>			"Удаленная команда",
	"S_FILTER"=>				"Фильтр",
	"S_FILTER_TYPE"=>			"Тип фильтра",
	"S_TRIGGER_NAME"=>			"Название триггера",
	"S_TRIGGER_SEVERITY"=>			"Важность триггера",
	"S_TRIGGER_VALUE"=>			"Значение триггера",
	"S_TIME_PERIOD"=>			"Период времени",
	"S_TRIGGER_DESCRIPTION"=>		"Описание триггера",
	"S_CONDITIONS"=>			"Условия",
	"S_CONDITION"=>				"Условие",
	"S_NO_CONDITIONS_DEFINED"=>		"Условия не определены",
	"S_ACTIONS_DELETED"=>			"Действия удалены",
	"S_CANNOT_DELETE_ACTIONS"=>		"Невозможно удалить действия",

//	actions.php
	"S_ACTIONS"=>				"Действия",
	"S_ACTIONS_BIG"=>			"ДЕЙСТВИЯ",
	"S_ACTION_ADDED"=>			"Действие добавлено",
	"S_CANNOT_ADD_ACTION"=>			"Невозможно добавить действие",
	"S_ACTION_UPDATED"=>			"Действие обновлено",
	"S_CANNOT_UPDATE_ACTION"=>		"Невозможно обновить действие",
	"S_ACTION_DELETED"=>			"Действие удалено",
	"S_CANNOT_DELETE_ACTION"=>		"Невозможно удалить действие",
	"S_SCOPE"=>				"Диапазон",
	"S_SEND_MESSAGE_TO"=>			"Отправить сообщение",
	"S_WHEN_TRIGGER"=>			"Когда триггер",
	"S_DELAY"=>				"Задержка",
	"S_SUBJECT"=>				"Тема",
	"S_ON"=>				"ВКЛ",
	"S_OFF"=>				"ВЫКЛ",
	"S_NO_ACTIONS_DEFINED"=>		"Действия не определены",
	"S_SINGLE_USER"=>			"Один пользователь",
	"S_USER_GROUP"=>			"Группа пользователей",
	"S_GROUP"=>				"Группа",
	"S_USER"=>				"Пользователь",
	"S_ON_OR_OFF"=>				"ВКЛ или ВЫКЛ",
	"S_DELAY_BETWEEN_MESSAGES_IN_SEC"=>	"Задержка между сообщениями (секунды)",
	"S_DELAY_BETWEEN_EXECUTIONS_IN_SEC"=>			"Задержка между запусками (секунды)",
	"S_MESSAGE"=>				"Сообщение",
	"S_THIS_TRIGGER_ONLY"=>			"Только этот триггер",
	"S_ALL_TRIGGERS_OF_THIS_HOST"=>		"Все триггеры этого узла сети",
	"S_ALL_TRIGGERS"=>			"Все триггеры",
	"S_USE_IF_TRIGGER_SEVERITY"=>		"Использовать, если важность триггера равна или больше, чем",
	"S_NOT_CLASSIFIED"=>			"Не классифицировано",
	"S_INFORMATION"=>			"Уведомление",
	"S_WARNING"=>				"Предупреждение",
	"S_AVERAGE"=>				"Средняя",
	"S_HIGH"=>				"Высокая",
	"S_DISASTER"=>				"Чрезвычайная",
	"S_REPEAT"=>				"Повтор",
	"S_REPEATS"=>				"Повторы",
	"S_NO_REPEATS"=>			"Без повторов",
	"S_NUMBER_OF_REPEATS"=>			"Количество повторов",
	"S_DELAY_BETWEEN_REPEATS"=>		"Задержка между повторам",
	"S_CREATE_ACTION"=>			"Создать действие",

//	alarms.php
	"S_ALARMS"=>				"Сигнализации", //FIXME?
	"S_ALARMS_SMALL"=>			"Сигнализации", //FIXME?
	"S_ALARMS_BIG"=>			"СИГНАЛИЗАЦИИ", //FIXME?
	"S_SHOW_ONLY_LAST_100"=>		"Показать только последние 100",
	"S_SHOW_ALL"=>				"Показать все",
	"S_TIME"=>				"Время",
	"S_STATUS"=>				"Статус",
	"S_DURATION"=>				"Длительность",
	"S_SUM"=>				"Сумма",
	"S_TRUE_BIG"=>				"ИСТИНА",
	"S_FALSE_BIG"=>				"ЛОЖЬ",
	"S_DISABLED_BIG"=>			"ДЕАКТИВИРОВАНО", //FIXME?
	"S_UNKNOWN_BIG"=>			"НЕИЗВЕСТНО",

//	actions.php
	"S_HISTORY_OF_ACTIONS_BIG"=>		"ИСТОРИЯ ДЕЙСТВИЙ",
	"S_LATEST_ACTIONS"=>			"Последние действия",
	"S_ALERTS_BIG"=>			"ОПОВЕЩЕНИЯ",
	"S_TYPE"=>				"Тип",
	"S_RECIPIENTS"=>			"Получатель(-и)",
	"S_ERROR"=>				"Ошибка",
	"S_SENT"=>				"отправлено",
	"S_NOT_SENT"=>				"не отправлено",
	"S_NO_ACTIONS_FOUND"=>			"Действия не найдены",
	"S_SHOW_NEXT_100"=>			"Показать следующие 100",
	"S_SHOW_PREVIOUS_100"=>			"Показать предыдущие 100",

//	charts.php
	"S_CUSTOM_GRAPHS"=>			"Пользовательские графики",
	"S_GRAPHS_BIG"=>			"ГРАФИКИ",
	"S_NO_GRAPHS_TO_DISPLAY"=>		"Нет графиков для отображения",
	"S_SELECT_GRAPH_TO_DISPLAY"=>		"Выберите график для отображения",
	"S_PERIOD"=>				"Период",
	"S_1H"=>				"1ч",
	"S_2H"=>				"2ч",
	"S_4H"=>				"4ч",
	"S_8H"=>				"8ч",
	"S_12H"=>				"12ч",
	"S_24H"=>				"24ч",
	"S_WEEK_SMALL"=>			"неделя",
	"S_MONTH_SMALL"=>			"месяц",
	"S_YEAR_SMALL"=>			"год",
	"S_KEEP_PERIOD"=>			"Сохранять период",
	"S_ON_C"=>				"Вкл",
	"S_OFF_C"=>				"Выкл",
	"S_MOVE"=>				"Сдвинуть",
	"S_NAVIGATE"=>				"Управление",
	"S_INCREASE"=>				"Увеличить",
	"S_DECREASE"=>				"Уменьшить",
	"S_NAVIGATE"=>				"Управление",
	"S_RIGHT_DIR"=>				"Вправо",
	"S_LEFT_DIR"=>				"Влево",
	"S_SELECT_GRAPH_DOT_DOT_DOT"=>		"Выберите график...",

// Colors
	"S_BLACK"=>				"Черный",
	"S_BLUE"=>				"Синий",
	"S_CYAN"=>				"Голубой",
	"S_DARK_BLUE"=>				"Темно-синий",
	"S_DARK_GREEN"=>			"Темно-зеленый",
	"S_DARK_RED"=>				"Темно-красный",
	"S_DARK_YELLOW"=>			"Темно-желтый",
	"S_GREEN"=>				"Зеленый",
	"S_RED"=>				"Красный",
	"S_WHITE"=>				"Белый",
	"S_YELLOW"=>				"Желтый",

//	config.php
	"S_CANNNOT_UPDATE_VALUE_MAP"=>		"Невозможно обновить преобразование значения",
	"S_VALUE_MAP_ADDED"=>			"Преобразование значения добавлено",
	"S_CANNNOT_ADD_VALUE_MAP"=>		"Невозможно добавить преобразование значения",
	"S_VALUE_MAP_DELETED"=>			"Преобразование значение удалено",
	"S_CANNNOT_DELETE_VALUE_MAP"=>		"Невозможно удалить преобразование значения",
	"S_VALUE_MAP_UPDATED"=>			"Преобразование значения обновлено",
	"S_VALUE_MAPPING_BIG"=>			"ПРЕОБРАЗОВАНИЕ ЗНАЧЕНИЙ",
	"S_VALUE_MAPPING"=>			"Преобразование значений",
	"S_VALUE_MAP"=>				"Преобразование значения",
	"S_MAPPING"=>				"Преобразование",
	"S_NEW_MAPPING"=>			"Новое преобразование",
	"S_NO_MAPPING_DEFINED"=>		"Преобразования не определены",
	"S_CREATE_VALUE_MAP"=>			"Создать преобразование значения",
	"S_CONFIGURATION_OF_ZABBIX"=>		"Настройка ZABBIX",
	"S_CONFIGURATION_OF_ZABBIX_BIG"=>	"НАСТРОЙКА ZABBIX",
	"S_CONFIGURATION_UPDATED"=>		"Настройки обновлены",
	"S_CONFIGURATION_WAS_NOT_UPDATED"=>	"Настройки не обновлены",
	"S_ADDED_NEW_MEDIA_TYPE"=>		"Добавлен новый тип средства передачи",
	"S_NEW_MEDIA_TYPE_WAS_NOT_ADDED"=>	"Новый тип средства передачи не добавлен",
	"S_MEDIA_TYPE_UPDATED"=>		"Тип средства передачи обновлен",
	"S_MEDIA_TYPE_WAS_NOT_UPDATED"=>	"Тип средства передачи не обновлен",
	"S_MEDIA_TYPE_DELETED"=>		"Тип средства передачи удален",
	"S_MEDIA_TYPE_WAS_NOT_DELETED"=>	"Тип средства передачи не удален",
	"S_CONFIGURATION"=>			"Настройка",
	"S_DO_NOT_KEEP_ACTIONS_OLDER_THAN"=>	"Не хранить действия старее чем (дни)",
	"S_DO_NOT_KEEP_EVENTS_OLDER_THAN"=>	"Не хранить события старее чем (дни)",
	"S_MEDIA_TYPES_BIG"=>			"ТИПЫ СРЕДСТВ ПЕРЕДАЧИ",
	"S_NO_MEDIA_TYPES_DEFINED"=>		"Типы средств передачи не определены",
	"S_SMTP_SERVER"=>			"SMTP сервер",
	"S_SMTP_HELO"=>				"SMTP приветствие",
	"S_SMTP_EMAIL"=>			"SMTP адрес электронной почты",
	"S_SCRIPT_NAME"=>			"Название сценария",
	"S_DELETE_SELECTED_MEDIA"=>		"Удалить выбранное средство передачи?",
	"S_DELETE_SELECTED_IMAGE"=>		"Удалить выбранное изображение?",
	"S_HOUSEKEEPER"=>			"Сборка 'мусора'",
	"S_MEDIA_TYPES"=>			"Типы средств передачи",
	"S_ESCALATION_RULES"=>			"Правила эскалации",
	"S_ESCALATION"=>			"Эскалация",
	"S_ESCALATION_RULES_BIG"=>		"ПРАВИЛА ЭСКАЛАЦИИ",
	"S_NO_ESCALATION_RULES_DEFINED"=>	"Правила эскалации не определены",
	"S_NO_ESCALATION_DETAILS"=>		"Нет подробностей эскалации",
	"S_ESCALATION_DETAILS_BIG"=>		"ПОДРОБНОСТИ ЭСКАЛАЦИИ",
	"S_ESCALATION_ADDED"=>			"Эскалация добавлена",
	"S_ESCALATION_WAS_NOT_ADDED"=>		"Эскалация не добавлена",
	"S_ESCALATION_RULE_ADDED"=>		"Правило эскалации добавлено",
	"S_ESCALATION_RULE_WAS_NOT_ADDED"=>	"Правило эскалации не добавлено",
	"S_ESCALATION_RULE_UPDATED"=>		"Правило эскалации обновлено",
	"S_ESCALATION_RULE_WAS_NOT_UPDATED"=>	"Правило эскалации не обновлено",
	"S_ESCALATION_RULE_DELETED"=>		"Правило эскалации удалено",
	"S_ESCALATION_RULE_WAS_NOT_DELETED"=>	"Правило эскалации не удалено",
	"S_ESCALATION_UPDATED"=>		"Эскалация обновлена",
	"S_ESCALATION_WAS_NOT_UPDATED"=>	"Эскалация не обновлена",
	"S_ESCALATION_DELETED"=>		"Эскалация удалена",
	"S_ESCALATION_WAS_NOT_DELETED"=>	"Эскалация не удалена",
	"S_ESCALATION_RULE"=>			"Правило эскалации",
	"S_DO"=>				"Произвести",
	"S_DEFAULT"=>				"По умолчанию",
	"S_IS_DEFAULT"=>			"По умолчанию",
	"S_LEVEL"=>				"Уровень",
	"S_DELAY_BEFORE_ACTION"=>		"Задержка перед действием",
	"S_IMAGES"=>				"Изображения",
	"S_IMAGE"=>				"Изображение",
	"S_IMAGES_BIG"=>			"ИЗОБРАЖЕНИЕ",
	"S_ICON"=>				"Пиктограмма",
	"S_NO_IMAGES_DEFINED"=>			"Изображения не определены",
	"S_BACKGROUND"=>			"Фон",
	"S_UPLOAD"=>				"Загрузить",
	"S_IMAGE_ADDED"=>			"Изображение добавлено",
	"S_CANNOT_ADD_IMAGE"=>			"Невозможно добавить изображение",
	"S_IMAGE_DELETED"=>			"Изображение удалено",
	"S_CANNOT_DELETE_IMAGE"=>		"Невозможно удалить изображение",
	"S_IMAGE_UPDATED"=>			"Изображение обновлено",
	"S_CANNOT_UPDATE_IMAGE"=>		"Невозможно обновить изображение",
	"S_UPDATE_SELECTED_IMAGE"=>		"Обновить выбранное изображение?",
	"S_AUTOREGISTRATION"=>			"Авторегистрация",
	"S_AUTOREGISTRATION_RULES_BIG"=>	"ПРАВИЛА АВТОРЕГИСТРАЦИИ",
	"S_PRIORITY"=>				"Приоритет",
	"S_PATTERN"=>				"Шаблон",
	"S_NO_AUTOREGISTRATION_RULES_DEFINED"=>	"Правила авторегистрации не определены",
	"S_AUTOREGISTRATION_ADDED"=>		"Авторегистрация добавлена",
	"S_CANNOT_ADD_AUTOREGISTRATION"=>	"Невозможно добавить авторегистрацию",
	"S_AUTOREGISTRATION_UPDATED"=>		"Авторегистрация обновлена",
	"S_AUTOREGISTRATION_WAS_NOT_UPDATED"=>	"Авторегистрация не была обновлена",
	"S_AUTOREGISTRATION_DELETED"=>		"Авторегистрация удалена",
	"S_AUTOREGISTRATION_WAS_NOT_DELETED"=>	"Авторегистрация не была удалена",
	"S_OTHER"=>				"Прочее",
	"S_OTHER_PARAMETERS"=>			"Прочие параметры",
	"S_REFRESH_UNSUPPORTED_ITEMS"=>		"Обновлять неподдерживаемые элементы данных (секунды)",
	"S_CREATE_MEDIA_TYPE"=>			"Создать тип средства передачи",
	"S_CREATE_IMAGE"=>			"Создать изображение",
	"S_CREATE_RULE"=>			"Создать правило",
	"S_WORKING_TIME"=>			"Рабочее время",

//	Latest values
	"S_LATEST_VALUES"=>			"Последние значения",
	"S_NO_PERMISSIONS"=>			"Нет прав !",
	"S_LATEST_DATA_BIG"=>			"ПОСЛЕДНИЕ ДАННЫЕ",
	"S_ALL_SMALL"=>				"все",
	"S_ALL"=>				"Все",
	"S_MINUS_ALL_MINUS"=>			"- все -",
	"S_MINUS_OTHER_MINUS"=>			"- прочее -",
	"S_DESCRIPTION_LARGE"=>			"ОПИСАНИЕ",
	"S_DESCRIPTION_SMALL"=>			"Описание",
	"S_GRAPH"=>				"График",
	"S_TREND"=>				"Динамика изменения",
	"S_COMPARE"=>				"Сравнить",

//	Footer
	"S_ZABBIX_VER"=>			"ZABBIX 1.1.5",
	"S_COPYRIGHT_BY"=>			"Copyright 2001-2006 by ",
	"S_CONNECTED_AS"=>			"Подключен как",
	"S_SIA_ZABBIX"=>			"SIA Zabbix",

//	graph.php
	"S_CONFIGURATION_OF_GRAPH"=>		"Настройка графика",
	"S_CONFIGURATION_OF_GRAPH_BIG"=>	"НАСТРОЙКА ГРАФИКА",
	"S_ITEM_ADDED"=>			"Элемент добавлен",
	"S_ITEM_UPDATED"=>			"Элемент обновлен",
	"S_SORT_ORDER_UPDATED"=>		"Порядок сортировки обновлен",
	"S_CANNOT_UPDATE_SORT_ORDER"=>		"Невозможность обновить порядок сортировки",
	"S_DISPLAYED_PARAMETERS_BIG"=>		"ОТОБРАЖАЕМЫЕ ПАРАМЕТРЫ",
	"S_SORT_ORDER"=>			"Порядок сортировки",
	"S_PARAMETER"=>				"Параметр",
	"S_COLOR"=>				"Цвет",
	"S_UP"=>				"Вверх",
	"S_DOWN"=>				"Вниз",
	"S_NEW_ITEM_FOR_THE_GRAPH"=>		"Новый элемент для графика",
	"S_SORT_ORDER_1_100"=>			"Порядок сортировки (0->100)",
	"S_YAXIS_SIDE"=>			"Расположение оси Y",
	"S_LEFT"=>				"По левому краю",
	"S_FUNCTION"=>				"Функция",
	"S_MIN_SMALL"=>				"минимальное", //FIXME?
	"S_AVG_SMALL"=>				"среднее", //FIXME?
	"S_MAX_SMALL"=>				"максимальное", //FIXME?
	"S_DRAW_STYLE"=>			"Способ черчения", //FIXME?
	"S_SIMPLE"=>				"Простой",
	"S_AGGREGATED"=>			"Агрегированный",
	"S_AGGREGATED_PERIODS_COUNT"=>			"Aggregated periods count", //FIXME

//	graphs.php
	"S_CONFIGURATION_OF_GRAPHS"=>		"Настройка графиков",
	"S_CONFIGURATION_OF_GRAPHS_BIG"=>	"НАСТРОЙКА ГРАФИКОВ",
	"S_GRAPH_ADDED"=>			"График добавлен",
	"S_GRAPH_UPDATED"=>			"График обновлен",
	"S_CANNOT_UPDATE_GRAPH"=>		"Невозможно обновить график",
	"S_GRAPH_DELETED"=>			"График удален",
	"S_CANNOT_DELETE_GRAPH"=>		"Невозможно удалить график",
	"S_CANNOT_ADD_GRAPH"=>			"Невозможно добавить график",
	"S_ID"=>				"Id",
	"S_NO_GRAPHS_DEFINED"=>			"Графики не определены",
	"S_DELETE_GRAPH_Q"=>			"Удалить график?",
	"S_YAXIS_TYPE"=>			"Тип оси Y",
	"S_YAXIS_MIN_VALUE"=>			"Минимальное значение оси Y",
	"S_YAXIS_MAX_VALUE"=>			"Максимальное значение оси Y",
	"S_CALCULATED"=>			"Подсчитываемое",
	"S_FIXED"=>				"Фиксированное",
	"S_CREATE_GRAPH"=>			"Создать график",
	"S_SHOW_WORKING_TIME"=>			"Показывать рабочее время",
	"S_SHOW_TRIGGERS"=>			"Показывать триггеры",

//	history.php
	"S_LAST_HOUR_GRAPH"=>			"График за последний час",
	"S_VALUES_OF_LAST_HOUR"=>		"Значения за последний час",
	"S_500_LATEST_VALUES"=>			"500 последних значений",
	"S_GRAPH_OF_SPECIFIED_PERIOD"=>		"График за указанный период",
	"S_VALUES_OF_SPECIFIED_PERIOD"=>	"Значения за указанный период",
	"S_VALUES_IN_PLAIN_TEXT_FORMAT"=>	"Значения в простом текстовом формате",//FIXME?
	"S_TIMESTAMP"=>				"Отметка времени",
	"S_LOCAL"=>				"Local",
	"S_SOURCE"=>				"Источник", //FIXME?

	"S_SHOW_SELECTED"=>			"Показать выбранное",
	"S_HIDE_SELECTED"=>			"Не показывать выбранное",
	"S_MARK_SELECTED"=>			"Пометить выбранное", //FIXME?
	"S_MARK_OTHERS"=>			"Пометить невыбранное", //FIXME?

	"S_AS_RED"=>				"красным", //FIXME?
	"S_AS_GREEN"=>				"зеленым", //FIXME?
	"S_AS_BLUE"=>				"синим", //FIXME?

//	hosts.php
	"S_APPLICATION"=>			"Группа элементов данных", //FIXME?
	"S_APPLICATIONS"=>			"Группы элементов данных", //FIXME?
	"S_APPLICATIONS_BIG"=>			"ГРУППЫ ЭЛЕМЕНТОВ ДАННЫХ", //FIXME?
	"S_CREATE_APPLICATION"=>		"Создать группу элементов данных", //FIXME?
	"S_DELETE_SELECTED_APPLICATIONS_Q"=>	"Удалить выбранные группы элементов данных?", //FIXME?
	"S_DISABLE_ITEMS_FROM_SELECTED_APPLICATIONS_Q"=>"Деактивировать элементы данных из выбранных групп?", //FIXME?
	"S_ACTIVATE_ITEMS_FROM_SELECTED_APPLICATIONS_Q"=>"Активировать элементы данных из выбранных групп?", //FIXME?
	"S_APPLICATION_UPDATED"=>		"Группа элементов данных обновлена", //FIXME?
	"S_CANNOT_UPDATE_APPLICATION"=>		"Невозможно обновить группу элементов данных", //FIXME?
	"S_APPLICATION_ADDED"=>			"Группа элементов данных добавлена", //FIXME?
	"S_CANNOT_ADD_APPLICATION"=>		"Невозможно добавить группу элементов данных", //FIXME?
	"S_APPLICATION_DELETED"=>		"Группа элементов данных удалена", //FIXME?
	"S_CANNOT_DELETE_APPLICATION"=>		"Невозможно удалить группу элементов данных", //FIXME?

	"S_HOSTS"=>				"Узлы сети",
	"S_ITEMS"=>				"Элементы данных",
	"S_ITEMS_BIG"=>				"ЭЛЕМЕНТЫ ДАННЫХ",
	"S_TRIGGERS"=>				"Триггеры",
	"S_GRAPHS"=>				"Графики",
	"S_HOST_ADDED"=>			"Узел сети добавлен",
	"S_CANNOT_ADD_HOST"=>			"Невозможно добавить узел сети",
	"S_ITEMS_ADDED"=>			"Элементы добавлены",
	"S_CANNOT_ADD_ITEMS"=>			"Невозможно добавить элементы",
	"S_HOST_UPDATED"=>			"Узел сети обновлен",
	"S_CANNOT_UPDATE_HOST"=>		"Невозможно обновить узел сети",
	"S_HOST_STATUS_UPDATED"=>		"Статус узла сети обновлен",
	"S_CANNOT_UPDATE_HOST_STATUS"=>		"Невозможно обновить статус узла сети",
	"S_HOST_DELETED"=>			"Узел сети удален",
	"S_CANNOT_DELETE_HOST"=>		"Невозможно удалить узел сети",
	"S_TEMPLATE_LINKAGE_ADDED"=>		"Связь с шаблоном добавлена",
	"S_CANNOT_ADD_TEMPLATE_LINKAGE"=>	"Невозможно добавить связь с шаблоном",
	"S_TEMPLATE_LINKAGE_UPDATED"=>		"Связь с шаблоном обновлена",
	"S_CANNOT_UPDATE_TEMPLATE_LINKAGE"=>	"Невозможно обновить связь с шаблоном",
	"S_TEMPLATE_LINKAGE_DELETED"=>		"Связь с шаблоном удалена",
	"S_CANNOT_DELETE_TEMPLATE_LINKAGE"=>	"Невозможно удалить связь с шаблоном",
	"S_CONFIGURATION_OF_HOSTS_GROUPS_AND_TEMPLATES"=>"НАСТРОЙКА УЗЛОВ СЕТИ, ГРУПП УЗЛОВ И ШАБЛОНОВ",
	"S_HOST_GROUPS_BIG"=>			"ГРУППЫ УЗЛОВ СЕТИ",
	"S_START"=>				"Start",
	"S_STOP"=>				"Stop",
	"S_NO_HOST_GROUPS_DEFINED"=>		"Группы узлов сети не определены",
	"S_NO_LINKAGES_DEFINED"=>		"Связи не определены",
	"S_NO_HOSTS_DEFINED"=>			"Узлы сети не определены",
	"S_HOSTS_BIG"=>				"УЗЛЫ СЕТИ",
	"S_HOST"=>				"Узел сети",
	"S_HOST_BIG"=>				"УЗЕЛ СЕТИ",
	"S_IP"=>				"IP",
	"S_PORT"=>				"Порт",
	"S_MONITORED"=>				"Контролируется",
	"S_NOT_MONITORED"=>			"Не контролируется",
	"S_UNREACHABLE"=>			"Недоступен",
	"S_TEMPLATE"=>				"Шаблон",
	"S_DELETED"=>				"Удален",
	"S_UNKNOWN"=>				"Неизвестно",
	"S_GROUPS"=>				"Группы",
	"S_NEW_GROUP"=>				"Новая группа",
	"S_USE_IP_ADDRESS"=>			"Использовать IP адрес",
	"S_IP_ADDRESS"=>			"IP адрес",
//	"S_USE_THE_HOST_AS_A_TEMPLATE"=>		"Use the host as a template",
//	//	"S_USE_TEMPLATES_OF_THIS_HOST"=>	"Использовать шаблоны этого узла",
	"S_LINK_WITH_TEMPLATE"=>		"Связять с шаблоном",
	"S_USE_PROFILE"=>			"Использовать профиль",
	"S_DELETE_SELECTED_HOST_Q"=>		"Удалить выбранный узел сети?",
	"S_DELETE_SELECTED_GROUP_Q"=>		"Удалить выбранную группу?",
	"S_DELETE_SELECTED_GROUPS_Q"=>		"Удалить выбранные группы?",
	"S_GROUP_NAME"=>			"Название группы",
	"S_HOST_GROUP"=>			"Группа узла сети",
	"S_HOST_GROUPS"=>			"Группы узлов сети",
	"S_UPDATE"=>				"Обновить",
	"S_AVAILABILITY"=>			"Доступность",
	"S_AVAILABLE"=>				"Доступен",
	"S_NOT_AVAILABLE"=>			"Недоступен",
//	Host profiles
	"S_HOST_PROFILE"=>			"Профиль узла сети",
	"S_DEVICE_TYPE"=>			"Тип устройства",
	"S_OS"=>				"ОС",
	"S_SERIALNO"=>				"Серийный номер",
	"S_TAG"=>				"Tag",
	"S_HARDWARE"=>				"Аппаратные средства", //FIXME?
	"S_SOFTWARE"=>				"Программное обеспечение",
	"S_CONTACT"=>				"Контактная информация",
	"S_LOCATION"=>				"Местоположение",
	"S_NOTES"=>				"Примечания",
	"S_MACADDRESS"=>			"MAC адрес",
	"S_PROFILE_ADDED"=>			"Профиль добавлен",
	"S_CANNOT_ADD_PROFILE"=>		"Невозможно добавить профиль",
	"S_PROFILE_UPDATED"=>			"Профиль обновлен",
	"S_CANNOT_UPDATE_PROFILE"=>		"Невозможно обновить профиль",
	"S_PROFILE_DELETED"=>			"Профиль удален",
	"S_CANNOT_DELETE_PROFILE"=>		"Невозможно удалить профиль",
	"S_ADD_TO_GROUP"=>			"Добавить в группу",
	"S_DELETE_FROM_GROUP"=>			"Удалить из группы",
	"S_UPDATE_IN_GROUP"=>			"Обновить в группе",
	"S_DELETE_SELECTED_HOSTS_Q"=>		"Удалить выбранные узлы сети?",
	"S_DISABLE_SELECTED_HOSTS_Q"=>		"Деактивировать выбранные узлы сети?", //FIXME?
	"S_ACTIVATE_SELECTED_HOSTS_Q"=>		"Активировать выбранные узлы сети?",
	"S_SELECT_HOST_TEMPLATE_FIRST"=>	"Сначала выберите шаблон узла сети",
	"S_CREATE_HOST"=>			"Создать узел сети",
	"S_CREATE_TEMPLATE"=>			"Создать шаблон",
	"S_TEMPLATE_LINKAGE"=>			"Связи с шаблонами",
	"S_TEMPLATE_LINKAGE_BIG"=>		"СВЯЗИ С ШАБЛОНАМИ",
	"S_NO_LINKAGES"=>			"Нет связей",
	"S_TEMPLATES"=>				"Шаблоны",
	"S_TEMPLATES_BIG"=>			"ШАБЛОНЫ",
	"S_HOSTS"=>				"Узлы сети",

//	items.php
	"S_NO_ITEMS_DEFINED"=>			"Элементы данных не определены",
	"S_HISTORY_CLEANED"=>			"История очищена",
	"S_CANNOT_CLEAN_HISTORY"=>		"Невозможно очистить историю",
	"S_CONFIGURATION_OF_ITEMS"=>		"Настройка элементов данных",
	"S_CONFIGURATION_OF_ITEMS_BIG"=>	"НАСТРОЙКА ЭЛЕМЕНТОВ ДАННЫХ",
	"S_CANNOT_UPDATE_ITEM"=>		"Невозможно обновить элемент данных",
	"S_STATUS_UPDATED"=>			"Статус обновлен",
	"S_CANNOT_UPDATE_STATUS"=>		"Невозможно обновить статус",
	"S_CANNOT_ADD_ITEM"=>			"Невозможно добавить элемент данных",
	"S_ITEM_DELETED"=>			"Элемент данных удален",
	"S_CANNOT_DELETE_ITEM"=>		"Невозможно удалить элемент данных",
	"S_ITEMS_DELETED"=>			"Элементы данных удалены",
	"S_CANNOT_DELETE_ITEMS"=>		"Невозможно удалить элементы данных",
	"S_ITEMS_ACTIVATED"=>			"Элементы данных активированы",
	"S_CANNOT_ACTIVATE_ITEMS"=>		"Невозможно активировать элементы данных",
	"S_ITEMS_DISABLED"=>			"Элементы данных деактивированы", //FIXME?
	"S_CANNOT_DISABLE_ITEMS"=>		"Невозможно деактивировать элементы данных", //FIXME?
	"S_SERVERNAME"=>			"Название сервера",
	"S_KEY"=>				"Ключ",
	"S_DESCRIPTION"=>			"Описание",
	"S_UPDATE_INTERVAL"=>			"Интервал обновления",
	"S_HISTORY"=>				"История",
	"S_TRENDS"=>				"Динамика изменений",
	"S_SHORT_NAME"=>			"Краткое название",
	"S_ZABBIX_AGENT"=>			"ZABBIX агент", //FIXME?
	"S_ZABBIX_AGENT_ACTIVE"=>		"ZABBIX агент (активный)", //FIXME?
	"S_SNMPV1_AGENT"=>			"SNMPv1 агент", //FIXME?
	"S_ZABBIX_TRAPPER"=>			"ZABBIX trapper", //FIXME
	"S_SIMPLE_CHECK"=>			"Простая проверка",
	"S_SNMPV2_AGENT"=>			"SNMPv2 агент", //FIXME?
	"S_SNMPV3_AGENT"=>			"SNMPv3 агент", //FIXME?
	"S_ZABBIX_INTERNAL"=>			"ZABBIX internal", //FIXME
	"S_ZABBIX_AGGREGATE"=>			"ZABBIX aggregate", //FIXME
	"S_ZABBIX_UNKNOWN"=>			"Неизвестно",
	"S_ACTIVE"=>				"Активен",
	"S_NOT_ACTIVE"=>			"Неактивен",
	"S_NOT_SUPPORTED"=>			"Не поддерживается",
	"S_ACTIVATE_SELECTED_ITEMS_Q"=>		"Активировать выбранные элементы данных?",
	"S_DISABLE_SELECTED_ITEMS_Q"=>		"Деактивировать выбранные элементы данных?", //FIXME?
	"S_DELETE_SELECTED_ITEMS_Q"=>		"Удалить выбранные эелементы данных?",
	"S_EMAIL"=>				"Адрес электронной почты",
	"S_SCRIPT"=>				"Сценарий",
	"S_SMS"=>				"SMS",
	"S_GSM_MODEM"=>				"GSM модем",
	"S_UNITS"=>				"Единица измерения",
	"S_MULTIPLIER"=>			"Множитель", //FIXME?
	"S_UPDATE_INTERVAL_IN_SEC"=>		"Интервал обновления (секунды)",
	"S_KEEP_HISTORY_IN_DAYS"=>		"Хранить историю (дни)",
	"S_KEEP_TRENDS_IN_DAYS"=>		"Хранить динамику изменений (дни)",
	"S_TYPE_OF_INFORMATION"=>		"Тип данных",
	"S_STORE_VALUE"=>			"Хранить значение",
	"S_SHOW_VALUE"=>			"Показывать значение",
	"S_NUMERIC_UINT64"=>			"Числовой (целое 64 бита)",
	"S_NUMERIC_FLOAT"=>			"Числовой (с плавающей точкой)",
	"S_CHARACTER"=>				"Символ",
	"S_LOG"=>				"Журнал (лог)",
	"S_TEXT"=>				"Текст",
	"S_AS_IS"=>				"Как есть",
	"S_DELTA_SPEED_PER_SECOND"=>		"Дельта (скорость в секунду)",
	"S_DELTA_SIMPLE_CHANGE"=>		"Дельта (простое изменение)",
	"S_ITEM"=>				"Элемент данных",
	"S_SNMP_COMMUNITY"=>			"SNMP community",
	"S_SNMP_OID"=>				"SNMP OID",
	"S_SNMP_PORT"=>				"SNMP порт",
	"S_ALLOWED_HOSTS"=>			"Разрешенные узлы сети",
	"S_SNMPV3_SECURITY_NAME"=>		"SNMPv3 security name", //FIXME
	"S_SNMPV3_SECURITY_LEVEL"=>		"SNMPv3 security level", //FIXME
	"S_SNMPV3_AUTH_PASSPHRASE"=>		"SNMPv3 auth passphrase", //FIXME
	"S_SNMPV3_PRIV_PASSPHRASE"=>		"SNMPv3 priv passphrase", //FIXME
	"S_CUSTOM_MULTIPLIER"=>			"Пользовательский множитель",
	"S_DO_NOT_USE"=>			"Не использовать",
	"S_USE_MULTIPLIER"=>			"Использовать множитель",
	"S_SELECT_HOST_DOT_DOT_DOT"=>		"Выберите узел сети...",
	"S_LOG_TIME_FORMAT"=>			"Формат времени в журнале (логе) ",
	"S_CREATE_ITEM"=>			"Создать элемент данных",
	"S_ADD_ITEM"=>				"Добавить элемент данных",
	"S_SHOW_DISABLED_ITEMS"=>               "Показывать деактивированные эелементы данных", //FIXME
	"S_HIDE_DISABLED_ITEMS"=>               "Не отображать деактивированные элементы данных",

//	events.php
	"S_LATEST_EVENTS"=>			"Последние события",
	"S_HISTORY_OF_EVENTS_BIG"=>		"ИСТОРИЯ СОБЫТИЙ",
	"S_NO_EVENTS_FOUND"=>			"События не найдены",

//	latest.php
	"S_LAST_CHECK"=>			"Последняя проверка",
	"S_LAST_CHECK_BIG"=>			"ПОСЛЕДНЯЯ ПРОВЕРКА",
	"S_LAST_VALUE"=>			"Последнее значение",

//	sysmap.php
	"S_LINK"=>				"Связь", //FIXME?
	"S_LABEL"=>				"Подпись",
	"S_X"=>					"X",
	"S_Y"=>					"Y",
	"S_ICON_ON"=>				"Пиктограмма (вкл)", //FIXME?
	"S_ICON_OFF"=>				"Пиктограмма (выкл)", //FIXME?
	"S_ELEMENT_1"=>				"Элемент 1",
	"S_ELEMENT_2"=>				"Элемент 2",
	"S_LINK_STATUS_INDICATOR"=>		"Индикатор статуса связи", //FIXME?
	"S_CONFIGURATION_OF_NETWORK_MAPS"=>	"НАСТРОЙКА КАРТ СЕТЕЙ",

//	sysmaps.php
	"S_MAPS_BIG"=>				"КАРТЫ СЕТИ",
	"S_NO_MAPS_DEFINED"=>			"Карты сети не определены",
	"S_CONFIGURATION_OF_NETWORK_MAPS"=>	"НАСТРОЙКА КАРТ СЕТЕЙ",
	"S_CREATE_MAP"=>			"Создать карту сети",
	"S_ICON_LABEL_LOCATION"=>		"Расположение пиктограммы состояния", //FIXME?
	"S_BOTTOM"=>				"По нижнему краю",
	"S_TOP"=>				"По верхнему краю",

//	map.php
	"S_OK_BIG"=>				"OK", //FIXME?
	"S_PROBLEMS_SMALL"=>			"проблемы", //FIXME?
	"S_ZABBIX_URL"=>			"http://www.zabbix.com",

//	maps.php
	"S_NETWORK_MAPS"=>			"Карты сетей",
	"S_NETWORK_MAPS_BIG"=>			"Карты сетей",
	"S_NO_MAPS_TO_DISPLAY"=>		"Нет карт сетей для отображения",
	"S_SELECT_MAP_TO_DISPLAY"=>		"Выберите карту сети для отображения",
	"S_SELECT_MAP_DOT_DOT_DOT"=>		"Выберите карту сети...",
	"S_BACKGROUND_IMAGE"=>			"Фоновое изображение",
	"S_ICON_LABEL_TYPE"=>			"Тип подписи у пиктограммы",
	"S_LABEL"=>				"Подпись",
	"S_LABEL_LOCATION"=>			"Расположение подписи",
	"S_ELEMENT_NAME"=>			"Название элемента",
	"S_STATUS_ONLY"=>			"Только статус",
	"S_NOTHING"=>				"Ничего",

//	media.php
	"S_MEDIA"=>				"Средство передачи",
	"S_MEDIA_BIG"=>				"СРЕДСТВО ПЕРЕДАЧИ",
	"S_MEDIA_ACTIVATED"=>			"Средство передачи активирование",
	"S_CANNOT_ACTIVATE_MEDIA"=>		"Невозможно активировать средство передачи",
	"S_MEDIA_DISABLED"=>			"Средство передачи деактивировано", //FIXME?
	"S_CANNOT_DISABLE_MEDIA"=>		"Невозможно деактивировать средство передачи", //FIXME?
	"S_MEDIA_ADDED"=>			"Средство передачи добавлено",
	"S_CANNOT_ADD_MEDIA"=>			"Невозможно добавить средство передачи",
	"S_MEDIA_UPDATED"=>			"Средство передачи обновлено",
	"S_CANNOT_UPDATE_MEDIA"=>		"Невозможно обновить средство передачи",
	"S_MEDIA_DELETED"=>			"Средство передачи удалено",
	"S_CANNOT_DELETE_MEDIA"=>		"Невозможно удалить средство передачи",
	"S_SEND_TO"=>				"Отправлять",
	"S_WHEN_ACTIVE"=>			"Когда активировано",
	"S_NO_MEDIA_DEFINED"=>			"Не определено средство передачи",
	"S_NEW_MEDIA"=>				"Новое средство передачи",
	"S_USE_IF_SEVERITY"=>			"Использовать, если важность",
	"S_DELETE_SELECTED_MEDIA_Q"=>		"Удалить выбранное средство передачи?",
	"S_CREATE_MEDIA"=>			"Создать средство передачи",
	"S_SAVE"=>				"Сохранить",
	"S_CANCEL"=>				"Отменить",

//	Menu
	"S_MENU_LATEST_VALUES"=>		"ПОСЛЕДНИЕ ЗНАЧЕНИЯ",
	"S_MENU_TRIGGERS"=>			"ТРИГГЕРЫ",
	"S_MENU_QUEUE"=>			"ОЧЕРЕДЬ",
	"S_MENU_ALARMS"=>			"СИГНАЛИЗАЦИИ",
	"S_MENU_ALERTS"=>			"ОПОВЕЩЕНИЯ",
	"S_MENU_NETWORK_MAPS"=>			"КАРТЫ СЕТЕЙ",
	"S_MENU_GRAPHS"=>			"ГРАФИКИ",
	"S_MENU_SCREENS"=>			"КОМПЛЕКСНЫЕ ОТЧЕТЫ",
	"S_MENU_IT_SERVICES"=>			"УСЛУГИ ИТ",
	"S_MENU_HOME"=>				"В НАЧАЛО",
	"S_MENU_ABOUT"=>			"О ПРОГРАММЕ",
	"S_MENU_STATUS_OF_ZABBIX"=>		"СТАТУС ZABBIX",
	"S_MENU_AVAILABILITY_REPORT"=>		"ОТЧЕТЫ О ДОСТУПНОСТИ",
	"S_MENU_CONFIG"=>			"НАСТРОЙКА",
	"S_MENU_USERS"=>			"ПОЛЬЗОВАТЕЛИ",
	"S_MENU_HOSTS"=>			"УЗЛЫ СЕТИ",
	"S_MENU_ITEMS"=>			"ЭЛЕМЕНТЫ ДАННЫХ",
	"S_MENU_AUDIT"=>			"ИСТОРИЯ ИЗМЕНЕНИЙ",

//	overview.php
	"S_SELECT_GROUP_DOT_DOT_DOT"=>		"Выберите группу ...",
	"S_OVERVIEW"=>				"Обзор",
	"S_OVERVIEW_BIG"=>			"ОБЗОР",
	"S_EXCL"=>				"!",
	"S_DATA"=>				"Данные",

//	queue.php
	"S_QUEUE_BIG"=>				"ОЧЕРЕДЬ",
	"S_QUEUE_OF_ITEMS_TO_BE_UPDATED_BIG"=>	"ОЧЕРЕДЬ С ЭЛЕМЕНТАМИ ДАННЫХ ДЛЯ ОБНОВЛЕНИЯ",
	"S_NEXT_CHECK"=>			"Следующая проверка",
	"S_THE_QUEUE_IS_EMPTY"=>		"Очередь пуста",
	"S_TOTAL"=>				"Всего",
	"S_COUNT"=>				"Количество",
	"S_5_SECONDS"=>				"5 секунд",
	"S_10_SECONDS"=>			"10 секунд",
	"S_30_SECONDS"=>			"30 секунд",
	"S_1_MINUTE"=>				"1 минута",
	"S_5_MINUTES"=>				"5 минут",
	"S_MORE_THAN_5_MINUTES"=>		"Больше 5 минут",

//	report1.php
	"S_STATUS_OF_ZABBIX"=>			"Статус ZABBIX",
	"S_STATUS_OF_ZABBIX_BIG"=>		"СТАТУС ZABBIX",
	"S_VALUE"=>				"Значение",
	"S_ZABBIX_SERVER_IS_RUNNING"=>		"ZABBIX сервер запущен",
	"S_NUMBER_OF_VALUES_STORED"=>		"Количество сохраненных значений",
	"S_VALUES_STORED"=>			"Количество сохраненных значений",
	"S_NUMBER_OF_TRENDS_STORED"=>		"Количество сохраненных динамик изменений",
	"S_TRENDS_STORED"=>			"Количество сохраненных динамик изменений",
	"S_NUMBER_OF_ALARMS"=>			"Количество сигнализация",
	"S_NUMBER_OF_ALERTS"=>			"Количество оповещений",
	"S_NUMBER_OF_TRIGGERS"=>		"Количество триггеров (активированных/деактивированных)[истина/неизвестно/ложь]", //FIXME?
	"S_NUMBER_OF_TRIGGERS_SHORT"=>		"Триггеры (а/д)[и/н/л]",
	"S_NUMBER_OF_ITEMS"=>			"Количество элементов данных (активных/неактивных/не поддерживается)[trapper]", //FIXME?
	"S_NUMBER_OF_ITEMS_SHORT"=>		"Элементы данных (а/н/нп)[t]",
	"S_NUMBER_OF_USERS"=>			"Количество пользователей",
	"S_NUMBER_OF_USERS_SHORT"=>		"Пользователи (подключены в данный момент)",
	"S_NUMBER_OF_HOSTS"=>			"Количество узлов сети (контролируется/не контролируется/шаблоны/удалено)",
	"S_NUMBER_OF_HOSTS_SHORT"=>		"Узлы сети (к/н/ш/у)",
	"S_YES"=>				"Да",
	"S_NO"=>				"Нет",
	"S_RUNNING"=>				"запущен",
	"S_NOT_RUNNING"=>			"не запущен",

//	report2.php
	"S_AVAILABILITY_REPORT"=>		"Отчет о доступности",
	"S_AVAILABILITY_REPORT_BIG"=>		"ОТЧЕТ О ДОСТУПНОСТИ",
	"S_SHOW"=>				"Показать",
	"S_TRUE"=>				"Истина",
	"S_FALSE"=>				"Ложь",

//	report3.php
	"S_IT_SERVICES_AVAILABILITY_REPORT"=>	"Отчет о доступности услуг ИТ", //FIXME?
	"S_IT_SERVICES_AVAILABILITY_REPORT_BIG"=>	"ОТЧЕТ О ДОСТУПНОСТИ УСЛУГ ИТ", //FIXME?
	"S_FROM"=>				"От",
	"S_TILL"=>				"До",
	"S_OK"=>				"Ok",
	"S_PROBLEMS"=>				"Проблемы",
	"S_PERCENTAGE"=>			"Процент", //FIXME?
	"S_SLA"=>				"SLA",
	"S_DAY"=>				"День",
	"S_MONTH"=>				"Месяц",
	"S_YEAR"=>				"Год",
	"S_DAILY"=>				"Ежедневно",
	"S_WEEKLY"=>				"Еженедельно",
	"S_MONTHLY"=>				"Ежемесячно",
	"S_YEARLY"=>				"Ежегодно",

//      report4.php
	"S_NOTIFICATIONS"=>			"Уведомления",
	"S_NOTIFICATIONS_BIG"=>			"УВЕДОМЛЕНИЯ",
	"S_IT_NOTIFICATIONS"=>			"Отчет об уведомлениях",

//	report5.php
        "S_TRIGGERS_TOP_100"=>			"100 наиболее активных триггеров",
	"S_TRIGGERS_TOP_100_BIG"=>		"100 наиболее активных триггеров",
	"S_NUMBER_OF_STATUS_CHANGES"=>		"Количество изменений статуса",
	"S_WEEK"=>				"Неделя",
	"S_LAST"=>				"За последний",
 
//	screenconf.php
	"S_SCREENS"=>				"Комплексные отчеты", //FIXME?
	"S_SCREEN"=>				"Комплексный отчет", //FIXME?
	"S_CONFIGURATION_OF_SCREENS_BIG"=>	"НАСТРОЙКА КОМПЛКСНЫХ ОТЧЕТОВ", //FIXME?
	"S_CONFIGURATION_OF_SCREENS"=>		"Настройка комплексных отчетов", //FIXME?
	"S_SCREEN_ADDED"=>			"Комплексный отчет добавлен", //FIXME?
	"S_CANNOT_ADD_SCREEN"=>			"Невозможно добавить комплексный отчет", //FIXME?
	"S_SCREEN_UPDATED"=>			"Комплексный отчет обновлен", //FIXME?
	"S_CANNOT_UPDATE_SCREEN"=>		"Невозможно обновить комплексный отчет", //FIXME?
	"S_SCREEN_DELETED"=>			"Комплексный отчет удален", //FIXME?
	"S_CANNOT_DELETE_SCREEN"=>		"Невозможно удалить комплексный отчет", //FIXME?
	"S_COLUMNS"=>				"Столбцов",
	"S_ROWS"=>				"Строк",
	"S_NO_SCREENS_DEFINED"=>		"Комплексные отчеты не определены", //FIXME?
	"S_DELETE_SCREEN_Q"=>			"Удалить комплексны отчет?", //FIXME?
	"S_CONFIGURATION_OF_SCREEN_BIG"=>	"НАСТРОЙКА КОМПЛЕКСНОГО ОТЧЕТА",
	"S_SCREEN_CELL_CONFIGURATION"=>		"Настройка элемента комплексного отчета", //FIXME?
	"S_RESOURCE"=>				"Ресурс",
	"S_SIMPLE_GRAPH"=>			"Простой график",
	"S_GRAPH_NAME"=>			"Название графика",
	"S_WIDTH"=>				"Ширина",
	"S_HEIGHT"=>				"Высота",
	"S_CREATE_SCREEN"=>			"Создать комплексный отчет", //FIXME?
	"S_EDIT"=>				"Изменить",
	"S_DIMENSION_COLS_ROWS"=>		"Размер (столбцов x строк)",

//	screenedit.php
	"S_MAP"=>				"Карта сети",
	"S_AS_PLAIN_TEXT"=>			"Как простой текст", //FIXME?
	"S_PLAIN_TEXT"=>			"Текст",
	"S_COLUMN_SPAN"=>			"Объединить столбцы", //FIXME?
	"S_ROW_SPAN"=>				"Объединить строки", //FIXME?
	"S_SHOW_LINES"=>			"Показывать строк", //FIXME?
	"S_HOSTS_INFO"=>			"Информация об узлах сети",
	"S_TRIGGERS_INFO"=>			"Информация о триггерах",
	"S_SERVER_INFO"=>			"Информация о сервере",
	"S_CLOCK"=>				"Часы",
	"S_TRIGGERS_OVERVIEW"=>			"Обзор триггеров",
	"S_DATA_OVERVIEW"=>			"Обзор данных",
        "S_HISTORY_OF_ACTIONS"=>                "История действий",
        "S_HISTORY_OF_EVENTS"=>                 "История событий",

	"S_TIME_TYPE"=>				"Время", //FIXME?
	"S_SERVER_TIME"=>			"На сервере", //FIXME?
	"S_LOCAL_TIME"=>			"Местное", //FIXME?

	"S_STYLE"=>				"Способ", //FIXME
	"S_VERTICAL"=>				"Вертикальный", //FIXME
	"S_HORISONTAL"=>			"Горизонтальный", //FIXME

	"S_HORISONTAL_ALIGN"=>			"Выравнивание по горизонтали",
	"S_LEFT"=>				"По левому краю",
	"S_CENTER"=>				"По центру",
	"S_RIGHT"=>				"Право",

	"S_VERTICAL_ALIGN"=>			"Выравнивание по вертикали",
	"S_TOP"=>				"По верхнему краю",
	"S_MIDDLE"=>				"По середине",
	"S_BOTTOM"=>				"По нижнему краю",

//	screens.php
	"S_CUSTOM_SCREENS"=>			"Пользовательские комплексные отчеты",
	"S_SCREENS_BIG"=>			"КОМПЛЕКСНЫЕ ОТЧЕТЫ",
	"S_NO_SCREENS_TO_DISPLAY"=>		"Нет комплексных отчетов для отображения",
	"S_SELECT_SCREEN_TO_DISPLAY"=>		"Выберите комплексный отчет для отображения",
	"S_SELECT_SCREEN_DOT_DOT_DOT"=>		"Выберите комплексный отчет ...",

//	services.php
	"S_IT_SERVICES"=>			"Услуги ИТ",
	"S_SERVICE_UPDATED"=>			"Услуга обновлена",
	"S_CANNOT_UPDATE_SERVICE"=>		"Невозможно обновить услугу",
	"S_SERVICE_ADDED"=>			"Услуга добавлена",
	"S_CANNOT_ADD_SERVICE"=>		"Невозможно добавить услугу",
	"S_LINK_ADDED"=>			"Связь добавлена",
	"S_CANNOT_ADD_LINK"=>			"Невозможно добавить связь",
	"S_SERVICE_DELETED"=>			"Услуга удалена",
	"S_CANNOT_DELETE_SERVICE"=>		"Невозможно удалить услугу",
	"S_LINK_DELETED"=>			"Связь удалена",
	"S_CANNOT_DELETE_LINK"=>		"Невозможно удалить связь",
	"S_STATUS_CALCULATION"=>		"Подсчет статуса",
	"S_STATUS_CALCULATION_ALGORITHM"=>	"Алгоритм подсчета статуса",
	"S_NONE"=>				"Нет",
	"S_MAX_OF_CHILDS"=>			"MAX потомков", //FIXME
	"S_MIN_OF_CHILDS"=>			"MIN потомков",
	"S_SERVICE_1"=>				"Услуга 1",
	"S_SERVICE_2"=>				"Услуга 2",
	"S_SOFT_HARD_LINK"=>			"Нежесткая/жесткая связь", //FIXME
	"S_SOFT"=>				"Нежесткая", //FIXME
	"S_HARD"=>				"Жесткая", //FIXME
	"S_DO_NOT_CALCULATE"=>			"Не подсчитывать",
	"S_MAX_BIG"=>				"MAX",
	"S_MIN_BIG"=>				"MIN",
	"S_SHOW_SLA"=>				"Показывать SLA", //FIXME
	"S_ACCEPTABLE_SLA_IN_PERCENT"=>		"Допустимый SLA (проценты)",
	"S_LINK_TO_TRIGGER_Q"=>			"Связать с триггером?",
	"S_SORT_ORDER_0_999"=>			"Порядок сортировки (0->999)",
	"S_DELETE_SERVICE_Q"=>			"Удалить сервис?", //FIXME
	"S_LINK_TO"=>				"Связь с", //FIXME
	"S_SOFT_LINK_Q"=>			"Нежесткая связь?", //FIXME
	"S_ADD_SERVER_DETAILS"=>		"Добавить подробности сервера", //FIXME
	"S_TRIGGER"=>				"Триггер",
	"S_SERVER"=>				"Сервер",
	"S_DELETE"=>				"Удалить",
	"S_DELETE_SELECTED_SERVICES"=>		"Удалить выбранные сервисы?", //FIXME
	"S_DELETE_SELECTED_LINKS"=>		"Удалить выбранные связи?",
	"S_SERVICES_DELETED"=>			"Сервисы удалены", //FIXME
	"S_CANNOT_DELETE_SERVICES"=>		"Невозможно удалить сервисы", //FIXME

//	srv_status.php
	"S_IT_SERVICES_BIG"=>			"Сервисы ИТ",
	"S_SERVICE"=>				"Сервис",
	"S_REASON"=>				"Причина",
	"S_SLA_LAST_7_DAYS"=>			"SLA (последние 7 дней)",
	"S_PLANNED_CURRENT_SLA"=>		"Запланированный/текущий SLA",
	"S_TRIGGER_BIG"=>			"ТРИГГЕР",

//	triggers.php
	"S_NO_TRIGGERS_DEFINED"=>		"Триггеры не определены",
	"S_CONFIGURATION_OF_TRIGGERS"=>		"Настройка триггеров",
	"S_CONFIGURATION_OF_TRIGGERS_BIG"=>	"НАСТРОЙКА ТРИГГЕРОВ",
	"S_DEPENDENCY_ADDED"=>			"Зависимость добавлена",
	"S_CANNOT_ADD_DEPENDENCY"=>		"Невозможно добавить зависимость",
	"S_TRIGGERS_UPDATED"=>			"Триггеры обновлены",
	"S_CANNOT_UPDATE_TRIGGERS"=>		"Невозможно обновить триггеры",
	"S_TRIGGERS_DISABLED"=>			"Триггеры деактивированы", //FIXME?
	"S_CANNOT_DISABLE_TRIGGERS"=>		"Невозможно деактивировать триггеры", //FIXME?
	"S_TRIGGERS_DELETED"=>			"Триггеры удалены",
	"S_CANNOT_DELETE_TRIGGERS"=>		"Невозможно удалить триггеры",
	"S_TRIGGER_DELETED"=>			"Триггер удален",
	"S_CANNOT_DELETE_TRIGGER"=>		"Невозможно удалить триггер",
	"S_INVALID_TRIGGER_EXPRESSION"=>	"Ошибочное выражение триггера",
	"S_TRIGGER_ADDED"=>			"Триггер добавлен",
	"S_CANNOT_ADD_TRIGGER"=>		"Невозможно добавить триггер",
	"S_SEVERITY"=>				"Важность", //FIXME
	"S_EXPRESSION"=>			"Выражение", //FIXME
	"S_DISABLED"=>				"Деактивирован", //FIXME?
	"S_ENABLED"=>				"Активирован", //FIXME
	"S_ENABLE_SELECTED_TRIGGERS_Q"=>	"Активировать выбранные триггеры?", //FIXME
	"S_DISABLE_SELECTED_TRIGGERS_Q"=>	"Деактивировать выбранные триггеры?", //FIXME?
	"S_DELETE_SELECTED_TRIGGERS_Q"=>	"Удалить выбранные триггеры?",
	"S_CHANGE"=>				"Изменить",
	"S_TRIGGER_UPDATED"=>			"Триггер обновлен",
	"S_CANNOT_UPDATE_TRIGGER"=>		"Невозможно обновить триггер",
	"S_DEPENDS_ON"=>			"Зависит от",
	"S_URL"=>				"URL",
	"S_CREATE_TRIGGER"=>			"Создать триггер",
	"S_SHOW_DISABLED_TRIGGERS"=>		"Показывать деактивированные триггеры", //FIXME
	"S_HIDE_DISABLED_TRIGGERS"=>		"Не отображать деактивированные триггеры",

//	tr_comments.php
	"S_TRIGGER_COMMENTS"=>			"Комментарии триггера",
	"S_TRIGGER_COMMENTS_BIG"=>		"КОММЕНТАРИИ ТРИГГЕРА",
	"S_COMMENT_UPDATED"=>			"Комментарий обновлен",
	"S_CANNOT_UPDATE_COMMENT"=>		"Невозможно обновить комментарий",
	"S_ADD"=>				"Добавить",

//	tr_status.php
	"S_STATUS_OF_TRIGGERS"=>		"Статус триггеров",
	"S_STATUS_OF_TRIGGERS_BIG"=>		"СТАТУС ТРИГГЕРОВ",
	"S_SHOW_ONLY_TRUE"=>			"Показать только со статусом ИСТИНА",
	"S_HIDE_ACTIONS"=>			"Не показывать действия",
	"S_SHOW_ACTIONS"=>			"Показать действия",
	"S_SHOW_ALL_TRIGGERS"=>			"Показать все триггеры",
	"S_HIDE_DETAILS"=>			"Не показывать подробности",
	"S_SHOW_DETAILS"=>			"Показать подробности",
	"S_SELECT"=>				"Выбрать",
	"S_HIDE_SELECT"=>			"Не показывать выбор",
	"S_TRIGGERS_BIG"=>			"ТРИГГЕРЫ",
	"S_NAME_BIG"=>				"NAME",
	"S_SEVERITY_BIG"=>			"ВАЖНОСТЬ",
	"S_LAST_CHANGE_BIG"=>			"ПОСЛЕДНЕЕ ИЗМЕНЕНИЕ",
	"S_LAST_CHANGE"=>			"Последнее изменение",
	"S_COMMENTS"=>				"Комментарии",
	"S_ACKNOWLEDGED"=>			"Подтвержден", //FIXME
	"S_ACK"=>				"Подтвердить", //FIXME

//	users.php
	"S_USERS"=>				"Пользователи",
	"S_USER_ADDED"=>			"Пользователь добавлен",
	"S_CANNOT_ADD_USER"=>			"Невозможно добавить пользователя",
	"S_CANNOT_ADD_USER_BOTH_PASSWORDS_MUST"=>"Невозможно добавить пользователя. Оба пароля должны совпадать.",
	"S_USER_DELETED"=>			"Пользователь удален",
	"S_CANNOT_DELETE_USER"=>		"Невозможно удалить пользователя",
	"S_PERMISSION_DELETED"=>		"Полномочие удалено",
	"S_CANNOT_DELETE_PERMISSION"=>		"Невозможно удалить полномочие",
	"S_PERMISSION_ADDED"=>			"Полномочие добавлено",
	"S_CANNOT_ADD_PERMISSION"=>		"Невозможно добавить полномочие",
	"S_USER_UPDATED"=>			"Данные пользователь обновлены",
	"S_CANNOT_UPDATE_USER"=>		"Невозможно обновить данные пользователя",
	"S_CANNOT_UPDATE_USER_BOTH_PASSWORDS"=>	"Невозможно обновить данные пользователя. Оба пароля должны совпадать.",
	"S_GROUP_ADDED"=>			"Группа добавлена",
	"S_CANNOT_ADD_GROUP"=>			"Невозможно добавить группу",
	"S_GROUP_UPDATED"=>			"Группа обновлена",
	"S_CANNOT_UPDATE_GROUP"=>		"Невозможно обновить группу",
	"S_GROUP_DELETED"=>			"Группа удалена",
	"S_CANNOT_DELETE_GROUP"=>		"Невозможно удалить группу",
	"S_CONFIGURATION_OF_USERS_AND_USER_GROUPS"=>"НАСТРОЙКА ПОЛЬЗОВАТЕЛЕЙ И ГРУПП ПОЛЬЗОВАТЕЛЕЙ",
	"S_USER_GROUPS_BIG"=>			"ГРУППЫ ПОЛЬЗОВАТЕЛЕЙ",
	"S_USERS_BIG"=>				"ПОЛЬЗОВАТЕЛИ",
	"S_USER_GROUPS"=>			"Группы пользователей",
	"S_MEMBERS"=>				"Члены группы",
	"S_TEMPLATES"=>				"Шаблоны",
	"S_HOSTS_TEMPLATES_LINKAGE"=>		"Связи Узлы сети<->Шаблоны",
	"S_CONFIGURATION_OF_TEMPLATES_LINKAGE"=>"НАСТРОЙКА СВЯЗЕЙ ШАБЛОНОВ",
	"S_LINKED_TEMPLATES_BIG"=>		"СВЯЗАННЫЕ ШАБЛОНЫ",
	"S_NO_USER_GROUPS_DEFINED"=>		"Группы пользователей не определены",
	"S_ALIAS"=>				"Псевдоним",
	"S_NAME"=>				"Имя",
	"S_SURNAME"=>				"Фамилия",
	"S_IS_ONLINE_Q"=>			"В системе?",
	"S_NO_USERS_DEFINED"=>			"Пользователи не определены",
	"S_PERMISSION"=>			"Полномочие",
	"S_RIGHT"=>				"Право",
	"S_RESOURCE_NAME"=>			"Название ресурса",
	"S_READ_ONLY"=>				"Только чтение",
	"S_READ_WRITE"=>			"Чтение-запись",
	"S_HIDE"=>				"Скрывать",
	"S_PASSWORD"=>				"Пароль",
	"S_PASSWORD_ONCE_AGAIN"=>		"Пароль (подтверждение)",
	"S_URL_AFTER_LOGIN"=>			"URL (после входа в систему)",
	"S_AUTO_LOGOUT_IN_SEC"=>		"Автоматический выход из систему (секунды =>0 - отключено)",
	"S_SCREEN_REFRESH"=>                    "Обновлять экран (секунды)",
	"S_CREATE_USER"=>			"Создать пользователя",
	"S_CREATE_GROUP"=>			"Создать группу",

//	audit.php
	"S_AUDIT_LOG"=>				"Журнал истории изменении",
	"S_AUDIT_LOG_BIG"=>			"ЖУРНАЛ ИСТОРИИ ИЗМЕНЕНИЙ",
	"S_ACTION"=>				"Действие",
	"S_DETAILS"=>				"Подробности",
	"S_UNKNOWN_ACTION"=>			"Неизвестное действие",
	"S_ADDED"=>				"Добавлено",
	"S_UPDATED"=>				"Обновлено",
	"S_LOGGED_IN"=>				"Вошел в систему",
	"S_LOGGED_OUT"=>			"Вышел из системы",
	"S_MEDIA_TYPE"=>			"Тип средства передачи",
	"S_GRAPH_ELEMENT"=>			"Элемент графика",
	"S_UNKNOWN_RESOURCE"=>			"Неизвестный ресурс",

//	profile.php
	"S_USER_PROFILE_BIG"=>			"ПРОФИЛЬ ПОЛЬЗОВАТЕЛЯ",
	"S_USER_PROFILE"=>			"Профиль пользователя",
	"S_LANGUAGE"=>				"Язык",
	"S_ENGLISH_GB"=>			"English (GB)",
	"S_BRAZILIAN_PT"=>			"Brazilian (PT)",
	"S_FRENCH_FR"=>				"French (FR)",
	"S_GERMAN_DE"=>				"German (DE)",
	"S_ITALIAN_IT"=>			"Italian (IT)",
	"S_LATVIAN_LV"=>			"Latvian (LV)",
	"S_RUSSIAN_RU"=>			"Russian (RU)",
	"S_SPANISH_SP"=>			"Spanish (SP)",
	"S_SWEDISH_SE"=>			"Swedish (SE)",
	"S_JAPANESE_JP"=>			"Japanese (JP)",
	"S_CHINESE_CN"=>			"Chinese (CN)",
	"S_DUTCH_NL"=>				"Dutch (NL)",

//	index.php
	"S_ZABBIX_BIG"=>			"ZABBIX",

//	hostprofiles.php
	"S_HOST_PROFILES"=>			"Профили узлов сети",
	"S_HOST_PROFILES_BIG"=>			"ПРОФИЛИ УЗЛОВ СЕТИ",

//	bulkloader.php
	"S_MENU_BULKLOADER"=>			"Множественная загрузка", //FIXME
	"S_BULKLOADER_MAIN"=>			"Множественная загрузка: Основная страница", //FIXME
	"S_BULKLOADER_HOSTS"=>			"Множественная загрузка: Узлы сети", //FIXME
	"S_BULKLOADER_ITEMS"=>			"Множественная загрузка: Элементы данных", //FIXME
	"S_BULKLOADER_USERS"=>			"Множественная загрузка: Пользователи", //FIXME
	"S_BULKLOADER_TRIGGERS"=>		"Множественная загрузка: Триггеры", //FIXME
	"S_BULKLOADER_ACTIONS"=>		"Множественная загрузка: Действия", //FIXME
	"S_BULKLOADER_ITSERVICES"=>		"Множественная загрузка: Услуги IT", //FIXME

	"S_BULKLOADER_IMPORT_HOSTS"=>		"Импортировать Узлы сети", //FIXME
	"S_BULKLOADER_IMPORT_ITEMS"=>		"Импортировать Элементы данных", //FIXME
	"S_BULKLOADER_IMPORT_USERS"=>		"Импортировать Пользоватлей", //FIXME
	"S_BULKLOADER_IMPORT_TRIGGERS"=>	"Импортировать Триггеры", //FIXME
	"S_BULKLOADER_IMPORT_ACTIONS"=>		"Импортировать Действия", //FIXME
	"S_BULKLOADER_IMPORT_ITSERVICES"=>	"Импортировать Услуги IT", //FIXME

//	popup.php
	"S_EMPTY"=>				"Пусто",
	"S_STANDARD_ITEMS_BIG"=>		"СТАНДАРТНЫЕ ЭЛЕМЕНТЫ ДАННЫХ",  //FIXME?
	"S_NO_ITEMS"=>				"Элементы данных не определены", //FIXME?

//	Menu

	"S_HELP"=>				"Помощь",
	"S_PROFILE"=>				"Профиль",
	"S_MONITORING"=>			"Мониторинг",  //FIXME?
	"S_INVENTORY"=>				"Инвентаризация",
	"S_QUEUE"=>				"Очередь",
	"S_EVENTS"=>				"События",
	"S_MAPS"=>				"Карты сети",
	"S_REPORTS"=>				"Отчеты",
	"S_GENERAL"=>				"Общие параметры",
	"S_AUDIT"=>				"Аудит",
	"S_LOGIN"=>				"Войти в систему", //FIXME
	"S_LOGOUT"=>				"Выйти из системы", //FIXME
	"S_LATEST_DATA"=>			"ПОСЛЕДНИЕ ДАННЫЕ",

//	Errors
	"S_INCORRECT_DESCRIPTION"=>		"Неверное описание"
	);
?>
