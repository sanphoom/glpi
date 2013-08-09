<?php
/*
 * @version $Id: commondbtm.class.php 21445 2013-07-31 07:06:44Z yllen $
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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


/** @file
* @brief
*/

/**
 * Class that manages all the massive actions
 *
 * @since version 0.85
**/
class MassiveActions {


   /**
    * Extract itemtype from the input (ie.: $input['itemtype'] is defined or $input['item'] only
    * contains one type of item. If none is available and we can display selector (inside the modal
    * window), then display a dropdown to select the itemtype.
    * This is only usefull in case of itemtype specific massive actions (update, ...)
    *
    * @param $input            the array of the input, mainly $_POST, $_GET or $_REQUEST
    * @param $display_selector can we display the itemtype selector ?
    *
    * @return the itemtype or false if we cannot define it (and we cannot display the selector)
   **/
   static function getItemtypeFromInput(array $input, $display_selector = false) {

      if (!empty($input['itemtype'])) {
         return $input['itemtype'];
      }

      if (isset($input['item']) && is_array($input['item'])) {
         $keys = array_keys($input['item']);
         if (count($keys) == 1) {
            return $keys[0];
         }

         if ($display_selector) {
            $itemtypes = array();
            foreach ($keys as $itemtype) {
               $itemtypes[$itemtype] = $itemtype::getTypeName(2);
            }

            _e('Select the type of the item on which applying this action')."<br>\n";

            $rand = Dropdown::showFromArray('itemtype', $itemtypes);

            echo "<br><br>";

            $params             = $input;
            $params['itemtype'] = '__VALUE__';
            Ajax::updateItemOnSelectEvent("dropdown_itemtype$rand", "show_itemtype$rand",
                                          $_SERVER['REQUEST_URI'], $params);

            echo "<span id='show_itemtype$rand'>&nbsp;</span>\n";
            exit();
         }
      }

     return false;
   }


   /**
    * process the massive actions for all passed items
    *
    * @param $input array of input datas
    *
    * @return an array of results (ok, ko, noright counts, may include REDIRECT field to set REDIRECT page)
   **/
   static function processMassiveActions(array $input) {

      if (!isset($input["item"]) || (count($input["item"]) == 0)) {
         return false;
      }

      $res = array('ok'      => 0,
                   'ko'      => 0,
                   'noright' => 0);

      foreach ($input['item'] as $itemtype => $data) {
         $input['itemtype'] = $itemtype;
         $input['item']     = $data;

         // Check if action is available for this itemtype
         $actionok = false;
         if ($item = getItemForItemtype($itemtype)) {
            $checkitem = NULL;
            if (isset($input['check_itemtype'])) {
               if ($checkitem = getItemForItemtype($input['check_itemtype'])) {
                  if (isset($input['check_items_id'])) {
                     $checkitem->getFromDB($input['check_items_id']);
                  }
               }
            }
            $actions = $item->getAllMassiveActions($input['is_deleted'], $checkitem);

            if ($input['specific_action'] || isset($actions[$input['action']])) {
               $actionok = true;
            } else {
               $res['noright'] += count($input['item']);
            }
         }

         if ($actionok) {
            if ($tmpres = $item->doMassiveActions($input)) {
               $res['ok']      += $tmpres['ok'];
               $res['ko']      += $tmpres['ko'];
               $res['noright'] += $tmpres['noright'];
            }
         } else {
            $res['noright'] += count($input['item']);
         }
      }
      return $res;
   }

}
?>
