DROP TABLE IF EXISTS `%PREFIX%_all_lang`;
CREATE TABLE `%PREFIX%_all_lang` (
  `lang` varchar(2) NOT NULL,
  `element` varchar(100) NOT NULL,
  `podelemen` tinyint(1) default NULL,
  `text` varchar(255) NOT NULL,
  KEY `lang` (`lang`),
  KEY `element` (`element`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица языковых меток';