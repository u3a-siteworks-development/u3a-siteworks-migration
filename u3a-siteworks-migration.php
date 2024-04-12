<?php
/* 
Plugin Name: u3a Siteworks Migration 
Plugin URI: https://u3awpdev.org.uk/
Description: Provides facility to migrate html files from sitebuilder
Version: 1.2.10
Author: Camilla Jordan, Nick Talbott, u3aWPdev team
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

require "u3a-siteworks-migration-activate.php";
require "u3a-siteworks-migration-admin.php";
require "u3a-siteworks-migrate.php";
require "u3a-siteworks-agroup.php";
require "u3a-siteworks-media.php";
require "u3a-siteworks-page.php";
require "u3a-siteworks-html.php";
require "u3a-siteworks-anevent.php";
require "u3a-siteworks-process.php";
require "u3a-siteworks-contact.php";
register_activation_hook(__FILE__, 'u3a_siteworks_migration_install');
