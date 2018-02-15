-- Generation Time: Feb 02, 2018 at 03:46 PM

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ultiorganizer`
--

-- --------------------------------------------------------

--
-- Table structure for table `uo_accreditationlog`
--

SET NAMES 'utf8';

CREATE TABLE `uo_accreditationlog` (
  `player` int(10) DEFAULT NULL,
  `team` int(10) DEFAULT NULL,
  `userid` varchar(50) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `value` tinyint(1) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `game` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_club`
--

CREATE TABLE `uo_club` (
  `club_id` int(10) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `contacts` text,
  `city` varchar(100) DEFAULT NULL,
  `country` int(10) DEFAULT NULL,
  `story` text,
  `achievements` text,
  `image` int(10) DEFAULT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT '1',
  `profile_image` varchar(20) DEFAULT NULL,
  `founded` int(4) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_comment`
--

CREATE TABLE `uo_comment` (
  `type` tinyint(3) NOT NULL,
  `id` varchar(10) NOT NULL,
  `comment` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_country`
--

CREATE TABLE `uo_country` (
  `country_id` int(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `abbreviation` char(3) DEFAULT NULL,
  `flagfile` varchar(50) DEFAULT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `uo_country`
--

INSERT INTO `uo_country` (`country_id`, `name`, `abbreviation`, `flagfile`, `valid`) VALUES
(1000, 'Afghanistan', 'AFG', 'Afghanistan.png', 1),
(1001, 'Netherlands Antilles', 'AHO', 'Netherlands_Antilles.png', 1),
(1002, 'Albania', 'ALB', 'Albania.png', 1),
(1003, 'Algeria', 'ALG', 'Algeria.png', 1),
(1004, 'Andorra', 'AND', 'Andorra.png', 1),
(1005, 'Angola', 'ANG', 'Angola.png', 1),
(1006, 'Antigua and Barbuda', 'ANT', 'Antigua_and_Barbuda.png', 1),
(1007, 'Argentina', 'ARG', 'Argentina.png', 1),
(1008, 'Armenia', 'ARM', 'Armenia.png', 1),
(1009, 'Aruba', 'ARU', 'Aruba.png', 1),
(1010, 'American Samoa', 'ASA', 'American_Samoa.png', 1),
(1011, 'Australia', 'AUS', 'Australia.png', 1),
(1012, 'Austria', 'AUT', 'Austria.png', 1),
(1013, 'Azerbaijan', 'AZE', 'Azerbaijan.png', 1),
(1014, 'Bahamas', 'BAH', 'Bahamas.png', 1),
(1015, 'Bangladesh', 'BAN', 'Bangladesh.png', 1),
(1016, 'Barbados', 'BAR', 'Barbados.png', 1),
(1017, 'Burundi', 'BDI', 'Burundi.png', 1),
(1018, 'Belgium', 'BEL', 'Belgium.png', 1),
(1019, 'Benin', 'BEN', 'Benin.png', 1),
(1020, 'Bermuda', 'BER', 'Bermuda.png', 1),
(1021, 'Bhutan', 'BHU', 'Bhutan.png', 1),
(1022, 'Bosnia and Herzegovina', 'BIH', NULL, 1),
(1023, 'Belize', 'BIZ', 'Belize.png', 1),
(1024, 'Belarus', 'BLR', 'Belarus.png', 1),
(1025, 'Bolivia', 'BOL', 'Bolivia.png', 1),
(1026, 'Botswana', 'BOT', 'Botswana.png', 1),
(1027, 'Brazil', 'BRA', 'Brazil.png', 1),
(1028, 'Bahrain', 'BRN', 'Bahrain.png', 1),
(1029, 'Brunei', 'BRU', 'Brunei.png', 1),
(1030, 'Bulgaria', 'BUL', 'Bulgaria.png', 1),
(1031, 'Burkina Faso', 'BUR', 'Burkina_Faso.png', 1),
(1032, 'Central African Republic', 'CAF', 'Central_African_Republic.png', 1),
(1033, 'Cambodia', 'CAM', 'Cambodia.png', 1),
(1034, 'Canada', 'CAN', 'Canada.png', 1),
(1035, 'Cayman Islands', 'CAY', 'Cayman_Islands.png', 1),
(1036, 'Congo', 'CGO', NULL, 1),
(1037, 'Chad', 'CHA', 'Chad.png', 1),
(1038, 'Chile', 'CHI', 'Chile.png', 1),
(1039, 'China', 'CHN', 'China.png', 1),
(1040, 'Cóte d\'Ivoire', 'CIV', NULL, 1),
(1041, 'Cameroon', 'CMR', 'Cameroon.png', 1),
(1042, 'DR Congo', 'COD', NULL, 1),
(1043, 'Cook Islands', 'COK', 'Cook_Islands.png', 1),
(1044, 'Colombia', 'COL', 'Colombia.png', 1),
(1045, 'Comoros', 'COM', 'Comoros.png', 1),
(1046, 'Cape Verde', 'CPV', 'Cape_Verde.png', 1),
(1047, 'Costa Rica', 'CRC', 'Costa_Rica.png', 1),
(1048, 'Croatia', 'CRO', 'Croatia.png', 1),
(1049, 'Cuba', 'CUB', 'Cuba.png', 1),
(1050, 'Cyprus', 'CYP', 'Cyprus.png', 1),
(1051, 'Czech Republic', 'CZE', 'Czech_Republic.png', 1),
(1052, 'Denmark', 'DEN', 'Denmark.png', 1),
(1053, 'Djibouti', 'DJI', 'Djibouti.png', 1),
(1054, 'Dominica', 'DMA', 'Dominica.png', 1),
(1055, 'Dominican Republic', 'DOM', 'Dominican_Republic.png', 1),
(1056, 'Ecuador', 'ECU', 'Ecuador.png', 1),
(1057, 'Egypt', 'EGY', 'Egypt.png', 1),
(1058, 'Eritrea', 'ERI', 'Eritrea.png', 1),
(1059, 'El Salvador', 'ESA', 'El_Salvador.png', 1),
(1060, 'Spain', 'ESP', 'Spain.png', 1),
(1061, 'Estonia', 'EST', 'Estonia.png', 1),
(1062, 'Ethiopia', 'ETH', 'Ethiopia.png', 1),
(1063, 'Fiji', 'FIJ', 'Fiji.png', 1),
(1064, 'Finland', 'FIN', 'Finland.png', 1),
(1065, 'France', 'FRA', 'France.png', 1),
(1066, 'Micronesia', 'FSM', 'Micronesia.png', 1),
(1067, 'Gabon', 'GAB', 'Gabon.png', 1),
(1068, 'Gambia', 'GAM', 'Gambia.png', 1),
(1069, 'Great Britain', 'GBR', 'United_Kingdom.png', 1),
(1070, 'Guinea-Bissau', 'GBS', 'Guinea_Bissau.png', 1),
(1071, 'Georgia', 'GEO', 'Georgia.png', 1),
(1072, 'Equatorial Guinea', 'GEQ', 'Equatorial_Guinea.png', 1),
(1073, 'Germany', 'GER', 'Germany.png', 1),
(1074, 'Ghana', 'GHA', 'Ghana.png', 1),
(1075, 'Greece', 'GRE', 'Greece.png', 1),
(1076, 'Grenada', 'GRN', 'Grenada.png', 1),
(1077, 'Guatemala', 'GUA', 'Guatemala.png', 1),
(1078, 'Guinea', 'GUI', 'Guinea.png', 1),
(1079, 'Guam', 'GUM', 'Guam.png', 1),
(1080, 'Guyana', 'GUY', 'Guyana.png', 1),
(1081, 'Haiti', 'HAI', 'Haiti.png', 1),
(1082, 'Hong Kong', 'HKG', 'Hong_Kong.png', 1),
(1083, 'Honduras', 'HON', 'Honduras.png', 1),
(1084, 'Hungary', 'HUN', 'Hungary.png', 1),
(1085, 'Indonesia', 'INA', 'Indonesia.png', 1),
(1086, 'India', 'IND', 'India.png', 1),
(1087, 'Iran', 'IRI', 'Iran.png', 1),
(1088, 'Ireland', 'IRL', 'Ireland.png', 1),
(1089, 'Iraq', 'IRQ', 'Iraq.png', 1),
(1090, 'Iceland', 'ISL', 'Iceland.png', 1),
(1091, 'Israel', 'ISR', 'Israel.png', 1),
(1092, 'Virgin Islands', 'ISV', NULL, 1),
(1093, 'Italy', 'ITA', 'Italy.png', 1),
(1094, 'British Virgin Islands', 'IVB', NULL, 1),
(1095, 'Jamaica', 'JAM', 'Jamaica.png', 1),
(1096, 'Jordan', 'JOR', 'Jordan.png', 1),
(1097, 'Japan', 'JPN', 'Japan.png', 1),
(1098, 'Kazakhstan', 'KAZ', 'Kazakhstan.png', 1),
(1099, 'Kenya', 'KEN', 'Kenya.png', 1),
(1100, 'Kyrgyzstan', 'KGZ', 'Kyrgyzstan.png', 1),
(1101, 'Kiribati', 'KIR', 'Kiribati.png', 1),
(1102, 'South Korea', 'KOR', 'South_Korea.png', 1),
(1103, 'Saudi Arabia', 'KSA', 'Saudi_Arabia.png', 1),
(1104, 'Kuwait', 'KUW', 'Kuwait.png', 1),
(1105, 'Laos', 'LAO', 'Laos.png', 1),
(1106, 'Latvia', 'LAT', 'Latvia.png', 1),
(1107, 'Libya', 'LBA', 'Libya.png', 1),
(1108, 'Liberia', 'LBR', 'Liberia.png', 1),
(1109, 'Saint Lucia', 'LCA', 'Saint_Lucia.png', 1),
(1110, 'Lesotho', 'LES', 'Lesotho.png', 1),
(1111, 'Lebanon', 'LIB', 'Lebanon.png', 1),
(1112, 'Liechtenstein', 'LIE', 'Liechtenstein.png', 1),
(1113, 'Lithuania', 'LTU', 'Lithuania.png', 1),
(1114, 'Luxembourg', 'LUX', 'Luxembourg.png', 1),
(1115, 'Madagascar', 'MAD', 'Madagascar.png', 1),
(1116, 'Morocco', 'MAR', 'Morocco.png', 1),
(1117, 'Malaysia', 'MAS', 'Malaysia.png', 1),
(1118, 'Malawi', 'MAW', 'Malawi.png', 1),
(1119, 'Moldova', 'MDA', 'Moldova.png', 1),
(1120, 'Maldives', 'MDV', 'Maldives.png', 1),
(1121, 'Mexico', 'MEX', 'Mexico.png', 1),
(1122, 'Mongolia', 'MGL', 'Mongolia.png', 1),
(1123, 'Marshall Islands', 'MHL', 'Marshall_Islands.png', 1),
(1124, 'Macedonia', 'MKD', 'Macedonia.png', 1),
(1125, 'Mali', 'MLI', 'Mali.png', 1),
(1126, 'Malta', 'MLT', 'Malta.png', 1),
(1127, 'Montenegro', 'MNE', NULL, 1),
(1128, 'Monaco', 'MON', 'Monaco.png', 1),
(1129, 'Mozambique', 'MOZ', 'Mozambique.png', 1),
(1130, 'Mauritius', 'MRI', 'Mauritius.png', 1),
(1131, 'Mauritania', 'MTN', 'Mauritania.png', 1),
(1132, 'Myanmar', 'MYA', 'Myanmar.png', 1),
(1133, 'Namibia', 'NAM', 'Namibia.png', 1),
(1134, 'Nicaragua', 'NCA', 'Nicaragua.png', 1),
(1135, 'Netherlands', 'NED', 'Netherlands.png', 1),
(1136, 'Nepal', 'NEP', 'Nepal.png', 1),
(1137, 'Nigeria', 'NGR', 'Nigeria.png', 1),
(1138, 'Niger', 'NIG', 'Niger.png', 1),
(1139, 'Norway', 'NOR', 'Norway.png', 1),
(1140, 'Nauru', 'NRU', 'Nauru.png', 1),
(1141, 'New Zealand', 'NZL', 'New_Zealand.png', 1),
(1142, 'Oman', 'OMA', 'Oman.png', 1),
(1143, 'Pakistan', 'PAK', 'Pakistan.png', 1),
(1144, 'Panama', 'PAN', 'Panama.png', 1),
(1145, 'Paraguay', 'PAR', 'Paraguay.png', 1),
(1146, 'Peru', 'PER', 'Peru.png', 1),
(1147, 'Philippines', 'PHI', 'Philippines.png', 1),
(1148, 'Palestine', 'PLE', NULL, 1),
(1149, 'Palau', 'PLW', 'Palau.png', 1),
(1150, 'Papua New Guinea', 'PNG', 'Papua_New_Guinea.png', 1),
(1151, 'Poland', 'POL', 'Poland.png', 1),
(1152, 'Portugal', 'POR', 'Portugal.png', 1),
(1153, 'North Korea', 'PRK', 'North_Korea.png', 1),
(1154, 'Puerto Rico', 'PUR', 'Puerto_Rico.png', 1),
(1155, 'Qatar', 'QAT', 'Qatar.png', 1),
(1156, 'Romania', 'ROU', 'Romania.png', 1),
(1157, 'South Africa', 'RSA', 'South_Africa.png', 1),
(1158, 'Russia', 'RUS', 'Russian_Federation.png', 1),
(1159, 'Rwanda', 'RWA', 'Rwanda.png', 1),
(1160, 'Samoa', 'SAM', 'Samoa.png', 1),
(1161, 'Senegal', 'SEN', 'Senegal.png', 1),
(1162, 'Seychelles', 'SEY', NULL, 1),
(1163, 'Singapore', 'SIN', 'Singapore.png', 1),
(1164, 'Saint Kitts and Nevis', 'SKN', 'Saint_Kitts_and_Nevis.png', 1),
(1165, 'Sierra Leone', 'SLE', 'Sierra_Leone.png', 1),
(1166, 'Slovenia', 'SLO', 'Slovenia.png', 1),
(1167, 'San Marino', 'SMR', 'San_Marino.png', 1),
(1168, 'Solomon Islands', 'SOL', 'Soloman_Islands.png', 1),
(1169, 'Somalia', 'SOM', 'Somalia.png', 1),
(1170, 'Serbia', 'SRB', NULL, 1),
(1171, 'Sri Lanka', 'SRI', 'Sri_Lanka.png', 1),
(1172, 'Sao Tomé and Príncipe', 'STP', 'Sao_Tomé_and_Príncipe.png', 1),
(1173, 'Sudan', 'SUD', 'Sudan.png', 1),
(1174, 'Switzerland', 'SUI', 'Switzerland.png', 1),
(1175, 'Suriname', 'SUR', 'Suriname.png', 1),
(1176, 'Slovakia', 'SVK', 'Slovakia.png', 1),
(1177, 'Sweden', 'SWE', 'Sweden.png', 1),
(1178, 'Swaziland', 'SWZ', 'Swaziland.png', 1),
(1179, 'Syria', 'SYR', 'Syria.png', 1),
(1180, 'Tanzania', 'TAN', 'Tanzania.png', 1),
(1181, 'Tonga', 'TGA', 'Tonga.png', 1),
(1182, 'Thailand', 'THA', 'Thailand.png', 1),
(1183, 'Tajikistan', 'TJK', 'Tajikistan.png', 1),
(1184, 'Turkmenistan', 'TKM', 'Turkmenistan.png', 1),
(1185, 'Timor-Leste', 'TLS', NULL, 1),
(1186, 'Togo', 'TOG', 'Togo.png', 1),
(1187, 'Chinese Taipei', 'TPE', 'Taiwan.png', 1),
(1188, 'Trinidad and Tobago', 'TRI', 'Trinidad_and_Tobago.png', 1),
(1189, 'Tunisia', 'TUN', 'Tunisia.png', 1),
(1190, 'Turkey', 'TUR', 'Turkey.png', 1),
(1191, 'Tuvalu', 'TUV', 'Tuvalu.png', 1),
(1192, 'United Arab Emirates', 'UAE', NULL, 1),
(1193, 'Uganda', 'UGA', 'Uganda.png', 1),
(1194, 'Ukraine', 'UKR', 'Ukraine.png', 1),
(1195, 'Uruguay', 'URU', 'Uruguay.png', 1),
(1196, 'United States', 'USA', 'United_States_of_America.png', 1),
(1197, 'Uzbekistan', 'UZB', 'Uzbekistan.png', 1),
(1198, 'Vanuatu', 'VAN', 'Vanuatu.png', 1),
(1199, 'Venezuela', 'VEN', 'Venezuela.png', 1),
(1200, 'Vietnam', 'VIE', 'Vietnam.png', 1),
(1201, 'Saint Vincent and the Grenadines', 'VIN', 'Saint_Vicent_and_the_Grenadines.png', 1),
(1202, 'Yemen', 'YEM', 'Yemen.png', 1),
(1203, 'Zambia', 'ZAM', 'Zambia.png', 1),
(1204, 'Zimbabwe', 'ZIM', 'Zimbabwe.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `uo_database`
--

CREATE TABLE `uo_database` (
  `version` int(10) DEFAULT NULL,
  `updated` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `uo_database`
--

INSERT INTO `uo_database` (`version`, `updated`) VALUES
(61, '2011-06-07 23:39:13'),
(62, '2011-11-04 21:52:17'),
(63, '2011-11-04 21:52:17'),
(64, '2011-11-04 21:52:17'),
(65, '2011-11-04 21:52:17'),
(66, '2016-06-24 12:59:19'),
(67, '2016-06-24 12:59:19'),
(68, '2016-06-24 12:59:19'),
(69, '2016-06-24 12:59:19'),
(70, '2016-06-24 12:59:20'),
(71, '2016-06-24 12:59:20'),
(72, '2016-06-24 12:59:20'),
(73, '2016-06-24 12:59:20'),
(74, '2016-06-24 12:59:20'),
(75, '2016-06-24 12:59:20'),
(76, '2016-06-24 12:59:20'),
(77, '2016-06-24 12:59:20'),
(78, '2017-03-20 14:12:20'),
(79, '2017-03-20 14:12:20'),
(80, '2018-02-02 14:15:45');

-- --------------------------------------------------------

--
-- Table structure for table `uo_defense`
--

CREATE TABLE `uo_defense` (
  `game` int(10) NOT NULL,
  `num` smallint(5) NOT NULL,
  `author` int(10) DEFAULT NULL,
  `time` smallint(5) DEFAULT NULL,
  `iscallahan` tinyint(1) NOT NULL,
  `iscaught` tinyint(1) NOT NULL,
  `ishomedefense` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_enrolledteam`
--

CREATE TABLE `uo_enrolledteam` (
  `id` int(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `clubname` varchar(50) DEFAULT NULL,
  `series` int(10) NOT NULL,
  `userid` varchar(50) NOT NULL,
  `status` int(10) DEFAULT '0',
  `enroll_time` datetime DEFAULT NULL,
  `countryname` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_event_log`
--

CREATE TABLE `uo_event_log` (
  `event_id` int(15) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(15) DEFAULT NULL,
  `user_id` varchar(50) NOT NULL,
  `category` varchar(15) NOT NULL,
  `type` varchar(15) NOT NULL,
  `id1` varchar(20) DEFAULT NULL,
  `id2` varchar(20) DEFAULT NULL,
  `source` varchar(20) DEFAULT NULL,
  `description` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_extraemail`
--

CREATE TABLE `uo_extraemail` (
  `userid` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_extraemailrequest`
--

CREATE TABLE `uo_extraemailrequest` (
  `userid` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_game`
--

CREATE TABLE `uo_game` (
  `game_id` int(10) NOT NULL,
  `hometeam` int(10) DEFAULT NULL,
  `visitorteam` int(10) DEFAULT NULL,
  `homescore` smallint(5) DEFAULT NULL,
  `visitorscore` smallint(5) DEFAULT NULL,
  `reservation` int(10) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `pool` int(10) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL,
  `halftime` int(10) DEFAULT NULL,
  `official` varchar(50) DEFAULT NULL,
  `respteam` int(10) DEFAULT NULL,
  `resppers` int(10) DEFAULT NULL,
  `isongoing` tinyint(1) DEFAULT '0',
  `scheduling_name_home` int(10) DEFAULT NULL,
  `scheduling_name_visitor` int(10) DEFAULT NULL,
  `name` int(10) DEFAULT NULL,
  `timeslot` int(10) DEFAULT NULL,
  `homedefenses` smallint(5) DEFAULT '0',
  `visitordefenses` smallint(5) DEFAULT '0',
  `hasstarted` tinyint(1) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_gameevent`
--

CREATE TABLE `uo_gameevent` (
  `game` int(10) NOT NULL,
  `num` smallint(5) NOT NULL,
  `time` smallint(5) DEFAULT NULL,
  `type` varchar(10) DEFAULT NULL,
  `ishome` tinyint(1) NOT NULL,
  `info` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_game_pool`
--

CREATE TABLE `uo_game_pool` (
  `game` int(10) NOT NULL,
  `pool` int(10) NOT NULL,
  `timetable` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_goal`
--

CREATE TABLE `uo_goal` (
  `game` int(10) NOT NULL,
  `num` smallint(5) NOT NULL,
  `assist` int(10) DEFAULT NULL,
  `scorer` int(10) DEFAULT NULL,
  `time` smallint(5) DEFAULT NULL,
  `homescore` tinyint(3) UNSIGNED DEFAULT NULL,
  `visitorscore` tinyint(3) UNSIGNED DEFAULT NULL,
  `ishomegoal` tinyint(1) NOT NULL,
  `iscallahan` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_image`
--

CREATE TABLE `uo_image` (
  `image_id` int(10) NOT NULL,
  `image_type` varchar(30) DEFAULT NULL,
  `image_height` int(5) DEFAULT NULL,
  `image_width` int(5) DEFAULT NULL,
  `image_size` int(10) DEFAULT NULL,
  `thumb_height` int(5) DEFAULT NULL,
  `thumb_width` int(5) DEFAULT NULL,
  `thumb` mediumblob,
  `image` longblob
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_keys`
--

CREATE TABLE `uo_keys` (
  `key_id` int(10) NOT NULL,
  `type` varchar(15) NOT NULL,
  `purpose` varchar(15) DEFAULT NULL,
  `id` varchar(15) DEFAULT NULL,
  `keystring` varchar(50) DEFAULT NULL,
  `secrets` varchar(50) DEFAULT NULL,
  `url` varchar(200) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_license`
--

CREATE TABLE `uo_license` (
  `lastname` varchar(255) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `membership` smallint(5) DEFAULT NULL,
  `birthdate` datetime DEFAULT NULL,
  `accreditation_id` varchar(150) DEFAULT NULL,
  `ultimate` tinyint(1) DEFAULT NULL,
  `women` tinyint(1) DEFAULT NULL,
  `junior` tinyint(1) DEFAULT NULL,
  `license` smallint(5) DEFAULT NULL,
  `external_id` int(10) DEFAULT NULL,
  `external_type` int(10) DEFAULT NULL,
  `external_validity` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_location`
--

CREATE TABLE `uo_location` (
  `id` int(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `fields` int(5) NOT NULL DEFAULT '1',
  `indoor` tinyint(1) NOT NULL DEFAULT '0',
  `address` varchar(255) DEFAULT NULL,
  `lat` float(17,13) DEFAULT NULL,
  `lng` float(17,13) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_location_info`
--

CREATE TABLE `uo_location_info` (
  `location_id` int(10) NOT NULL,
  `locale` varchar(20) NOT NULL,
  `info` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_moveteams`
--

CREATE TABLE `uo_moveteams` (
  `frompool` int(10) NOT NULL,
  `topool` int(10) NOT NULL,
  `fromplacing` int(5) NOT NULL,
  `torank` int(5) NOT NULL,
  `ismoved` tinyint(4) NOT NULL DEFAULT '0',
  `scheduling_id` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_movingtime`
--

CREATE TABLE `uo_movingtime` (
  `season` varchar(10) NOT NULL,
  `fromlocation` int(10) NOT NULL,
  `fromfield` varchar(50) NOT NULL,
  `tolocation` int(10) NOT NULL,
  `tofield` varchar(50) NOT NULL,
  `time` int(10) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_pageload_counter`
--

CREATE TABLE `uo_pageload_counter` (
  `id` int(11) NOT NULL,
  `page` varchar(100) NOT NULL,
  `loads` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_played`
--

CREATE TABLE `uo_played` (
  `player` int(10) NOT NULL,
  `game` int(10) NOT NULL,
  `num` smallint(5) DEFAULT NULL,
  `accredited` tinyint(1) NOT NULL DEFAULT '0',
  `acknowledged` tinyint(1) NOT NULL DEFAULT '0',
  `captain` tinyint(1) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_player`
--

CREATE TABLE `uo_player` (
  `player_id` int(10) NOT NULL,
  `firstname` varchar(40) DEFAULT '',
  `lastname` varchar(40) DEFAULT '',
  `team` int(10) DEFAULT NULL,
  `num` tinyint(3) UNSIGNED DEFAULT NULL,
  `accreditation_id` varchar(150) DEFAULT NULL,
  `accredited` tinyint(1) NOT NULL DEFAULT '0',
  `profile_id` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_player_profile`
--

CREATE TABLE `uo_player_profile` (
  `profile_id` int(10) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `firstname` varchar(40) DEFAULT NULL,
  `lastname` varchar(40) DEFAULT NULL,
  `num` tinyint(3) DEFAULT NULL,
  `nickname` varchar(20) DEFAULT NULL,
  `birthdate` datetime DEFAULT NULL,
  `birthplace` varchar(30) DEFAULT NULL,
  `nationality` varchar(30) DEFAULT NULL,
  `throwing_hand` varchar(15) DEFAULT NULL,
  `height` varchar(10) DEFAULT NULL,
  `story` text,
  `achievements` text,
  `image` int(10) DEFAULT NULL,
  `profile_image` varchar(30) DEFAULT NULL,
  `weight` varchar(10) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `gender` char(1) DEFAULT NULL,
  `info` text,
  `national_id` varchar(100) DEFAULT NULL,
  `accreditation_id` varchar(50) DEFAULT NULL,
  `public` varchar(200) DEFAULT 'nickname|birthplace|nationality|throwing_hand|height|weight|position|story|achievements|profile_image',
  `ffindr_id` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_player_stats`
--

CREATE TABLE `uo_player_stats` (
  `player_id` int(10) NOT NULL,
  `profile_id` int(10) NOT NULL,
  `team` int(10) DEFAULT NULL,
  `season` varchar(10) DEFAULT NULL,
  `series` int(10) DEFAULT NULL,
  `games` int(5) DEFAULT '0',
  `wins` int(5) DEFAULT '0',
  `goals` int(5) DEFAULT '0',
  `passes` int(5) DEFAULT '0',
  `callahans` int(5) DEFAULT '0',
  `breaks` int(5) DEFAULT '0',
  `offence_turns` int(5) DEFAULT '0',
  `defence_turns` int(5) DEFAULT '0',
  `offence_time` int(5) DEFAULT '0',
  `defence_time` int(5) DEFAULT '0',
  `defenses` int(5) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_pool`
--

CREATE TABLE `uo_pool` (
  `pool_id` int(10) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `ordering` varchar(20) DEFAULT NULL,
  `visible` tinyint(1) NOT NULL,
  `continuingpool` tinyint(1) NOT NULL,
  `placementpool` tinyint(1) DEFAULT '0',
  `mvgames` int(10) DEFAULT NULL,
  `timeoutlen` int(10) DEFAULT NULL,
  `halftime` int(10) DEFAULT NULL,
  `winningscore` smallint(5) DEFAULT NULL,
  `timecap` int(10) DEFAULT NULL,
  `scorecap` smallint(5) DEFAULT NULL,
  `played` tinyint(1) NOT NULL,
  `addscore` int(10) DEFAULT NULL,
  `halftimescore` int(10) DEFAULT NULL,
  `timeouts` int(10) DEFAULT NULL,
  `timeoutsper` varchar(5) DEFAULT NULL,
  `timeoutsovertime` int(10) DEFAULT NULL,
  `timeoutstimecap` varchar(5) DEFAULT NULL,
  `betweenpointslen` int(10) DEFAULT NULL,
  `series` int(10) DEFAULT NULL,
  `type` int(10) NOT NULL DEFAULT '1',
  `timeslot` int(10) DEFAULT NULL,
  `color` varchar(6) DEFAULT NULL,
  `forfeitscore` int(10) DEFAULT NULL,
  `forfeitagainst` int(10) DEFAULT NULL,
  `follower` int(10) DEFAULT NULL,
  `drawsallowed` smallint(5) DEFAULT '0',
  `playoff_template` varchar(30) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_pooltemplate`
--

CREATE TABLE `uo_pooltemplate` (
  `template_id` int(10) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `ordering` varchar(20) DEFAULT NULL,
  `continuingpool` tinyint(1) NOT NULL,
  `mvgames` int(10) DEFAULT NULL,
  `timeoutlen` int(10) DEFAULT NULL,
  `halftime` int(10) DEFAULT NULL,
  `winningscore` smallint(5) DEFAULT NULL,
  `timecap` int(10) DEFAULT NULL,
  `scorecap` smallint(5) DEFAULT NULL,
  `addscore` int(10) DEFAULT NULL,
  `halftimescore` int(10) DEFAULT NULL,
  `timeouts` int(10) DEFAULT NULL,
  `timeoutsper` varchar(5) DEFAULT NULL,
  `timeoutsovertime` int(10) DEFAULT NULL,
  `timeoutstimecap` varchar(5) DEFAULT NULL,
  `betweenpointslen` int(10) DEFAULT NULL,
  `type` int(10) NOT NULL DEFAULT '1',
  `timeslot` int(10) DEFAULT NULL,
  `forfeitscore` int(10) DEFAULT NULL,
  `forfeitagainst` int(10) DEFAULT NULL,
  `drawsallowed` smallint(5) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_registerrequest`
--

CREATE TABLE `uo_registerrequest` (
  `userid` varchar(50) NOT NULL,
  `password` char(32) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) DEFAULT NULL,
  `last_login` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_reservation`
--

CREATE TABLE `uo_reservation` (
  `id` int(10) NOT NULL,
  `location` int(10) NOT NULL,
  `fieldname` varchar(50) DEFAULT NULL,
  `reservationgroup` varchar(50) DEFAULT NULL,
  `starttime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  `season` varchar(10) DEFAULT NULL,
  `timeslots` varchar(100) DEFAULT NULL,
  `date` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_scheduling_name`
--

CREATE TABLE `uo_scheduling_name` (
  `scheduling_id` int(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_season`
--

CREATE TABLE `uo_season` (
  `season_id` varchar(10) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `starttime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  `iscurrent` tinyint(4) NOT NULL DEFAULT '0',
  `enrollopen` tinyint(1) NOT NULL DEFAULT '0',
  `enroll_deadline` datetime DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `istournament` tinyint(1) DEFAULT '0',
  `isinternational` tinyint(1) DEFAULT '0',
  `isnationalteams` tinyint(1) DEFAULT '0',
  `organizer` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `showspiritpoints` tinyint(1) DEFAULT '0',
  `timezone` varchar(50) DEFAULT NULL,
  `spiritmode` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_season_stats`
--

CREATE TABLE `uo_season_stats` (
  `season` varchar(10) NOT NULL,
  `teams` int(5) DEFAULT '0',
  `games` int(5) DEFAULT '0',
  `players` int(5) DEFAULT '0',
  `goals_total` int(5) DEFAULT '0',
  `home_wins` int(5) DEFAULT '0',
  `defenses_total` int(5) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_series`
--

CREATE TABLE `uo_series` (
  `series_id` int(10) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `ordering` varchar(5) DEFAULT NULL,
  `season` varchar(50) DEFAULT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT '0',
  `type` varchar(20) DEFAULT NULL,
  `color` varchar(6) DEFAULT NULL,
  `pool_template` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_series_stats`
--

CREATE TABLE `uo_series_stats` (
  `series_id` int(10) NOT NULL,
  `season` varchar(10) DEFAULT NULL,
  `teams` int(5) DEFAULT '0',
  `games` int(5) DEFAULT '0',
  `players` int(5) DEFAULT '0',
  `goals_total` int(5) DEFAULT '0',
  `home_wins` int(5) DEFAULT '0',
  `defenses_total` int(5) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_setting`
--

CREATE TABLE `uo_setting` (
  `name` varchar(50) DEFAULT NULL,
  `value` varchar(200) DEFAULT '',
  `setting_id` int(10) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `uo_setting`
--

INSERT INTO `uo_setting` (`name`, `value`, `setting_id`) VALUES
('CurrentSeason', NULL, 1),
('HomeTeamResponsible', 'yes', 2),
('GoogleMapsAPIKey', NULL, 3),
('TwitterEnabled', 'false', 4),
('TwitterConsumerKey', NULL, 5),
('TwitterConsumerSecret', NULL, 6),
('TwitterOAuthCallback', 'http://127.0.0.1:80/pelikone3/ext/twittercallback.php', 7),
('EmailSource', 'ultiorganizer@diibadaaba.net', 8),
('FacebookEnabled', 'false', 9),
('FacebookAppKey', NULL, 10),
('FacebookAppSecret', NULL, 11),
('FacebookGameMessage', 'Game finished in pool $pool', 12),
('FacebookUpdatePage', '', 13),
('FacebookAppId', '', 14),
('GameRSSEnabled', 'false', 15),
('PageTitle', 'Ultiorganizer - ', 16),
('DefaultTimezone', 'Europe/Helsinki', 17),
('DefaultLocale', 'en_GB.utf8', 18),
('ShowDefenseStats', 'false', 19);

-- --------------------------------------------------------

--
-- Table structure for table `uo_sms`
--

CREATE TABLE `uo_sms` (
  `sms_id` int(10) NOT NULL,
  `to1` int(15) NOT NULL,
  `to2` int(15) DEFAULT NULL,
  `to3` int(15) DEFAULT NULL,
  `to4` int(15) DEFAULT NULL,
  `to5` int(15) DEFAULT NULL,
  `msg` varchar(400) DEFAULT NULL,
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `click_id` int(10) DEFAULT NULL,
  `sent` datetime DEFAULT NULL,
  `delivered` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `uo_specialranking`
--

CREATE TABLE `uo_specialranking` (
  `frompool` int(10) NOT NULL,
  `fromplacing` int(5) NOT NULL,
  `torank` int(5) NOT NULL,
  `scheduling_id` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_spirit_category`
--

CREATE TABLE `uo_spirit_category` (
  `category_id` int(10) NOT NULL,
  `mode` int(10) NOT NULL,
  `group` int(5) NOT NULL DEFAULT '1',
  `index` int(5) NOT NULL,
  `min` int(5) NOT NULL DEFAULT '0',
  `max` int(5) NOT NULL DEFAULT '4',
  `factor` int(5) NOT NULL DEFAULT '1',
  `text` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `uo_spirit_category`
--

INSERT INTO `uo_spirit_category` (`category_id`, `mode`, `group`, `index`, `min`, `max`, `factor`, `text`) VALUES
(1000, 1001, 1, 0, 0, 4, 1, 'One simple score'),
(1001, 1001, 1, 1, 0, 20, 1, 'Spirit score'),
(1002, 1002, 1, 0, 0, 4, 1, 'WFDF (four categories plus comparison)'),
(1003, 1002, 1, 1, 0, 4, 1, 'Rules Knowledge and Use'),
(1004, 1002, 1, 2, 0, 4, 1, 'Fouls and Body Contact'),
(1005, 1002, 1, 3, 0, 4, 1, 'Fair-Mindedness'),
(1006, 1002, 1, 4, 0, 4, 1, 'Positive Attitude and Self-Control'),
(1007, 1002, 1, 5, 0, 4, 1, 'Our Spirit compared to theirs'),
(1008, 1003, 1, 0, 0, 4, 1, 'WFDF (five categories)'),
(1009, 1003, 1, 1, 0, 4, 1, 'Rules Knowledge and Use'),
(1010, 1003, 1, 2, 0, 4, 1, 'Fouls and Body Contact'),
(1011, 1003, 1, 3, 0, 4, 1, 'Fair-Mindedness'),
(1012, 1003, 1, 4, 0, 4, 1, 'Positive Attitude and Self-Control'),
(1013, 1003, 1, 5, 0, 4, 1, 'Communication'),
(1014, 1004, 1, 0, 0, 4, 1, 'WFDF (five categories, theirs and ours)'),
(1015, 1004, 1, 1, 0, 4, 1, 'Rules Knowledge and Use (theirs)'),
(1016, 1004, 1, 2, 0, 4, 0, 'Rules Knowledge and Use (ours)'),
(1017, 1004, 1, 3, 0, 4, 1, 'Fouls and Body Contact (theirs)'),
(1018, 1004, 1, 4, 0, 4, 0, 'Fouls and Body Contact (ours)'),
(1019, 1004, 1, 5, 0, 4, 1, 'Fair-Mindedness (theirs)'),
(1020, 1004, 1, 6, 0, 4, 0, 'Fair-Mindedness (ours)'),
(1021, 1004, 1, 7, 0, 4, 1, 'Positive Attitude and Self-Control (theirs)'),
(1022, 1004, 1, 8, 0, 4, 0, 'Positive Attitude and Self-Control (ours)'),
(1023, 1004, 1, 9, 0, 4, 1, 'Communication (theirs)'),
(1024, 1004, 1, 10, 0, 4, 0, 'Communication (ours)');

-- --------------------------------------------------------

--
-- Table structure for table `uo_spirit_score`
--

CREATE TABLE `uo_spirit_score` (
  `game_id` int(10) NOT NULL,
  `team_id` int(10) NOT NULL,
  `category_id` int(10) NOT NULL,
  `value` int(3) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_team`
--

CREATE TABLE `uo_team` (
  `team_id` int(10) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `pool` int(10) DEFAULT NULL,
  `club` int(10) DEFAULT NULL,
  `rank` smallint(5) DEFAULT NULL,
  `activerank` int(10) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL,
  `series` int(10) DEFAULT NULL,
  `country` int(10) DEFAULT NULL,
  `abbreviation` varchar(15) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_team_pool`
--

CREATE TABLE `uo_team_pool` (
  `team` int(10) NOT NULL,
  `pool` int(10) NOT NULL,
  `rank` smallint(5) DEFAULT NULL,
  `activerank` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_team_profile`
--

CREATE TABLE `uo_team_profile` (
  `team_id` int(11) NOT NULL,
  `coach` varchar(100) DEFAULT NULL,
  `story` text,
  `achievements` text,
  `image` int(10) DEFAULT NULL,
  `profile_image` varchar(20) DEFAULT NULL,
  `captain` varchar(100) DEFAULT '',
  `ffindr_id` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_team_stats`
--

CREATE TABLE `uo_team_stats` (
  `team_id` int(10) NOT NULL,
  `season` varchar(10) DEFAULT NULL,
  `series` int(10) DEFAULT NULL,
  `goals_made` int(5) DEFAULT '0',
  `goals_against` int(5) DEFAULT '0',
  `standing` int(5) DEFAULT '0',
  `wins` int(5) DEFAULT '0',
  `losses` int(5) DEFAULT NULL,
  `defenses_total` int(5) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_timeout`
--

CREATE TABLE `uo_timeout` (
  `timeout_id` int(10) NOT NULL,
  `game` int(10) DEFAULT NULL,
  `num` tinyint(3) UNSIGNED DEFAULT NULL,
  `time` int(10) DEFAULT NULL,
  `ishome` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_translation`
--

CREATE TABLE `uo_translation` (
  `translation_key` varchar(50) NOT NULL,
  `locale` varchar(15) NOT NULL,
  `translation` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `uo_urls`
--

CREATE TABLE `uo_urls` (
  `url_id` int(10) NOT NULL,
  `owner` varchar(15) NOT NULL,
  `owner_id` varchar(15) DEFAULT NULL,
  `type` varchar(15) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `ordering` varchar(5) DEFAULT NULL,
  `ismedialink` tinyint(1) DEFAULT '0',
  `mediaowner` varchar(100) DEFAULT NULL,
  `publisher_id` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `uo_urls`
--

INSERT INTO `uo_urls` (`url_id`, `owner`, `owner_id`, `type`, `name`, `url`, `ordering`, `ismedialink`, `mediaowner`, `publisher_id`) VALUES
(1, 'ultiorganizer', '0', 'menulink', 'Powered by Ultiorganizer', 'http://ultiorganizer.sourceforge.net/', 'XA', 0, NULL, NULL),
(2, 'ultiorganizer', '0', 'menumail', 'Administration', 'admin@example.com', 'YA', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `uo_userproperties`
--

CREATE TABLE `uo_userproperties` (
  `prop_id` int(10) NOT NULL,
  `userid` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `value` varchar(256) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `uo_userproperties`
--

INSERT INTO `uo_userproperties` (`prop_id`, `userid`, `name`, `value`) VALUES
(1, 'anonymous', 'poolselector', 'currentseason'),
(2, 'admin', 'userrole', 'superadmin');

-- --------------------------------------------------------

--
-- Table structure for table `uo_users`
--

CREATE TABLE `uo_users` (
  `id` int(10) NOT NULL,
  `userid` varchar(50) NOT NULL,
  `password` char(32) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `uo_users`
--

INSERT INTO `uo_users` (`id`, `userid`, `password`, `name`, `email`, `last_login`) VALUES
(1, 'anonymous', NULL, NULL, NULL, NULL),
(2, 'admin', '21232f297a57a5a743894a0e4a801fc3', 'Administrator', NULL, '2010-04-01 15:40:46');

-- --------------------------------------------------------

--
-- Table structure for table `uo_victorypoints`
--

CREATE TABLE `uo_victorypoints` (
  `pointdiff` tinyint(10) NOT NULL,
  `victorypoints` tinyint(10) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='defines how many victory points you get for a point diff';

--
-- Dumping data for table `uo_victorypoints`
--

INSERT INTO `uo_victorypoints` (`pointdiff`, `victorypoints`) VALUES
(-15, 0),
(-14, 1),
(-13, 2),
(-12, 3),
(-11, 4),
(-10, 5),
(-9, 6),
(-8, 7),
(-7, 8),
(-6, 9),
(-5, 10),
(-4, 11),
(-3, 12),
(-2, 13),
(-1, 14),
(0, 15),
(1, 16),
(2, 17),
(3, 18),
(4, 19),
(5, 20),
(6, 21),
(7, 22),
(8, 23),
(9, 24),
(10, 25),
(11, 25),
(12, 25),
(13, 25),
(14, 25),
(15, 25);

-- --------------------------------------------------------

--
-- Table structure for table `uo_visitor_counter`
--

CREATE TABLE `uo_visitor_counter` (
  `id` int(11) NOT NULL,
  `ip` varchar(15) NOT NULL DEFAULT '',
  `visits` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `uo_accreditationlog`
--
ALTER TABLE `uo_accreditationlog`
  ADD KEY `idx_player` (`player`),
  ADD KEY `idx_user` (`userid`),
  ADD KEY `idx_game` (`game`);

--
-- Indexes for table `uo_club`
--
ALTER TABLE `uo_club`
  ADD PRIMARY KEY (`club_id`);

--
-- Indexes for table `uo_comment`
--
ALTER TABLE `uo_comment`
  ADD PRIMARY KEY (`type`,`id`),
  ADD KEY `idx_id` (`id`);

--
-- Indexes for table `uo_country`
--
ALTER TABLE `uo_country`
  ADD PRIMARY KEY (`country_id`);

--
-- Indexes for table `uo_defense`
--
ALTER TABLE `uo_defense`
  ADD PRIMARY KEY (`game`,`num`),
  ADD KEY `idx_game` (`game`),
  ADD KEY `idx_player` (`author`);

--
-- Indexes for table `uo_enrolledteam`
--
ALTER TABLE `uo_enrolledteam`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_series` (`series`);

--
-- Indexes for table `uo_event_log`
--
ALTER TABLE `uo_event_log`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `uo_extraemail`
--
ALTER TABLE `uo_extraemail`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `uo_extraemailrequest`
--
ALTER TABLE `uo_extraemailrequest`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `uo_game`
--
ALTER TABLE `uo_game`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `idx_hometeam` (`hometeam`),
  ADD KEY `idx_visitorteam` (`visitorteam`),
  ADD KEY `idx_reservation` (`reservation`),
  ADD KEY `idx_game_id` (`game_id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_pool` (`pool`),
  ADD KEY `idx_scheduling_name_home` (`scheduling_name_home`),
  ADD KEY `idx_scheduling_name_visitor` (`scheduling_name_visitor`);

--
-- Indexes for table `uo_gameevent`
--
ALTER TABLE `uo_gameevent`
  ADD PRIMARY KEY (`game`,`num`);

--
-- Indexes for table `uo_game_pool`
--
ALTER TABLE `uo_game_pool`
  ADD PRIMARY KEY (`game`,`pool`),
  ADD KEY `idx_game` (`game`),
  ADD KEY `idx_pool` (`pool`);

--
-- Indexes for table `uo_goal`
--
ALTER TABLE `uo_goal`
  ADD PRIMARY KEY (`game`,`num`),
  ADD KEY `idx_game` (`game`),
  ADD KEY `idx_assist` (`assist`),
  ADD KEY `idx_scorer` (`scorer`);

--
-- Indexes for table `uo_image`
--
ALTER TABLE `uo_image`
  ADD PRIMARY KEY (`image_id`);

--
-- Indexes for table `uo_keys`
--
ALTER TABLE `uo_keys`
  ADD PRIMARY KEY (`key_id`);

--
-- Indexes for table `uo_license`
--
ALTER TABLE `uo_license`
  ADD KEY `idx_firstname` (`firstname`),
  ADD KEY `idx_lastname` (`lastname`);

--
-- Indexes for table `uo_location`
--
ALTER TABLE `uo_location`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `uo_location_info`
--
ALTER TABLE `uo_location_info`
  ADD PRIMARY KEY (`location_id`,`locale`);

--
-- Indexes for table `uo_moveteams`
--
ALTER TABLE `uo_moveteams`
  ADD PRIMARY KEY (`frompool`,`fromplacing`),
  ADD KEY `idx_scheduling_id` (`scheduling_id`),
  ADD KEY `idx_frompool` (`frompool`),
  ADD KEY `idx_topool` (`topool`);

--
-- Indexes for table `uo_movingtime`
--
ALTER TABLE `uo_movingtime`
  ADD PRIMARY KEY (`season`,`fromlocation`,`fromfield`,`tolocation`,`tofield`),
  ADD KEY `idx_season` (`season`);

--
-- Indexes for table `uo_pageload_counter`
--
ALTER TABLE `uo_pageload_counter`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `uo_played`
--
ALTER TABLE `uo_played`
  ADD PRIMARY KEY (`player`,`game`),
  ADD KEY `idx_player` (`player`),
  ADD KEY `idx_game` (`game`);

--
-- Indexes for table `uo_player`
--
ALTER TABLE `uo_player`
  ADD PRIMARY KEY (`player_id`),
  ADD KEY `idx_accreditation_id` (`profile_id`),
  ADD KEY `idx_team` (`team`);

--
-- Indexes for table `uo_player_profile`
--
ALTER TABLE `uo_player_profile`
  ADD PRIMARY KEY (`profile_id`);

--
-- Indexes for table `uo_player_stats`
--
ALTER TABLE `uo_player_stats`
  ADD PRIMARY KEY (`player_id`),
  ADD KEY `idx_profile_id` (`profile_id`),
  ADD KEY `idx_team` (`team`),
  ADD KEY `idx_season` (`season`),
  ADD KEY `idx_series` (`series`);

--
-- Indexes for table `uo_pool`
--
ALTER TABLE `uo_pool`
  ADD PRIMARY KEY (`pool_id`),
  ADD KEY `idx_series` (`series`);

--
-- Indexes for table `uo_pooltemplate`
--
ALTER TABLE `uo_pooltemplate`
  ADD PRIMARY KEY (`template_id`);

--
-- Indexes for table `uo_registerrequest`
--
ALTER TABLE `uo_registerrequest`
  ADD PRIMARY KEY (`userid`);

--
-- Indexes for table `uo_reservation`
--
ALTER TABLE `uo_reservation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_season` (`season`);

--
-- Indexes for table `uo_scheduling_name`
--
ALTER TABLE `uo_scheduling_name`
  ADD PRIMARY KEY (`scheduling_id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `uo_season`
--
ALTER TABLE `uo_season`
  ADD PRIMARY KEY (`season_id`);

--
-- Indexes for table `uo_season_stats`
--
ALTER TABLE `uo_season_stats`
  ADD PRIMARY KEY (`season`);

--
-- Indexes for table `uo_series`
--
ALTER TABLE `uo_series`
  ADD PRIMARY KEY (`series_id`),
  ADD KEY `idx_season` (`season`);

--
-- Indexes for table `uo_series_stats`
--
ALTER TABLE `uo_series_stats`
  ADD PRIMARY KEY (`series_id`),
  ADD KEY `idx_season` (`season`);

--
-- Indexes for table `uo_setting`
--
ALTER TABLE `uo_setting`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `uo_sms`
--
ALTER TABLE `uo_sms`
  ADD PRIMARY KEY (`sms_id`);

--
-- Indexes for table `uo_specialranking`
--
ALTER TABLE `uo_specialranking`
  ADD PRIMARY KEY (`frompool`,`fromplacing`),
  ADD KEY `idx_scheduling_id` (`scheduling_id`);

--
-- Indexes for table `uo_spirit_category`
--
ALTER TABLE `uo_spirit_category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `uo_spirit_score`
--
ALTER TABLE `uo_spirit_score`
  ADD PRIMARY KEY (`game_id`,`team_id`,`category_id`);

--
-- Indexes for table `uo_team`
--
ALTER TABLE `uo_team`
  ADD PRIMARY KEY (`team_id`),
  ADD KEY `idx_club` (`club`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_series` (`series`),
  ADD KEY `idx_pool` (`pool`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `uo_team_pool`
--
ALTER TABLE `uo_team_pool`
  ADD PRIMARY KEY (`team`,`pool`),
  ADD KEY `idx_team` (`team`),
  ADD KEY `idx_pool` (`pool`);

--
-- Indexes for table `uo_team_profile`
--
ALTER TABLE `uo_team_profile`
  ADD PRIMARY KEY (`team_id`);

--
-- Indexes for table `uo_team_stats`
--
ALTER TABLE `uo_team_stats`
  ADD PRIMARY KEY (`team_id`);

--
-- Indexes for table `uo_timeout`
--
ALTER TABLE `uo_timeout`
  ADD PRIMARY KEY (`timeout_id`),
  ADD KEY `idx_game` (`game`);

--
-- Indexes for table `uo_translation`
--
ALTER TABLE `uo_translation`
  ADD PRIMARY KEY (`translation_key`,`locale`);

--
-- Indexes for table `uo_urls`
--
ALTER TABLE `uo_urls`
  ADD PRIMARY KEY (`url_id`),
  ADD KEY `idx_owner_id` (`owner_id`);

--
-- Indexes for table `uo_userproperties`
--
ALTER TABLE `uo_userproperties`
  ADD PRIMARY KEY (`prop_id`),
  ADD KEY `idx_userid` (`userid`);

--
-- Indexes for table `uo_users`
--
ALTER TABLE `uo_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `userid` (`userid`);

--
-- Indexes for table `uo_victorypoints`
--
ALTER TABLE `uo_victorypoints`
  ADD PRIMARY KEY (`pointdiff`);

--
-- Indexes for table `uo_visitor_counter`
--
ALTER TABLE `uo_visitor_counter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `uo_club`
--
ALTER TABLE `uo_club`
  MODIFY `club_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;
--
-- AUTO_INCREMENT for table `uo_country`
--
ALTER TABLE `uo_country`
  MODIFY `country_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2000;
--
-- AUTO_INCREMENT for table `uo_enrolledteam`
--
ALTER TABLE `uo_enrolledteam`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_event_log`
--
ALTER TABLE `uo_event_log`
  MODIFY `event_id` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
--
-- AUTO_INCREMENT for table `uo_game`
--
ALTER TABLE `uo_game`
  MODIFY `game_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_image`
--
ALTER TABLE `uo_image`
  MODIFY `image_id` int(10) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `uo_keys`
--
ALTER TABLE `uo_keys`
  MODIFY `key_id` int(10) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `uo_location`
--
ALTER TABLE `uo_location`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_pageload_counter`
--
ALTER TABLE `uo_pageload_counter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `uo_player`
--
ALTER TABLE `uo_player`
  MODIFY `player_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_player_profile`
--
ALTER TABLE `uo_player_profile`
  MODIFY `profile_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_pool`
--
ALTER TABLE `uo_pool`
  MODIFY `pool_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_pooltemplate`
--
ALTER TABLE `uo_pooltemplate`
  MODIFY `template_id` int(10) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `uo_reservation`
--
ALTER TABLE `uo_reservation`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_scheduling_name`
--
ALTER TABLE `uo_scheduling_name`
  MODIFY `scheduling_id` int(10) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `uo_series`
--
ALTER TABLE `uo_series`
  MODIFY `series_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_setting`
--
ALTER TABLE `uo_setting`
  MODIFY `setting_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;
--
-- AUTO_INCREMENT for table `uo_sms`
--
ALTER TABLE `uo_sms`
  MODIFY `sms_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_spirit_category`
--
ALTER TABLE `uo_spirit_category`
  MODIFY `category_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1025;
--
-- AUTO_INCREMENT for table `uo_team`
--
ALTER TABLE `uo_team`
  MODIFY `team_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_timeout`
--
ALTER TABLE `uo_timeout`
  MODIFY `timeout_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_urls`
--
ALTER TABLE `uo_urls`
  MODIFY `url_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;
--
-- AUTO_INCREMENT for table `uo_userproperties`
--
ALTER TABLE `uo_userproperties`
  MODIFY `prop_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;
--
-- AUTO_INCREMENT for table `uo_users`
--
ALTER TABLE `uo_users`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1001;
--
-- AUTO_INCREMENT for table `uo_visitor_counter`
--
ALTER TABLE `uo_visitor_counter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
