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

   const CLASS_ACTION_SEPARATOR = ':';

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
   static function getItemtypeFromInput(array $input, $display_selector) {

      if (!empty($input['itemtype'])) {
         return $input['itemtype'];
      }

      if (isset($input['item']) && is_array($input['item'])) {
         $keys = array_keys($input['item']);
         if (count($keys) == 1) {
            return $keys[0];
         }

         if (($display_selector) && (count($keys) > 1)) {
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
    * Get the standard massive actions
    *
    * @param $item the item for which we want the massive actions
    * @param $is_deleted massive action for deleted items ?   (default 0)
    * @param $checkitem link item to check right              (default NULL)
    *
    * @return an array of massive actions
   **/
   static function getAllMassiveActions(CommonDBTM $item, $is_deleted=0, $checkitem=NULL) {
      global $CFG_GLPI, $PLUGIN_HOOKS;

      $itemtype = $item->getType();

      if (!is_null($checkitem)) {
         $canupdate = $checkitem->canUpdate();
         $candelete = $checkitem->canDelete();
         $canpurge  = $checkitem->canPurge();
      } else {
         $canupdate = $itemtype::canUpdate();
         $candelete = $itemtype::canDelete();
         $canpurge  = $itemtype::canPurge();
      }

      $actions  = array();

      $self_pref = __CLASS__.self::CLASS_ACTION_SEPARATOR;

      if ($is_deleted) {
         if ($canpurge) {
            if (in_array($item->getType(), Item_Devices::getConcernedItems())) {
               $actions[$self_pref.'purge_item_but_devices'] = _x('button',
                                                       'Delete permanently but keep devices');
               $actions[$self_pref.'purge']                  = _x('button',
                                                       'Delete permanently and remove devices');
            } else {
               $actions[$self_pref.'purge']   = _x('button', 'Delete permanently');
            }
         }

         if ( $canpurge) {
            $actions[$self_pref.'restore'] = _x('button', 'Restore');
         }

      } else {
         if ($canupdate
             || (in_array($itemtype, $CFG_GLPI["infocom_types"])
                 && Infocom::canUpdate())) {

            //TRANS: select action 'update' (before doing it)
            $actions['update'] = _x('button', 'Update');
         }

         if (in_array($itemtype, $CFG_GLPI["infocom_types"])
             && Infocom::canCreate()) {
            $actions['activate_infocoms'] = __('Enable the financial and administrative information');
         }

         if ($item instanceof CommonDBChild) {
            if (!$itemtype::$mustBeAttached) {
               $actions['unaffect'] = __('Dissociate');
            }
         }
         if ($item instanceof CommonDBRelation) {
            if ((!$itemtype::$mustBeAttached_1) || (!$itemtype::$mustBeAttached_2)) {
               $actions['unaffect'] = __('Dissociate');
            }
         }

         // do not take into account is_deleted if items may be dynamic
         if ($item->maybeDeleted()
             && !$item->useDeletedToLockIfDynamic()) {
            if ($candelete) {
               $actions[$self_pref.'delete'] = _x('button', 'Put in dustbin');
            }
         } else if ($canpurge){
            $actions['purge'] = _x('button', 'Delete permanently');
         }

         if (in_array($itemtype,$CFG_GLPI["document_types"])) {
            if (Document::canView()) {
               $actions['add_document']    = _x('button', 'Add a document');
               $actions['remove_document'] = _x('button', 'Remove a document');
            }
         }

         if (in_array($itemtype,$CFG_GLPI["contract_types"])) {
            if (Contract::canUpdate()) {
               $actions['add_contract_item']    = _x('button', 'Add a contract');
               $actions['remove_contract_item'] = _x('button', 'Remove a contract');
            }
         }
         // Specific actions
         $actions += $item->getSpecificMassiveActions($checkitem);
         // Plugin Specific actions
         if (isset($PLUGIN_HOOKS['use_massive_action'])) {
            foreach ($PLUGIN_HOOKS['use_massive_action'] as $plugin => $val) {
               $plug_actions = Plugin::doOneHook($plugin,'MassiveActions',$itemtype);

               if (count($plug_actions)) {
                  $actions += $plug_actions;
               }
            }
         }
      }
      //Add unlock if needed
      $actions += Lock::getUnlockMassiveActions($itemtype);

      // Manage forbidden actions
      $forbidden_actions = $item->getForbiddenStandardMassiveAction();
      if (is_array($forbidden_actions) && count($forbidden_actions)) {
         foreach ($forbidden_actions as $actiontodel) {
            if (isset($actions[$actiontodel])) {
               unset($actions[$actiontodel]);
            }
         }
      }
      return $actions;
   }


   /**
    * Main entry of the modal window for massive actions
    *
    * @param $input parameters from the field (mainly $_POST or $_GET)
    *
    * @return nothing: display
   **/
   static function showSubForm(array $input) {

      if (empty($input['action'])) {
         return false;
      }

      $action = explode(self::CLASS_ACTION_SEPARATOR, $input['action']);
      if (count($action) == 2) {
         // New formalism
         $processor       = $action[0];

         $display_submit = true;
         if (method_exists($processor, 'showMassiveActionsSubForm')) {
            $display_submit = $processor::showMassiveActionsSubForm($action[1], $input);
         }
         if ($display_submit) {
            echo "<input type='submit' name='massiveaction' class='submit' value='".
               __s('Post')."'>\n";
         }
      } elseif (count($action) == 1) {
         // Old formalism
         // To prevent any error when the itemtype will be remove from input ...
         $input['itemtype'] = self::getItemtypeFromInput($input, true);
         self::tmpShowMassiveActionsSubForm($input);
      }
   }


   /**
    * Display options add action button for massive actions
    *
    * Temporary method during transition from previous formalism to new one
    *
    * @param $input array of input datas
    *
    * @return nothing display
   **/
   static function tmpShowMassiveActionsSubForm(array $input) {
      global $CFG_GLPI;

      switch ($input['action']) {
         case "add_contract_item" :
            if ($input['itemtype'] == 'Contract') {
               Dropdown::showSelectItemFromItemtypes(array('itemtype_name'
                                                                   => 'item_itemtype',
                                                           'itemtypes'
                                                                    => $CFG_GLPI["contract_types"],
                                                           'checkright'
                                                                    => true));
               echo "<br><br><input type='submit' name='massiveaction' class='submit' value='".
                              _sx('button', 'Add')."'>";
            } else {
               Contract::dropdown(array('name' => "contracts_id"));
               echo "<br><br><input type='submit' name='massiveaction' class='submit' value='".
                              _sx('button', 'Add')."'>";
            }
            break;

         case "remove_contract_item" :
            if ($input['itemtype'] == 'Contract') {
               Dropdown::showSelectItemFromItemtypes(array('itemtype_name'
                                                                   => 'item_itemtype',
                                                           'itemtypes'
                                                                    => $CFG_GLPI["contract_types"],
                                                           'checkright'
                                                                    => true));
               echo "<br><br><input type='submit' name='massiveaction' class='submit' value='".
                              _sx('button', 'Delete permanently')."'>";
            } else {
               Contract::dropdown(array('name' => "contracts_id"));
               echo "<br><br><input type='submit' name='massiveaction' class='submit' value='".
                              _sx('button', 'Delete permanently')."'>";
            }
            break;

         case "add_document" :
            Document::dropdown(array('name' => 'documents_id'));
            echo "<br><br><input type='submit' name='massiveaction' class='submit' value='".
                           _sx('button', 'Add')."'>";
            break;

         case "remove_document" :
            Document::dropdown(array('name' => 'documents_id'));
            echo "<br><br><input type='submit' name='massiveaction' class='submit' value='".
                           _sx('button', 'Delete permanently')."'>";
            break;

         case 'unaffect':
            $itemtype = self::getItemtypeFromInput($input, true);
            if (is_a($itemtype, 'CommonDBRelation', true)) {
               if ((!$itemtype::$mustBeAttached_1) && (!$itemtype::$mustBeAttached_2)) {
                  $values = array();
                  if ((empty($itemtype::$itemtype_1))
                      || (preg_match('/^itemtype/', $itemtype::$itemtype_1))) {
                     $values[0] = __('First Item');
                  } else {
                     $itemtype_1 = $itemtype::$itemtype_1;
                     $values[0] = $itemtype_1::getTypeName(2);
                  }
                  if ((empty($itemtype::$itemtype_2))
                      || (preg_match('/^itemtype/', $itemtype::$itemtype_2))) {
                     $values[1] = __('Second Item');
                  } else {
                     $itemtype_2 = $itemtype::$itemtype_2;
                     $values[1] = $itemtype_2::getTypeName(2);
                  }
                  Dropdown::showFromArray('peer', $values);
               } else if (!$itemtype::$mustBeAttached_1) {
                  echo "<input type='hidden' name='peer' value='0'>";
               } else if (!$itemtype::$mustBeAttached_2) {
                  echo "<input type='hidden' name='peer' value='1'>";
               }
            }
            echo "<br><br><input type='submit' name='massiveaction' class='submit' value='".
                           __('Dissociate')."'>";
            break;

         case "update" :
            $itemtype = self::getItemtypeFromInput($input, true);
            // Specific options for update fields
            if (!isset($input['options'])) {
               $input['options'] = array();
            }
            $group          = "";
            $show_all       = true;
            $show_infocoms  = true;

            if (in_array($itemtype, $CFG_GLPI["infocom_types"])
                && (!$itemtype::canUpdate()
                    || !Infocom::canUpdate())) {
               $show_all      = false;
               $show_infocoms = Infocom::canUpdate();
            }
            $searchopt = Search::getCleanedOptions($itemtype, UPDATE);

            $values = array(0 => Dropdown::EMPTY_VALUE);

            foreach ($searchopt as $key => $val) {
               if (!is_array($val)) {
                  $group = $val;
               } else {
                  // No id and no entities_id massive action and no first item
                  if (($val["field"] != 'id')
                      && ($key != 1)
                     // Permit entities_id is explicitly activate
                      && (($val["linkfield"] != 'entities_id')
                          || (isset($val['massiveaction']) && $val['massiveaction']))) {

                     if (!isset($val['massiveaction']) || $val['massiveaction']) {

                        if ($show_all) {
                           $values[$group][$key] = $val["name"];
                        } else {
                           // Do not show infocom items
                           if (($show_infocoms
                                && Search::isInfocomOption($itemtype, $key))
                               || (!$show_infocoms
                                   && !Search::isInfocomOption($itemtype, $key))) {
                              $values[$group][$key] = $val["name"];
                           }
                        }
                     }
                  }
               }
            }

            $rand = Dropdown::showFromArray('id_field', $values);

            $paramsmassaction = array('id_field' => '__VALUE__',
                                      'itemtype' => $itemtype,
                                      'options'  => $input['options']);

            foreach ($input as $key => $val) {
               if (preg_match("/extra_/",$key,$regs)) {
                  $paramsmassaction[$key] = $val;
               }
            }
            Ajax::updateItemOnSelectEvent("dropdown_id_field$rand", "show_massiveaction_field",
                                          $CFG_GLPI["root_doc"]."/ajax/dropdownMassiveActionField.php",
                                          $paramsmassaction);

            echo "<br><br><span id='show_massiveaction_field'>&nbsp;</span>\n";
            break;

         default :
            // TODO : fix it after transition ...
            $itemtype = self::getItemtypeFromInput($input, true);
            if (!($item = getItemForItemtype($itemtype))) {
               exit();
            }
            if (!$item->showSpecificMassiveActionsParameters($input)) {
               echo "<input type='submit' name='massiveaction' class='submit' value='".
                      _sx('button','Post')."'>\n";
            }
      }

      return false;
   }


   /**
    * Process the massive actions for all passed items. This a switch between different methods:
    * old formalism, new one, plugin ...
    *
    * @param $input array of input datas
    *
    * @return an array of results (ok, ko, noright counts, may include REDIRECT field to set REDIRECT page)
   **/
   static function process(array $input) {

      if (!isset($input["item"]) || (count($input["item"]) == 0) || empty($input['action'])) {
         return false;
      }

      $res = array('ok'      => 0,
                   'ko'      => 0,
                   'noright' => 0);

      $action = explode(self::CLASS_ACTION_SEPARATOR, $input['action']);
      if (count($action) == 2) {
         // New formalism
         $processor       = $action[0];

         if (method_exists($processor, 'processMassiveActionsForSeveralItemtype')) {
            $res = $processor::processMassiveActionsForSeveralItemtype($action[1], $input);
         } else {
            $res['must_process_each_itemtype'] = true;
         }
         if (!empty($res['must_process_each_itemtype'])) {
            unset($res['must_process_each_itemtype']);
            if (method_exists($processor, 'processMassiveActionsForOneItemtype')) {
               foreach ($input['item'] as $itemtype => $ids) {
                  if ($item = getItemForItemtype($itemtype)) {
                     $tmpres = $processor::processMassiveActionsForOneItemtype($action[1], $item,
                                                                               $ids, $input);
                     $res['ok']      += $tmpres['ok'];
                     $res['ko']      += $tmpres['ko'];
                     $res['noright'] += $tmpres['noright'];
                  }
               }
            }
         }

      } elseif (count($action) == 1) {
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
               $actions = self::getAllMassiveActions($item, $input['is_deleted'], $checkitem);

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
      }
      return $res;
   }


   /**
    * Execute the massive actions (new formalism) by itemtypes
    *
    * @param $action the name of the action
    * @param $item the item on which apply the massive action
    * @param $ids an array of the ids of the item on which apply the action
    * @param $input the array of the input provided by the form ($_POST, $_GET ...)
    *
    * @return an array of results (ok, ko, noright counts, may include REDIRECT field to set REDIRECT page)
   **/
   static function processMassiveActionsForOneItemtype($action, CommonDBTM $item, array $ids,
                                                       array $input) {

      $res = array('ok'      => 0,
                   'ko'      => 0,
                   'noright' => 0);

      switch ($action) {
         case 'delete':
            foreach ($ids as $id => $val) {
               if ($val == 1) {
                  if ($item->can($id, DELETE)) {
                     if ($item->delete(array("id" => $id))) {
                        $res['ok']++;
                     } else {
                        $res['ko']++;
                        $res['messages'][] = $item->getErrorMessage(ERROR_ON_ACTION);
                     }
                  } else {
                     $res['noright']++;
                     $res['messages'][] = $item->getErrorMessage(ERROR_RIGHT);
                  }
               }
            }
            break;

         case 'restore' :
            foreach ($ids as $id => $val) {
               if ($val == 1) {
                  if ($item->can($id, PURGE)) {
                     if ($item->restore(array("id" => $id))) {
                        $res['ok']++;
                     } else {
                        $res['ko']++;
                        $res['messages'][] = $item->getErrorMessage(ERROR_ON_ACTION);
                     }
                  } else {
                     $res['noright']++;
                     $res['messages'][] = $item->getErrorMessage(ERROR_RIGHT);
                  }
               }
            }
            break;

         case 'purge_item_but_devices':
         case 'purge' :
            foreach ($ids as $id => $val) {
               if ($val == 1) {
                  if ($item->can($id, PURGE)) {
                     $force = 1;
                     // Only mark deletion for
                     if ($item->maybeDeleted()
                         && $item->useDeletedToLockIfDynamic()
                         && $item->isDynamic()) {
                        $force = 0;
                     }
                     $delete_array = array('id' => $id);
                     if ($input['action'] == 'purge_item_but_devices') {
                        $delete_array['keep_devices'] = true;
                     }
                     if ($item->delete($delete_array, $force)) {
                        $res['ok']++;
                     } else {
                        $res['ko']++;
                        $res['messages'][] = $item->getErrorMessage(ERROR_ON_ACTION);
                     }
                  } else {
                     $res['noright']++;
                     $res['messages'][] = $item->getErrorMessage(ERROR_RIGHT);
                  }
               }
            }
            break;

      }

      return $res;
   }
}

?>
