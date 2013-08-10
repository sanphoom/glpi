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

include ('../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

if (isset($_POST["action"]) && ($_POST["action"] != '-1')
    && (!empty($_POST["item"]))) {

   if (!isset($_POST['is_deleted'])) {
      $_POST['is_deleted'] = 0;
   }

   // Only set $_POST['item'][itemtype] if $_POST['itemtype'] is defined
   if (!empty($_POST['itemtype'])) {
      if ($_POST['itemtype'] == -1) {
         exit();
      }
      $itemtype = $_POST['itemtype'];
      if (!empty($_POST['item'][$itemtype])) {
         $_POST['item'] = array($itemtype => $_POST['item'][$itemtype]);
      } else {
         $_POST['item'] = array();
      }
      unset($itemtype);
   }

   $actions = MassiveAction::getAllActionsFromInput($_POST, true);

   if (!isset($_POST['specific_action']) || !$_POST['specific_action']) {
      // If it is not a specific action, then, it must be a standard action !
      if (!isset($actions[$_POST['action']])) {
         Html::displayRightError();
         exit();
      }
      $_POST['specific_action'] = 0;
   } else {
      $_POST['specific_action'] = (isset($actions[$_POST['action']]) ? 0 : 1);
   }

   // Remove from itemtypes the items that doesn't match the action
   if ($_POST['specific_action'] == 0) {
      foreach ($_POST['item'] as $itemtype => $ids) {
         $actions = MassiveAction::getAllMassiveActions($itemtype, $_POST['is_deleted'],
                                                        $checkitem);
         if (!isset($actions[$_POST['action']])) {
            unset($_POST['item'][$itemtype]);
         }
      }
   }

   MassiveAction::showSubForm($_POST);
}
?>
