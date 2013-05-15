DROP TABLE IF EXISTS `%PREFIX%_stat_word`;
CREATE TABLE `%PREFIX%_stat_word` (
  `IDWord` bigint(20) unsigned NOT NULL auto_increment,
  `IDSearch` int(10) unsigned NOT NULL default '0',
  `IDReferer` bigint(20) unsigned NOT NULL default '0',
  `IDPartner` bigint(20) unsigned NOT NULL default '0',
  `word` varchar(255) NOT NULL default '',
  `tstc` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`IDWord`),
  KEY `tstc` (`tstc`),
  KEY `IDSearch` (`IDSearch`),
  KEY `IDReferer` (`IDReferer`),
  KEY `IDPartner` (`IDPartner`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Статистика по словам';
