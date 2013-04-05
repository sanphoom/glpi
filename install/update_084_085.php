<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2013 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
* @brief
*/

/**
 * Update from 0.84 to 0.85
 *
 * @return bool for success (will die for most error)
**/
function update084to085() {
   global $DB, $migration;

   $updateresult     = true;
   $ADDTODISPLAYPREF = array();

   //TRANS: %s is the number of new version
   $migration->displayTitle(sprintf(__('Update to %s'), '0.85'));
   $migration->setVersion('0.85');

   $backup_tables = false;
   $newtables     = array('glpi_changes', 'glpi_changes_groups', 'glpi_changes_items',
                          'glpi_changes_problems', 'glpi_changes_suppliers',
                          'glpi_changes_tickets', 'glpi_changes_users',
                          'glpi_changetasks'
                          // Only do profilerights once : so not delete it
                          /*, 'glpi_profilerights'*/);

   foreach ($newtables as $new_table) {
      // rename new tables if exists ?
      if (TableExists($new_table)) {
         $migration->dropTable("backup_$new_table");
         $migration->displayWarning("$new_table table already exists. ".
                                    "A backup have been done to backup_$new_table.");
         $backup_tables = true;
         $query         = $migration->renameTable("$new_table", "backup_$new_table");
      }
   }
   if ($backup_tables) {
      $migration->displayWarning("You can delete backup tables if you have no need of them.",
                                 true);
   }


   $migration->displayMessage(sprintf(__('Data migration - %s'), 'config table'));

   if (FieldExists('glpi_configs', 'version')) {
      if (!TableExists('origin_glpi_configs')) {
         $migration->copyTable('glpi_configs', 'origin_glpi_configs');
      }

      $query  = "SELECT *
                 FROM `glpi_configs`
                 WHERE `id` = '1'";
      $result_of_configs = $DB->query($query);

      // Update glpi_configs
      $migration->addField('glpi_configs', 'context', 'VARCHAR(150) COLLATE utf8_unicode_ci',
                           array('update' => "'core'"));
      $migration->addField('glpi_configs', 'name', 'VARCHAR(150) COLLATE utf8_unicode_ci',
                           array('update' => "'version'"));
      $migration->addField('glpi_configs', 'value', 'text', array('update' => "'0.85'"));
      $migration->addKey('glpi_configs', array('context', 'name'), 'unicity', 'UNIQUE');

      $migration->migrationOneTable('glpi_configs');

      $fields = array();
      if ($DB->numrows($result_of_configs) == 1) {
         $configs = $DB->fetch_assoc($result_of_configs);
         unset($configs['id']);
         unset($configs['version']);
         // First drop fields not to have constraint on insert
         foreach ($configs as $name => $value) {
            $migration->dropField('glpi_configs', $name);
         }
         $migration->migrationOneTable('glpi_configs');
         // Then insert new values
         foreach ($configs as $name => $value) {
            $query = "INSERT INTO `glpi_configs`
                             (`context`, `name`, `value`)
                      VALUES ('core', '$name', '$value');";
            $DB->query($query);
         }
      }
      $migration->dropField('glpi_configs', 'version');
      $migration->migrationOneTable('glpi_configs');
      $migration->dropTable('origin_glpi_configs');

   }

   $migration->displayMessage(sprintf(__('Data migration - %s'), 'profile table'));

   if (!TableExists('glpi_profilerights')) {
      if (!TableExists('origin_glpi_profiles')) {
         $migration->copyTable('glpi_profiles', 'origin_glpi_profiles');
      }

      /// TODO : right using char(1) ? not able to store others configs... But interesting to change it ?

      $query = "CREATE TABLE `glpi_profilerights` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `profiles_id` int(11) NOT NULL DEFAULT '0',
                  `name` varchar(255) DEFAULT NULL,
                  `right` char(1) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unicity` (`profiles_id`, `name`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 add table glpi_profilerights");

      $query = "DESCRIBE `origin_glpi_profiles`";

      $rights = array();
      foreach ($DB->request($query) as $field) {
         if ($field['Type'] == 'char(1)') {
            $rights[$field['Field']] = $field['Field'];
            $migration->dropField('glpi_profiles', $field['Field']);
         }
      }
      $query = "SELECT *
                FROM `origin_glpi_profiles`";
      foreach ($DB->request($query) as $profile) {
         $profiles_id = $profile['id'];
         foreach ($rights as $right) {
            if ($profile[$right] == NULL) {
               $new_right = '';
            } else {
               $new_right = $profile[$right];
            }
            $query = "INSERT INTO `glpi_profilerights`
                             (`profiles_id`, `name`, `right`)
                      VALUES ('$profiles_id', '$right', '".$profile[$right]."')";
            $DB->query($query);
         }
      }
      $migration->migrationOneTable('glpi_profiles');
      $migration->dropTable('origin_glpi_profiles');

      ProfileRight::addProfileRights(array('show_my_change', 'show_all_change', 'edit_all_change'));

      ProfileRight::updateProfileRightAsOtherRight('show_my_change', '1',
                                                   "`name` = 'own_ticket' AND `right`='1'");
      ProfileRight::updateProfileRightAsOtherRight('show_all_change', '1',
                                                   "`name` = 'show_all_ticket' AND `right`='1'");
      ProfileRight::updateProfileRightAsOtherRight('edit_all_change', '1',
                                                   "`name` = 'update_ticket' AND `right`='1'");

   }

   $migration->displayMessage(sprintf(__('Change of the database layout - %s'), 'Change'));

   // changes management
   if (!TableExists('glpi_changes')) {
      $query = "CREATE TABLE `glpi_changes` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) DEFAULT NULL,
                  `entities_id` int(11) NOT NULL DEFAULT '0',
                  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
                  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
                  `status` int(11) NOT NULL DEFAULT '1',
                  `content` longtext DEFAULT NULL,
                  `date_mod` DATETIME DEFAULT NULL,
                  `date` DATETIME DEFAULT NULL,
                  `solvedate` DATETIME DEFAULT NULL,
                  `closedate` DATETIME DEFAULT NULL,
                  `due_date` DATETIME DEFAULT NULL,
                  `users_id_recipient` int(11) NOT NULL DEFAULT '0',
                  `users_id_lastupdater` int(11) NOT NULL DEFAULT '0',
                  `urgency` int(11) NOT NULL DEFAULT '1',
                  `impact` int(11) NOT NULL DEFAULT '1',
                  `priority` int(11) NOT NULL DEFAULT '1',
                  `itilcategories_id` int(11) NOT NULL DEFAULT '0',
                  `impactcontent` longtext DEFAULT NULL,
                  `controlistcontent` longtext DEFAULT NULL,
                  `rolloutplancontent` longtext DEFAULT NULL,
                  `backoutplancontent` longtext DEFAULT NULL,
                  `checklistcontent` longtext DEFAULT NULL,
                  `solutiontypes_id` int(11) NOT NULL DEFAULT '0',
                  `solution` text COLLATE utf8_unicode_ci,
                  `actiontime` int(11) NOT NULL DEFAULT '0',
                  `notepad` LONGTEXT NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`),
                  KEY `entities_id` (`entities_id`),
                  KEY `is_recursive` (`is_recursive`),
                  KEY `is_deleted` (`is_deleted`),
                  KEY `date` (`date`),
                  KEY `closedate` (`closedate`),
                  KEY `status` (`status`),
                  KEY `priority` (`priority`),
                  KEY `date_mod` (`date_mod`),
                  KEY `itilcategories_id` (`itilcategories_id`),
                  KEY `users_id_recipient` (`users_id_recipient`),
                  KEY `solvedate` (`solvedate`),
                  KEY `solutiontypes_id` (`solutiontypes_id`),
                  KEY `urgency` (`urgency`),
                  KEY `impact` (`impact`),
                  KEY `due_date` (`due_date`),
                  KEY `users_id_lastupdater` (`users_id_lastupdater`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 create glpi_changes");
   }

   if (!TableExists('glpi_changes_users')) {
      $query = "CREATE TABLE `glpi_changes_users` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `changes_id` int(11) NOT NULL DEFAULT '0',
                  `users_id` int(11) NOT NULL DEFAULT '0',
                  `type` int(11) NOT NULL DEFAULT '1',
                  `use_notification` tinyint(1) NOT NULL DEFAULT '0',
                  `alternative_email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unicity` (`changes_id`,`type`,`users_id`,`alternative_email`),
                  KEY `user` (`users_id`,`type`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 add table glpi_changes_users");
   }

   if (!TableExists('glpi_changes_groups')) {
      $query = "CREATE TABLE `glpi_changes_groups` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `changes_id` int(11) NOT NULL DEFAULT '0',
                  `groups_id` int(11) NOT NULL DEFAULT '0',
                  `type` int(11) NOT NULL DEFAULT '1',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unicity` (`changes_id`,`type`,`groups_id`),
                  KEY `group` (`groups_id`,`type`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 add table glpi_changes_groups");
   }

   if (!TableExists('glpi_changes_suppliers')) {
      $query = "CREATE TABLE `glpi_changes_suppliers` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `changes_id` int(11) NOT NULL DEFAULT '0',
                  `suppliers_id` int(11) NOT NULL DEFAULT '0',
                  `type` int(11) NOT NULL DEFAULT '1',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unicity` (`changes_id`,`type`,`suppliers_id`),
                  KEY `group` (`suppliers_id`,`type`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 add table glpi_changes_suppliers");
   }

   if (!TableExists('glpi_changes_items')) {
      $query = "CREATE TABLE `glpi_changes_items` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `changes_id` int(11) NOT NULL DEFAULT '0',
                  `itemtype` varchar(100) default NULL,
                  `items_id` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unicity` (`changes_id`,`itemtype`,`items_id`),
                  KEY `item` (`itemtype`,`items_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 add table glpi_changes_items");
   }

   if (!TableExists('glpi_changes_tickets')) {
      $query = "CREATE TABLE `glpi_changes_tickets` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `changes_id` int(11) NOT NULL DEFAULT '0',
                  `tickets_id` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unicity` (`changes_id`,`tickets_id`),
                  KEY `tickets_id` (`tickets_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 add table glpi_changes_tickets");
   }

   if (!TableExists('glpi_changes_problems')) {
      $query = "CREATE TABLE `glpi_changes_problems` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `changes_id` int(11) NOT NULL DEFAULT '0',
                  `problems_id` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unicity` (`changes_id`,`problems_id`),
                  KEY `problems_id` (`problems_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 add table glpi_changes_problems");
   }

   if (!TableExists('glpi_changetasks')) {
      $query = "CREATE TABLE `glpi_changetasks` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) DEFAULT NULL,
                  `changes_id` int(11) NOT NULL DEFAULT '0',
                  `changetasks_id` int(11) NOT NULL DEFAULT '0',
                  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
                  `taskcategories_id` int(11) NOT NULL DEFAULT '0',
                  `status` varchar(255) DEFAULT NULL,
                  `priority` int(11) NOT NULL DEFAULT '1',
                  `percentdone` int(11) NOT NULL DEFAULT '0',
                  `date` datetime DEFAULT NULL,
                  `begin` datetime DEFAULT NULL,
                  `end` datetime DEFAULT NULL,
                  `users_id` int(11) NOT NULL DEFAULT '0',
                  `users_id_tech` int(11) NOT NULL DEFAULT '0',
                  `content` longtext COLLATE utf8_unicode_ci,
                  `actiontime` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`),
                  KEY `changes_id` (`changes_id`),
                  KEY `changetasks_id` (`changetasks_id`),
                  KEY `is_blocked` (`is_blocked`),
                  KEY `priority` (`priority`),
                  KEY `status` (`status`),
                  KEY `percentdone` (`percentdone`),
                  KEY `users_id` (`users_id`),
                  KEY `users_id_tech` (`users_id_tech`),
                  KEY `date` (`date`),
                  KEY `begin` (`begin`),
                  KEY `end` (`end`),
                  KEY `taskcategories_id` (taskcategories_id)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, "0.85 add table glpi_changetasks");
   }

   /// TODO add changetasktypes table as dropdown
   /// TODO review users linked to changetask
   /// TODO add display prefs
/*
   ProfileRight::addProfileRights(array('show_my_change',
                                        'show_all_change',
                                        'edit_all_change'));

   ProfileRight::updateProfileRightAsOtherRight('show_my_change', '1',
                                 "`name` = 'own_ticket' AND `right`='1'");
   ProfileRight::updateProfileRightAsOtherRight('show_all_change', '1',
                                 "`name` = 'show_all_ticket' AND `right`='1'");
   ProfileRight::updateProfileRightAsOtherRight('edit_all_change', '1',
                                 "`name` = 'update_ticket' AND `right`='1'");
*/
   $migration->addField('glpi_profiles', 'change_status', "text",
                        array('comment' => "json encoded array of from/dest allowed status change"));


   $migration->displayMessage(sprintf(__('Data migration - %s'), 'drop rules cache'));
   $migration->dropTable('glpi_rulecachecomputermodels');
   $migration->dropTable('glpi_rulecachecomputertypes');
   $migration->dropTable('glpi_rulecachemanufacturers');
   $migration->dropTable('glpi_rulecachemonitormodels');
   $migration->dropTable('glpi_rulecachemonitortypes');
   $migration->dropTable('glpi_rulecachenetworkequipmentmodels');
   $migration->dropTable('glpi_rulecachenetworkequipmenttypes');
   $migration->dropTable('glpi_rulecacheoperatingsystems');
   $migration->dropTable('glpi_rulecacheoperatingsystemservicepacks');
   $migration->dropTable('glpi_rulecacheoperatingsystemversions');
   $migration->dropTable('glpi_rulecacheperipheralmodels');
   $migration->dropTable('glpi_rulecacheperipheraltypes');
   $migration->dropTable('glpi_rulecachephonemodels');
   $migration->dropTable('glpi_rulecachephonetypes');
   $migration->dropTable('glpi_rulecacheprintermodels');
   $migration->dropTable('glpi_rulecacheprinters');
   $migration->dropTable('glpi_rulecacheprintertypes');
   $migration->dropTable('glpi_rulecachesoftwares');

   $migration->displayMessage(sprintf(__('Data migration - %s'), 'glpi_rules'));

   $migration->addField("glpi_rules", 'uuid', "string");
   $migration->migrationOneTable('glpi_rules');


   //generate uuid for the basic rules of glpi
   // we use a complete sql where for cover all migration case (0.78 -> 0.85)
   $rules = array(array('sub_type'    => 'RuleImportEntity',
                        'name'        => 'Root',
                        'match'       => 'AND',
                        'description' => ''),

                  array('sub_type'    => 'RuleRight',
                        'name'        => 'Root',
                        'match'       => 'AND',
                        'description' => ''),

                  array('sub_type'    => 'RuleMailCollector',
                        'name'        => 'Root',
                        'match'       => 'AND',
                        'description' => ''),

                  array('sub_type'    => 'RuleMailCollector',
                        'name'        => 'Auto-Reply X-Auto-Response-Suppress',
                        'match'       => 'AND',
                        'description' => 'Exclude Auto-Reply emails using X-Auto-Response-Suppress header'),

                  array('sub_type'    => 'RuleMailCollector',
                        'name'        => 'Auto-Reply Auto-Submitted',
                        'match'       => 'AND',
                        'description' => 'Exclude Auto-Reply emails using Auto-Submitted header'),

                  array('sub_type'    => 'RuleTicket',
                        'name'        => 'Ticket location from item',
                        'match'       => 'AND',
                        'description' => ''),

                  array('sub_type'    => 'RuleTicket',
                        'name'        => 'Ticket location from user',
                        'match'       => 'AND',
                        'description' => ''));

   $i = 0;
   foreach ($rules as $rule) {
      $query  = "UPDATE `glpi_rules`
                 SET `uuid` = 'STATIC-UUID-$i'
                 WHERE `entities_id` = 0
                       AND `is_recursive` = 0
                       AND `sub_type` = '".$rule['sub_type']."'
                       AND `name` = '".$rule['name']."'
                       AND `description` = '".$rule['description']."'
                       AND `match` = '".$rule['match']."'
                 ORDER BY id ASC
                 LIMIT 1";
      $DB->queryOrDie($query, "0.85 add uuid to basic rules (STATIC-UUID-$i)");
      $i++;
   }

   //generate uuid for the rules of user
   foreach ($DB->request('glpi_rules', array('uuid' => NULL)) as $data) {
      $uuid  = Rule::getUuid();
      $query = "UPDATE `glpi_rules`
                SET `uuid` = '$uuid'
                WHERE `id` = '".$data['id']."'";
      $DB->queryOrDie($query, "0.85 add uuid to existing rules");
   }

   $migration->addField('glpi_users', 'is_deleted_ldap', 'bool');
   $migration->addKey('glpi_users', 'is_deleted_ldap');

   Config::setConfigurationValues('core', array('use_unicodefont' => 0));
   $migration->addField("glpi_users", 'use_unicodefont', "int(11) DEFAULT NULL");
   $migration->addField("glpi_users", 'picture', "string", array('value' => 'NULL'));

   $migration->addField("glpi_authldaps", 'picture_field','string');

   $migration->addField('glpi_links', 'open_window', 'bool', array('value' => 1));

   $migration->displayMessage(sprintf(__('Data migration - %s'), 'glpi_states'));
   foreach (array('is_visible_computer', 'is_visible_monitor', 'is_visible_networkequipment',
                  'is_visible_peripheral', 'is_visible_phone', 'is_visible_printer',
                  'is_visible_softwareversion') as $field)  {
      $migration->addField('glpi_states', $field, 'bool',
                           array('value' => '1'));
      $migration->addKey('glpi_states', $field);
   }


   // glpi_domains by entity
   $migration->addField('glpi_domains', 'entities_id', 'integer', array('after' => 'name'));
   $migration->addField('glpi_domains', 'is_recursive', 'bool', array('update' => '1',
                                                                      'after'  => 'entities_id'));

   // glpi_states by entity
   $migration->addField('glpi_states', 'entities_id', 'integer', array('after' => 'name'));
   $migration->addField('glpi_states', 'is_recursive', 'bool', array('update' => '1',
                                                                     'after'  => 'entities_id'));


   // add validity date for a user
   $migration->addField('glpi_users', 'begin', 'datetime');
   $migration->addField('glpi_users', 'end', 'datetime');

   // ************ Keep it at the end **************
   //TRANS: %s is the table or item to migrate
   $migration->displayMessage(sprintf(__('Data migration - %s'), 'glpi_displaypreferences'));

   foreach ($ADDTODISPLAYPREF as $type => $tab) {
      $query = "SELECT DISTINCT `users_id`
                FROM `glpi_displaypreferences`
                WHERE `itemtype` = '$type'";

      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)>0) {
            while ($data = $DB->fetch_assoc($result)) {
               $query = "SELECT MAX(`rank`)
                         FROM `glpi_displaypreferences`
                         WHERE `users_id` = '".$data['users_id']."'
                               AND `itemtype` = '$type'";
               $result = $DB->query($query);
               $rank   = $DB->result($result,0,0);
               $rank++;

               foreach ($tab as $newval) {
                  $query = "SELECT *
                            FROM `glpi_displaypreferences`
                            WHERE `users_id` = '".$data['users_id']."'
                                  AND `num` = '$newval'
                                  AND `itemtype` = '$type'";
                  if ($result2=$DB->query($query)) {
                     if ($DB->numrows($result2)==0) {
                        $query = "INSERT INTO `glpi_displaypreferences`
                                         (`itemtype` ,`num` ,`rank` ,`users_id`)
                                  VALUES ('$type', '$newval', '".$rank++."',
                                          '".$data['users_id']."')";
                        $DB->query($query);
                     }
                  }
               }
            }

         } else { // Add for default user
            $rank = 1;
            foreach ($tab as $newval) {
               $query = "INSERT INTO `glpi_displaypreferences`
                                (`itemtype` ,`num` ,`rank` ,`users_id`)
                         VALUES ('$type', '$newval', '".$rank++."', '0')";
               $DB->query($query);
            }
         }
      }
   }

   // must always be at the end
   $migration->executeMigration();

   return $updateresult;
}

?>