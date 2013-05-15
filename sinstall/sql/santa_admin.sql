DROP TABLE IF EXISTS `%PREFIX%_admin`;
CREATE TABLE `%PREFIX%_admin` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `login` varchar(50) NOT NULL,
  `pass` varchar(50) NOT NULL,
  `full_name` varchar(150) default NULL,
  `lang` varchar(2) default NULL,
  `code_page` varchar(30) default NULL,
  `enabled` int(1) NOT NULL default '1',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='Таблица администраторов';
