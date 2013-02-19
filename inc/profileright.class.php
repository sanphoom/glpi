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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/// Profile class
class ProfileRight extends CommonDBChild {

   // From CommonDBChild:
   static public $itemtype = 'Profile';
   static public $items_id = 'profiles_id'; // Field name


   static function getAllPossibleRights() {
      global $DB;

      if (!isset($_SESSION['glpi_all_possible_rights'])
         || count($_SESSION['glpi_all_possible_rights']) == 0) {
         $_SESSION['glpi_all_possible_rights'] = array();
         $rights = array();
         $query  = "SELECT DISTINCT `name`
                    FROM `".self::getTable()."`";
         foreach ($DB->request($query) as $right) {
            // By default, all rights are NULL ...
            $_SESSION['glpi_all_possible_rights'][$right['name']] = '';
         }
      }
      return $_SESSION['glpi_all_possible_rights'];
   }


   static function getProfileRights($profiles_id, array $rights = array()) {
      global $DB;

      if (count($rights) == 0) {
         $query  = "SELECT *
                    FROM `glpi_profilerights`
                    WHERE `profiles_id` = '$profiles_id'";
      } else {
         $query  = "SELECT *
                    FROM `glpi_profilerights`
                    WHERE `profiles_id` = '$profiles_id'
                      AND `name` IN ('".implode("', '", $rights)."')";
      }
      $rights = array();
      foreach ($DB->request($query) as $right) {
         $rights[$right['name']] = $right['right'];
      }
      return $rights;
   }

   static function addProfileRights(array $rights) {
      global $DB;

      $query = "SELECT `id`
                  FROM `glpi_profiles`;";
      foreach ($DB->request($query) as $profile) {
         $profiles_id = $profile['id'];
         foreach ($rights as $name) {
            $query = "INSERT INTO `glpi_profilerights` (`profiles_id`, `name`)
                              VALUES ('$profiles_id', '$name')";
            $DB->query($query);
         }
      }
      $_SESSION['glpi_all_possible_rights'] = array();
   }

   static function deleteProfileRights(array $rights) {
      global $DB;

      foreach ($rights as $name) {
         $query = "DELETE FROM `glpi_profilerights`
                     WHERE `name` = '$name'";
         $DB->query($query);
      }
      $_SESSION['glpi_all_possible_rights'] = array();
   }

   static function fillProfileRights($profiles_id) {
      global $DB;

      $query = "SELECT DISTINCT POSSIBLE.`name` AS NAME
                FROM `glpi_profilerights` AS POSSIBLE
                WHERE NOT EXISTS (
                        SELECT *
                        FROM `glpi_profilerights` AS CURRENT
                        WHERE CURRENT.`profiles_id` = '$profiles_id'
                          AND CURRENT.`NAME` = POSSIBLE.`NAME`)";

      foreach ($DB->request($query) as $right) {
         $query = "INSERT INTO `glpi_profilerights`
                     (`profiles_id`, `name`) values ('$profiles_id', '".$right['NAME']."')";
         $DB->query($query);
      }

   }

   function updateProfileRights($profiles_id, array $rights = array()) {

      foreach ($rights as $name => $right) {
         if ($this->getFromDBByQuery("WHERE `profiles_id` = '$profiles_id'
                                        AND `name` = '$name'")) {

            $input = array('id'          => $this->getID(),
                           'right'       => $right);

            $this->update($input);
         } else {

            $input = array('profiles_id' => $profiles_id,
                           'name'        => $name,
                           'right'       => $right);

            $this->add($input);
         }
      }

      // Don't forget to complete the profile rights ...
      self::fillProfileRights($profiles_id);
   }
}
?>