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
* @since version 0.84
*/

include ('../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();


if (isset($_POST['itemtype']) && isset($_POST['container'])) {
   if (!($item = getItemForItemtype($_POST['itemtype']))) {
      exit();
   }
   if (!isset($_POST['is_deleted'])) {
      $_POST['is_deleted'] = 0;
   }
   $checkitem = NULL;
   if (isset($_POST['check_itemtype'])) {
      $checkitem = new $_POST['check_itemtype']();
      if (isset($_POST['check_items_id'])) {
         $checkitem->getFromDB($_POST['check_items_id']);
      }
   }
   echo "<div width='90%' class='center'><br>";
   $formname = 'massform'.$_POST['itemtype'].mt_rand();
   Html::openMassiveActionsForm($formname);
   echo "<script type='text/javascript'>";
   echo "var items = $('[id=".$_POST['container']."] [id*=massaction_item_]:checked').each(function( index ) {
                     $('<input>').attr({
                     type: 'hidden',
                     name: $(this).attr('name'),
                     value: 1
                     }).appendTo('#$formname');
         });";
   echo "</script>";
   $params = array('action' => '__VALUE__');
   foreach ($_POST as $key => $val) {
      $params[$key] = $val;
   }

   $params['specific_action'] = 0;
   $actions                   = MassiveAction::getAllMassiveActions($item, $_POST['is_deleted'],
                                                                    $checkitem);
   if (isset($_POST['specific_actions'])
       && is_array($_POST['specific_actions'])
       && count($_POST['specific_actions'])) {
      $specific_actions = Toolbox::stripslashes_deep($_POST['specific_actions']);

      // If specific actions is used to limit display
      if (count($specific_actions) != count(array_intersect_key($actions,$specific_actions))) {
         $params['specific_action'] = 1;
      }
      $actions = $specific_actions;
   }

   if (count($actions)) {
      if (isset($params['hidden']) && count($params['hidden'])) {
         foreach ($params['hidden'] as $key => $val) {
            echo "<input type='hidden' name=\"$key\" value=\"$val\">";
         }

      }
      _e('Action');
      echo "&nbsp;";

      $actions = array_merge(array(-1 => Dropdown::EMPTY_VALUE), $actions);
      $rand    = Dropdown::showFromArray('massiveaction', $actions);

      echo "<br><br>";

      Ajax::updateItemOnSelectEvent("dropdown_massiveaction$rand", "show_massiveaction$rand",
                                    $CFG_GLPI["root_doc"]."/ajax/dropdownMassiveAction.php",
                                    $params);

      echo "<span id='show_massiveaction$rand'>&nbsp;</span>\n";
   }
   // Force 'checkbox-zero-on-empty', because some massive actions can use checkboxes
   $CFG_GLPI['checkbox-zero-on-empty'] = true;
   Html::closeForm();
   echo "</div>";
}
?>
