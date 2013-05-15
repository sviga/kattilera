DROP TABLE IF EXISTS `%PREFIX%_user_fields_value`;
CREATE TABLE `%PREFIX%_user_fields_value` (
  `user` int(10) unsigned NOT NULL,
  `field` int(10) unsigned NOT NULL,
  `value` text,
  `addon` text,
  KEY `key_user` (`user`),
  UNIQUE INDEX `key_user_field` (`user`,`field`),
  KEY `field` (`field`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Значения свойств пользователей';
