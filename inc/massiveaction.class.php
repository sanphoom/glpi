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
class MassiveAction {

   const CLASS_ACTION_SEPARATOR = ':';

   /**
    * Get all the actions available regarding the checked items. If the checkitem is not valid,
    * then, exists ! If an itemtype is not valid,then remove it from the given items.
    *
    * @param $input $input (as reference) list of the inputs (mainly $_POST or $_GET)
    * @param $set_hidden_check_item_fields If true, then, define the checked item for next stage.
    *
    * @return the array of the actions available
   **/
   static function getAllActionsFromInput(array &$input, $set_hidden_check_item_fields) {
      $checkitem = NULL;

      if (isset($input['check_itemtype'])) {
         if (!($checkitem = getItemForItemtype($input['check_itemtype']))) {
            exit();
         }
         if (isset($input['check_items_id'])) {
            if (!$checkitem->getFromDB($input['check_items_id'])) {
               exit();
            }
            if ($set_hidden_check_item_fields) {
               echo Html::Hidden('check_items_id', array('value' => $_POST["check_items_id"]));
            }
         }
         if ($set_hidden_check_item_fields) {
            echo Html::Hidden('check_itemtype', array('value' => $_POST["check_itemtype"]));
         }
      }

      $actions = array();
      if (isset($input['item'])) {
         foreach ($input['item'] as $itemtype => $values) {
            $item_actions = self::getAllMassiveActions($itemtype, $input['is_deleted'], $checkitem);
            if (is_array($item_actions)) {
               $actions = array_merge($actions, $item_actions);
            } else {
               unset($input['item'][$itemtype]);
            }
         }
      }
      return $actions;
   }


   /**
    * Add hidden fields containing all the checked items to the current form
    *
    * @param $input list of input (mainly $_POST or $_GET)
    *
    * @return nothing (display)
   **/
   static function addHiddenItemsFromInput(array $input) {
      if (isset($input['item']) && is_array($input['item'])) {
         foreach ($input['item'] as $itemtype => $items_ids) {
            foreach ($items_ids as $items_id => $val) {
               echo Html::hidden('item['.$itemtype.']['.$items_id.']', array('value' => $val));
            }
         }
      }
   }

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
            $itemtypes = array(-1 => Dropdown::EMPTY_VALUE);
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
    * @return an array of massive actions or false if $item is not valid
   **/
   static function getAllMassiveActions($item, $is_deleted=0, CommonDBTM $checkitem = NULL) {
      global $CFG_GLPI, $PLUGIN_HOOKS;

      // TODO: when maybe* will be static, when can completely switch to $itemtype !
      if (is_string($item)) {
         $itemtype = $item;
         if (!($item = getItemForItemtype($itemtype))) {
            return false;
         }
      } elseif ($item instanceof CommonDBTM) {
         $itemtype = $item->getType();
      } else {
         return false;
      }


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
            if (in_array($itemtype, Item_Devices::getConcernedItems())) {
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
            $actions[$self_pref.'update'] = _x('button', 'Update');
         }

         if (in_array($itemtype, $CFG_GLPI["infocom_types"])
             && Infocom::canCreate()) {
            $actions['activate_infocoms'] = __('Enable the financial and administrative information');
         }

         CommonDBConnexity::getMassiveActionsForItemtype($actions, $itemtype,
                                                         $is_deleted, $checkitem);

         // do not take into account is_deleted if items may be dynamic
         if ($item->maybeDeleted()
             && !$item->useDeletedToLockIfDynamic()) {
            if ($candelete) {
               $actions[$self_pref.'delete'] = _x('button', 'Put in dustbin');
            }
         } else if ($canpurge){
            $actions['purge'] = _x('button', 'Delete permanently');
         }

         if (in_array($itemtype, $CFG_GLPI["document_types"])) {
            if (Document::canView()) {
               $actions['add_document']    = _x('button', 'Add a document');
               $actions['remove_document'] = _x('button', 'Remove a document');
            }
         }

         if (in_array($itemtype, $CFG_GLPI["contract_types"])) {
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
               $plug_actions = Plugin::doOneHook($plugin, 'MassiveActions', $itemtype);

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
            self::addHiddenItemsFromInput($input);
            echo "<input type='submit' name='massiveaction' class='submit' value='".
               __s('Post')."'>\n";
         }
      } elseif (count($action) == 1) {
         // Old formalism
         // To prevent any error when the itemtype will be remove from input ...

         self::addHiddenItemsFromInput($input);
         $input['itemtype'] = self::getItemtypeFromInput($input, true);

         $split = explode('_',$input["action"]);

         if (($split[0] == 'plugin') && isset($split[1])) {
            // Normalized name plugin_name_action
            // Allow hook from any plugin on any (core or plugin) type
            $plugin_input = array('action'   => $input['action'],
                                  'itemtype' => $input['itemtype']);
            Plugin::doOneHook($split[1], 'MassiveActionsDisplay', $plugin_input);

            /*
         } elseif ($plug=isPluginItemType($_POST["itemtype"])) {
            // non-normalized name
            // hook from the plugin defining the type
            Plugin::doOneHook($plug['plugin'], 'MassiveActionsDisplay', $_POST["itemtype"],
                              $_POST["action"]);
            */
         } else {
            self::tmpShowMassiveActionsSubForm($input);
         }
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

         case 'update' :
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
    * Class-specific method used to show the fields to specify the massive action
    *
    * @param $action the name of the action (not prefixed by the class name)
    * @param $input the inputs (mainly $_POST or $_GET)
    *
    * @return nothing (display only)
   **/
   static function showMassiveActionsSubForm($action, array $input) {
      global $CFG_GLPI;

      switch ($action) {
         case 'update':
            $itemtype = self::getItemtypeFromInput($input, true);
            self::addHiddenItemsFromInput($input);
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
      }
      return false;
   }


   /**
    * Process the massive actions for all passed items. This a switch between different methods:
    * new system, old one and plugins ...
    *
    * @param $input array of input datas
    *
    * @return an array of results (ok, ko, noright counts, may include REDIRECT field to set REDIRECT page)
   **/
   static function process(array $input) {

      if (!isset($input['item']) || (count($input['item']) == 0) || empty($input['action'])) {
         return false;
      }

      $res = array('ok'      => 0,
                   'ko'      => 0,
                   'noright' => 0);

      $action = explode(self::CLASS_ACTION_SEPARATOR, $input['action']);
      if (count($action) == 2) {
         // New formalism
         $processor = $action[0];

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
    * Class specific execution of the massive action (new system) by itemtypes
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

         case 'update' :
            $input['itemtype'] = self::getItemtypeFromInput($input, false);
            $searchopt         = Search::getCleanedOptions($input["itemtype"], UPDATE);
            if (isset($searchopt[$input["id_field"]])) {
               /// Infocoms case
               if (!isPluginItemType($input["itemtype"])
                   && Search::isInfocomOption($input["itemtype"], $input["id_field"])) {

                  $ic               = new Infocom();
                  $link_entity_type = -1;
                  /// Specific entity item
                  if ($searchopt[$input["id_field"]]["table"] == "glpi_suppliers") {
                     $ent = new Supplier();
                     if ($ent->getFromDB($input[$input["field"]])) {
                        $link_entity_type = $ent->fields["entities_id"];
                     }
                  }
                  foreach ($ids as $key => $val) {
                     if ($val == 1) {
                        if ($item->getFromDB($key)) {
                           if (($link_entity_type < 0)
                               || ($link_entity_type == $item->getEntityID())
                               || ($ent->fields["is_recursive"]
                                   && in_array($link_entity_type, getAncestorsOf("glpi_entities",
                                               $item->getEntityID())))) {
                              $input2["items_id"] = $key;
                              $input2["itemtype"] = $input["itemtype"];

                              if ($ic->can(-1, CREATE, $input2)) {
                                 // Add infocom if not exists
                                 if (!$ic->getFromDBforDevice($input["itemtype"],$key)) {
                                    $input2["items_id"] = $key;
                                    $input2["itemtype"] = $input["itemtype"];
                                    unset($ic->fields);
                                    $ic->add($input2);
                                    $ic->getFromDBforDevice($input["itemtype"], $key);
                                 }
                                 $id = $ic->fields["id"];
                                 unset($ic->fields);
                                 if ($ic->update(array('id'   => $id,
                                                       $input["field"]
                                                              => $input[$input["field"]]))) {
                                    $res['ok']++;
                                 } else {
                                    $res['ko']++;
                                    $res['messages'][] = $item->getErrorMessage(ERROR_ON_ACTION);
                                 }
                              } else {
                                 $res['noright']++;
                                 $res['messages'][] = $item->getErrorMessage(ERROR_RIGHT);
                              }
                           } else {
                              $res['ko']++;
                              $res['messages'][] = $item->getErrorMessage(ERROR_COMPAT);
                           }
                        } else {
                           $res['ko']++;
                           $res['messages'][] = $item->getErrorMessage(ERROR_NOT_FOUND);
                        }
                     }
                  }

               } else { /// Not infocoms

                  $link_entity_type = array();
                  /// Specific entity item
                  $itemtable = getTableForItemType($input["itemtype"]);

                  $itemtype2 = getItemTypeForTable($searchopt[$input["id_field"]]["table"]);
                  if ($item2 = getItemForItemtype($itemtype2)) {

                     if (($input["id_field"] != 80) // No entities_id fields
                         && ($searchopt[$input["id_field"]]["table"] != $itemtable)
                         && $item2->isEntityAssign()
                         && $item->isEntityAssign()) {
                        if ($item2->getFromDB($input[$input["field"]])) {
                           if (isset($item2->fields["entities_id"])
                               && ($item2->fields["entities_id"] >= 0)) {

                              if (isset($item2->fields["is_recursive"])
                                  && $item2->fields["is_recursive"]) {
                                 $link_entity_type = getSonsOf("glpi_entities",
                                                               $item2->fields["entities_id"]);
                              } else {
                                 $link_entity_type[] = $item2->fields["entities_id"];
                              }
                           }
                        }
                     }
                  }

                  foreach ($ids as $key => $val) {
                     if ($val == 1) {
                        if ($item->canEdit($key)
                            && $item->canMassiveAction($input['action'], $input['field'],
                                                       $input[$input["field"]])) {
                           if ((count($link_entity_type) == 0)
                               || in_array($item->fields["entities_id"], $link_entity_type)) {
                              if ($item->update(array('id'   => $key,
                                                      $input["field"]
                                                             => $input[$input["field"]]))) {
                                 $res['ok']++;
                              } else {
                                 $res['ko']++;
                                 $res['messages'][] = $item->getErrorMessage(ERROR_ON_ACTION);
                              }
                           } else {
                              $res['ko']++;
                              $res['messages'][] = $item->getErrorMessage(ERROR_COMPAT);
                           }
                        } else {
                           $res['noright']++;
                           $res['messages'][] = $item->getErrorMessage(ERROR_RIGHT);
                        }
                     }
                  }
               }
            }
            break;

      }

      return $res;
   }
}

?>
