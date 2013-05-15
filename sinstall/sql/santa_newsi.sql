DROP TABLE IF EXISTS `%PREFIX%_newsi`;
CREATE TABLE `%PREFIX%_newsi` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `module_id` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `available` tinyint(1) unsigned NOT NULL,
  `lenta` tinyint(1) unsigned NOT NULL,
  `delivery` tinyint(1) unsigned NOT NULL,
  `rss` tinyint(1) unsigned NOT NULL,
  `header` varchar(255) NOT NULL,
  `description_short` text NOT NULL,
  `description_full` text NOT NULL,
  `author` varchar(255) NOT NULL,
  `source_name` varchar(255) default NULL,
  `source_url` varchar(255) default NULL,
  `image` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `module_id` (`module_id`),
  KEY `date` (`date`),
  KEY `time` (`time`),
  KEY `available` (`available`),
  KEY `lenta` (`lenta`),
  KEY `delivery` (`delivery`),
  KEY `rss` (`rss`),
  KEY `header` (`header`),
  KEY `author` (`author`),
  KEY `source_name` (`source_name`),
  KEY `source_url` (`source_url`),
  KEY `image` (`image`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Новости';
INSERT INTO `%PREFIX%_newsi` VALUES ('4','newsi1','2002-09-19','08:52:11','1','1','1','1','Скидки на телевизиры LG','С 1 октября цены на некоторые модели телевизоров LG будут снижены.','<p>С 1 октября цены на некоторые модели телевизоров LG будут снижены. Скидк в размере 15% буду предоставлены на телевизоры<span class=\"LinkShowName\">: </span></p>\r\n<ul>\r\n<li><span class=\"LinkShowName\">LG 32LG5000,</span><span class=\"LinkShowName\"> </span></li>\r\n<li><span class=\"LinkShowName\">LG 32LH2000,</span><span class=\"LinkShowName\"> </span></li>\r\n<li><span class=\"LinkShowName\">LG 22LH2000</span></li>\r\n</ul>','','','','');
INSERT INTO `%PREFIX%_newsi` VALUES ('5','newsi1','2004-03-15','02:58:43','1','1','1','1','Конкурс \"Теле-Мисс\"','Победительница конкурса \"Теле-Мисс\" получила в подарок телевизор.','<p><img style=\"float: left; margin-bottom: 10px;  margin-right: 10px;\" src=\"/content/images/022b-267x400.jpg\" alt=\"\" width=\"133\" height=\"201\" />С 1 февраля по 1 марта проводился конкурс \"Теле-Мисс\", в котором приняли участие 350 девушек.</p>\r\n<p>\"Теле-Мисс\" стала 20-летняя Софья Пергаева - студентка Санкт-Петербургского Институт  						экономики и финансов.</p>\r\n<p>Победительница конкурса стала обладательницей короны и получила в подарок телевизор <span class=\"LinkShowName\">Hantarex 32\" TV G-W Mirror</span>.</p>\r\n<p>&nbsp;</p>','','','','');
INSERT INTO `%PREFIX%_newsi` VALUES ('6','newsi1','2008-05-20','03:20:29','1','1','1','1','Акция \"Уже лето\"!','С 1 июня до 31 августа Вы можете приобрести популярные модели телевизионной техники с магазинах \"TelePult\" по сниженным ценам.','<p>С 1 июня до 31 августа Вы можете приобрести популярные модели телевизионной техники с магазинах \"TelePult\" по сниженным ценам.</p>\r\n<p>У вас также есть возможность заказывать продукцию через интернет на сайте нашей компании <a href=\"/catalog.html\">\"TelePult\"</a></p>','','','','');
