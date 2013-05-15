DROP TABLE IF EXISTS `%PREFIX%_stat_referer`;
CREATE TABLE `%PREFIX%_stat_referer` (
  `IDReferer` bigint(20) unsigned NOT NULL auto_increment,
  `IDDomain` bigint(20) unsigned NOT NULL default '0',
  `IDPartner` int(10) unsigned NOT NULL default '0',
  `IDSearch` int(10) unsigned NOT NULL default '0',
  `referer` varchar(255) NOT NULL default '',
  `referer_domain` varchar(128) NOT NULL default '',
  `tstc` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`IDReferer`),
  KEY `IDDomain` (`IDDomain`),
  KEY `IDPartner` (`IDPartner`),
  KEY `referer_domain` (`referer_domain`),
  KEY `tstc` (`tstc`),
  KEY `IDSearch` (`IDSearch`)
) ENGINE=MyISAM AUTO_INCREMENT=671 DEFAULT CHARSET=utf8 COMMENT='Статистика - рефереры';
