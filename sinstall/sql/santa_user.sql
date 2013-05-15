DROP TABLE IF EXISTS `%PREFIX%_user`;
CREATE TABLE `%PREFIX%_user` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `login` varchar(255) default NULL,
  `password` varchar(255) default NULL,
  `email` varchar(255) default NULL,
  `name` varchar(255) default NULL,
  `verified` int(1) NOT NULL default '0',
  `enabled` int(1) NOT NULL default '1',
  `date` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `login` (`login`),
  KEY `password` (`password`),
  KEY `email` (`email`),
  KEY `verified` (`verified`),
  KEY `date` (`date`),
  KEY `enabled` (`enabled`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица пользователей';
