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
   $newtables     = array('glpi_profilerights');

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
     $migration->copyTable('glpi_configs', 'origin_glpi_configs');
     
     $query  = "SELECT * FROM `glpi_configs` WHERE `id` = '1'";
     $result_of_configs = $DB->query($query);

     // Update glpi_configs
     $migration->addField('glpi_configs', 'context', 'VARCHAR(150) COLLATE utf8_unicode_ci',
                          array('update' => "'core'"));
     $migration->addField('glpi_configs', 'name',    'VARCHAR(150) COLLATE utf8_unicode_ci',
                          array('update' => "'version'"));
     $migration->addField('glpi_configs', 'value',   'text',   array('update' => "'0.85'"));
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
           $query = "INSERT INTO `glpi_configs` (`context`, `name`, `value`)
                                         VALUES ('core', '$name', '$value');";
           $DB->query($query);
        }
     }
     $migration->dropField('glpi_configs', 'version');
     $migration->migrationOneTable('glpi_configs');
  }
   

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

   $migration->displayMessage(sprintf(__('Data migration - %s'), 'glpi_rules'));
    
   $migration->addField("glpi_rules", 'uuid', "string");
   $migration->migrationOneTable('glpi_rules');
   
   
   //generate uuid for the basic rules of glpi
   // we use a complete sql where for cover all migration case (0.78 -> 0.84)
   $rules = array(array('sub_type' => 'RuleImportEntity', 'name' => 'Root', 'match' => 'AND',
                         'description' => ''),
                   array('sub_type' => 'RuleRight', 'name' => 'Root', 'match' => 'AND',
                         'description' => ''),
                   array('sub_type' => 'RuleMailCollector', 'name' => 'Root', 'match' => 'AND',
                         'description' => ''),
                   array('sub_type' => 'RuleMailCollector',
                         'name' => 'Auto-Reply X-Auto-Response-Suppress', 'match' => 'AND',
                         'description' => 'Exclude Auto-Reply emails using X-Auto-Response-Suppress header'),
                  array('sub_type' => 'RuleMailCollector',
                         'name' => 'Auto-Reply Auto-Submitted', 'match' => 'AND',
                         'description' => 'Exclude Auto-Reply emails using Auto-Submitted header'),
                  array('sub_type' => 'RuleTicket', 'name' => 'Ticket location from item', 'match' => 'AND',
                         'description' => ''),
                  array('sub_type' => 'RuleTicket', 'name' => 'Ticket location from user', 'match' => 'AND',
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
      $uuid = Rule::getUuid();
      $query  = "UPDATE `glpi_rules`
      SET `uuid` = '$uuid'
      WHERE `id` = '".$data['id']."'";
      $DB->queryOrDie($query, "0.85 add uuid to existing rules");
   }
   // must always be at the end
   $migration->executeMigration();

   return $updateresult;
}

?>