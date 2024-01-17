-- Adminer 4.8.1 MySQL 8.0.30 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `api`;
CREATE TABLE `api` (
  `id` int NOT NULL AUTO_INCREMENT,
  `apikey` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `apikey` (`apikey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `api` (`id`, `apikey`) VALUES
(1,	'37b51d194a7513e45b56f6524f2d51f2');

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_id_parent_id` (`category_id`,`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `category_description`;
CREATE TABLE `category_description` (
  `category_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `language_id` int NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'thematic',
  `post` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  KEY `category_id` (`category_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `category_description_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`),
  CONSTRAINT `category_description_ibfk_4` FOREIGN KEY (`language_id`) REFERENCES `language` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `file_id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `url` varchar(255) NOT NULL,
  `downloads` int NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`file_id`),
  UNIQUE KEY `url_item_id` (`url`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `files_description`;
CREATE TABLE `files_description` (
  `file_id` int NOT NULL,
  `language_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  KEY `language_id` (`language_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `files_description_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`),
  CONSTRAINT `files_description_ibfk_2` FOREIGN KEY (`language_id`) REFERENCES `language` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `item_to_category`;
CREATE TABLE `item_to_category` (
  `category_id` int NOT NULL,
  `item_id` int NOT NULL,
  `type` varchar(255) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  UNIQUE KEY `category_id_id` (`category_id`,`item_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `item_to_category_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  CONSTRAINT `item_to_category_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `author` varchar(255) NOT NULL COMMENT 'автор, если есть брать из описания',
  `screens` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'скрины',
  `recipes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'рецепты',
  `date` int NOT NULL COMMENT 'дата поста',
  `views` int NOT NULL DEFAULT '0' COMMENT 'количество просмотров у поста',
  `likes` int NOT NULL DEFAULT '0' COMMENT 'количество лайков у поста',
  `versions` text NOT NULL COMMENT 'все версии подробно',
  `source_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'источник - ссылка',
  `source_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'источник - имя',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'mods' COMMENT 'тип записи',
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'ключ для seeds',
  `downloads` int DEFAULT '0' COMMENT 'общее количество загрузок',
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `items_description`;
CREATE TABLE `items_description` (
  `item_id` int NOT NULL,
  `language_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  KEY `language_id` (`language_id`),
  KEY `id` (`item_id`),
  CONSTRAINT `items_description_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  CONSTRAINT `items_description_ibfk_2` FOREIGN KEY (`language_id`) REFERENCES `language` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `language`;
CREATE TABLE `language` (
  `language_id` int NOT NULL AUTO_INCREMENT COMMENT 'id языка',
  `name` varchar(255) NOT NULL COMMENT 'название',
  `code` varchar(255) NOT NULL COMMENT 'код языка',
  `active` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`language_id`),
  UNIQUE KEY `name_code` (`name`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `language` (`language_id`, `name`, `code`, `active`) VALUES
(1,	'Русский',	'ru',	1),
(2,	'Португальский',	'pt',	1),
(3,	'Испанский',	'es',	1),
(4,	'Турецкий',	'tr',	1),
(5,	'Индонезийский',	'id',	1),
(6,	'Английский',	'en',	1),
(7,	'Вьетнамский',	'vi',	1),
(8,	'Итальянский',	'it',	1),
(9,	'Польский',	'pl',	1),
(10,	'Французский',	'fr',	1),
(11,	'Немецкий',	'de',	1),
(12,	'Румынский',	'ro',	1),
(13,	'Украинский',	'uk',	1),
(14,	'Чешский',	'cs',	1),
(15,	'Венгерский',	'hu',	1),
(16,	'Малайский',	'ms',	1),
(17,	'Греческий',	'el',	1),
(18,	'Болгарский',	'bg',	1),
(19,	'Словацкий',	'sk',	1),
(20,	'Литовский',	'lt',	1),
(21,	'Нидерландский',	'nl',	1),
(22,	'Хорватский',	'hr',	1),
(23,	'Сербский',	'sr',	1);

DROP TABLE IF EXISTS `p_settings`;
CREATE TABLE `p_settings` (
  `set_name` varchar(255) NOT NULL,
  `set_value` varchar(255) NOT NULL,
  PRIMARY KEY (`set_name`),
  UNIQUE KEY `set_name_set_value` (`set_name`,`set_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `p_settings` (`set_name`, `set_value`) VALUES
('page',	'1');

DROP TABLE IF EXISTS `parser_data`;
CREATE TABLE `parser_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `link` varchar(255) NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `category` varchar(255) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `link` (`link`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `proxy`;
CREATE TABLE `proxy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proxy` varchar(255) NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `uptime` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `proxy` (`proxy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


-- 2024-01-17 10:45:00
