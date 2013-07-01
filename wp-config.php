<?php
/**
 * La configuration de base de votre installation WordPress.
 *
 * Ce fichier contient les réglages de configuration suivants : réglages MySQL,
 * préfixe de table, clefs secrètes, langue utilisée, et ABSPATH.
 * Vous pouvez en savoir plus à leur sujet en allant sur 
 * {@link http://codex.wordpress.org/Editing_wp-config.php Modifier
 * wp-config.php} (en anglais). C'est votre hébergeur qui doit vous donner vos
 * codes MySQL.
 *
 * Ce fichier est utilisé par le script de création de wp-config.php pendant
 * le processus d'installation. Vous n'avez pas à utiliser le site web, vous
 * pouvez simplement renommer ce fichier en "wp-config.php" et remplir les
 * valeurs.
 *
 * @package WordPress
 */

// ** Réglages MySQL - Votre hébergeur doit vous fournir ces informations. ** //
/** Nom de la base de données de WordPress. */
define('DB_NAME', 'flemmingite');

/** Utilisateur de la base de données MySQL. */
define('DB_USER', 'root');

/** Mot de passe de la base de données MySQL. */
define('DB_PASSWORD', 'root');

/** Adresse de l'hébergement MySQL. */
define('DB_HOST', 'localhost');

/** Jeu de caractères à utiliser par la base de données lors de la création des tables. */
define('DB_CHARSET', 'utf8');

/** Type de collation de la base de données. 
  * N'y touchez que si vous savez ce que vous faites. 
  */
define('DB_COLLATE', '');

/**#@+
 * Clefs uniques d'authentification et salage.
 *
 * Remplacez les valeurs par défaut par des phrases uniques !
 * Vous pouvez générer des phrases aléatoires en utilisant 
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ le service de clefs secrètes de WordPress.org}.
 * Vous pouvez modifier ces phrases à n'importe quel moment, afin d'invalider tous les cookies existants.
 * Cela forcera également tous les utilisateurs à se reconnecter.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '8kUh1=(6: xv^#,Ky.zxtI!1Uiy7hgg~z3-f@ k4RJt})(>5jw_`:Y^,_L7V&1Ad');
define('SECURE_AUTH_KEY',  'Ji(15$6+A}YzGihjFe2>|;C5I&3YRRyl]-)ZV-e ;5wE]-qP3Jlk,d!RI^xK./he');
define('LOGGED_IN_KEY',    '];V>Y)mAQ=;SIiL26wr(Y8.?cw5DGdM ()l,o_s0ioilQFav1mJ18LGMZ_Z>?Ob/');
define('NONCE_KEY',        'wa_1sOTv$NMvN,h7hly`tw#SVqMeR#+sLVy@PYztK+f5$V^j5I@+nE|SS*AFko?K');
define('AUTH_SALT',        'j^`k,3y&W)g;AI;Nas#Gs7z1G0I1$BGF+FCzs_Y] alm)R<8;n@]Ge={8OmBCT2$');
define('SECURE_AUTH_SALT', '1Fsz2zkeJZgruX9<oiPSL/=&w@IY7Bwx#X}>&}LQAjs*)~pEjIb9I;.HE5YB^e0Y');
define('LOGGED_IN_SALT',   '`bK3o6;el,*U-G%#1bl&Ghyz3A!4xpB;^#4%%)Zij|2/;wR_DaHuPKL+=L~l@iIz');
define('NONCE_SALT',       'NH|+Ooa6xq]P~sRhFJ/@uO=nd_r!D^s,]NT jd}I&@KP #YMXU]NO64<N,1;fnhl');
/**#@-*/

/**
 * Préfixe de base de données pour les tables de WordPress.
 *
 * Vous pouvez installer plusieurs WordPress sur une seule base de données
 * si vous leur donnez chacune un préfixe unique. 
 * N'utilisez que des chiffres, des lettres non-accentuées, et des caractères soulignés!
 */
$table_prefix  = 'wp_';

/**
 * Langue de localisation de WordPress, par défaut en Anglais.
 *
 * Modifiez cette valeur pour localiser WordPress. Un fichier MO correspondant
 * au langage choisi doit être installé dans le dossier wp-content/languages.
 * Par exemple, pour mettre en place une traduction française, mettez le fichier
 * fr_FR.mo dans wp-content/languages, et réglez l'option ci-dessous à "fr_FR".
 */
define('WPLANG', 'fr_FR');

/** 
 * Pour les développeurs : le mode deboguage de WordPress.
 * 
 * En passant la valeur suivante à "true", vous activez l'affichage des
 * notifications d'erreurs pendant votre essais.
 * Il est fortemment recommandé que les développeurs d'extensions et
 * de thèmes se servent de WP_DEBUG dans leur environnement de 
 * développement.
 */ 
define('WP_DEBUG', false); 

/* C'est tout, ne touchez pas à ce qui suit ! Bon blogging ! */

/** Chemin absolu vers le dossier de WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once(ABSPATH . 'wp-settings.php');