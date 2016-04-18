ALTER TABLE users MODIFY (
	userid DEFAULT NULL,
	lang DEFAULT 'en_GB',
	theme DEFAULT 'default'
);
UPDATE users SET lang = 'zh_CN' WHERE lang = 'cn_zh';
UPDATE users SET lang = 'es_ES' WHERE lang = 'sp_sp';
UPDATE users SET lang = 'en_GB' WHERE lang = 'en_gb';
UPDATE users SET lang = 'cs_CZ' WHERE lang = 'cs_cz';
UPDATE users SET lang = 'nl_NL' WHERE lang = 'nl_nl';
UPDATE users SET lang = 'fr_FR' WHERE lang = 'fr_fr';
UPDATE users SET lang = 'de_DE' WHERE lang = 'de_de';
UPDATE users SET lang = 'hu_HU' WHERE lang = 'hu_hu';
UPDATE users SET lang = 'ko_KR' WHERE lang = 'ko_kr';
UPDATE users SET lang = 'ja_JP' WHERE lang = 'ja_jp';
UPDATE users SET lang = 'lv_LV' WHERE lang = 'lv_lv';
UPDATE users SET lang = 'pl_PL' WHERE lang = 'pl_pl';
UPDATE users SET lang = 'pt_BR' WHERE lang = 'pt_br';
UPDATE users SET lang = 'ru_RU' WHERE lang = 'ru_ru';
UPDATE users SET lang = 'sv_SE' WHERE lang = 'sv_se';
UPDATE users SET lang = 'uk_UA' WHERE lang = 'ua_ua';

UPDATE users SET theme = 'darkblue' WHERE theme = 'css_bb.css';
UPDATE users SET theme = 'originalblue' WHERE theme = 'css_ob.css';
UPDATE users SET theme = 'darkorange' WHERE theme = 'css_od.css';
UPDATE users SET theme = 'default' WHERE theme = 'default.css';
