DROP TABLE IF EXISTS `%PREFIX%_user_fields`;
CREATE TABLE `%PREFIX%_user_fields` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `id_field` varchar(255) default NULL,
  `id_modul` varchar(255) default NULL,
  `caption` varchar(255) default NULL,
  `only_admin` int(1) NOT NULL default '1',
  `type_field` varchar(255) default NULL,
  `required` tinyint(1) unsigned  NOT NULL default '0',
  `params` text  default NULL,
  PRIMARY KEY  (`id`),
  KEY `id_field` (`id_field`),
  KEY `id_modul` (`id_modul`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Свойства пользователей';
