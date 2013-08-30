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
 * @TODO: all documentation !
 *
 * @since version 0.85
**/
class MassiveAction {

   const CLASS_ACTION_SEPARATOR = ':';

   const NO_ACTION      = 0;
   const ACTION_OK      = 1;
   const ACTION_KO      = 2;
   const ACTION_NORIGHT = 3;


   /**
    * Constructor of massive actions.
    * There is three stages and each one have its own objectives:
    * - initial: propose the actions and filter the checkboxes (only once)
    * - specialize: add action specific fields and filter items. There can be as many as needed!
    * - process: process the massive action (only once, but can be reload to avoid timeout)
    *
    * We trust all previous stages: we don't redo the checks
    *
    * @param $POST  something like $_POST
    * @param $GET   something like $_GET
    * @param $stage the current stage
    *
    * @return nothing (it is a constructor).
   **/
   function __construct (array $POST, array $GET, $stage) {
      global $CFG_GLPI;

      // Attributs to add to $_SESSION
      $this->attributes  = array('identifier', 'items', 'nb_items', 'results', 'messages',
                                 'redirect', 'POST', 'done', 'action', 'processor', 'action_name');
      $this->timer = new Timer();
      $this->timer->start();

      if (!empty($POST)) {

         if (!isset($POST['is_deleted'])) {
            $POST['is_deleted'] = 0;
         }

         $this->nb_items = 0;

         if ((isset($POST['item'])) || (isset($POST['items']))) {

            $remove_from_post = array();

            switch ($stage) {
               case 'initial':

                  $POST['action_filter'] = array();
                  if (isset($POST['specific_actions'])) {
                     $POST['actions']  = $POST['specific_actions'];
                     $specific_action = 1;
                     $dont_filter_for = array_keys($POST['actions']);
                  } else{
                     $specific_action = 0;
                     if (isset($POST['add_actions'])) {
                        $POST['actions']  = $POST['add_actions'];
                        $dont_filter_for = array_keys($POST['actions']);
                     } else {
                        $POST['actions']  = array();
                        $dont_filter_for = array();
                     }
                  }
                  if (count($dont_filter_for)) {
                     $POST['dont_filter_for'] = array_combine($dont_filter_for, $dont_filter_for);
                  } else {
                     $POST['dont_filter_for'] = array();
                  }
                  $remove_from_post[] = 'specific_actions';
                  $remove_from_post[] = 'add_actions';

                  $POST['items']         = array();
                  foreach ($POST['item'] as $itemtype => $ids) {
                     // initial are raw checkboxes: 0=unchecked or 1=checked
                     $items = array();
                     foreach ($ids as $id => $checked) {
                        if ($checked == 1) {
                           $items[$id] = $id;
                           $this->nb_items ++;
                        }
                     }
                     $POST['items'][$itemtype] = $items;
                     if (!$specific_action) {
                        $actions = self::getAllMassiveActions($itemtype, $POST['is_deleted'],
                                                              $this->getCheckItem($POST));
                        $POST['actions'] = array_merge($actions, $POST['actions']);
                        foreach ($actions as $action => $label) {
                           $POST['action_filter'][$action][] = $itemtype;
                           $POST['actions'][$action] = $label;
                        }
                     }
                  }

                  if (empty($POST['actions'])) {
                     throw new Exception(__('No action available'));
                  }

                  // Initial items is used to define $_SESSION['glpimassiveactionselected']
                  $POST['initial_items'] = $POST['items'];

                  $remove_from_post[] = 'item';
                  break;
               case 'specialize':
                  if (!isset($POST['action'])) {
                     Toolbox::logDebug('Implementation error !');
                     throw new Exception(__('Implementation error !'));
                  }
                  if ($POST['action'] == -1) {
                     exit();
                  }
                  if (isset($POST['actions'])) {
                     // First, get the name of the action !
                     if (!isset($POST['actions'][$POST['action']])) {
                        Toolbox::logDebug('Implementation error !');
                        throw new Exception(__('Implementation error !'));
                     }
                     $POST['action_name'] = $POST['actions'][$POST['action']];
                     $remove_from_post[] = 'actions';

                     // Then filter the items regarding the action
                     if (!isset($POST['dont_filter_for'][$POST['action']])) {
                        if (isset($POST['action_filter'][$POST['action']])) {
                           $items = array();
                           foreach ($POST['action_filter'][$POST['action']] as $itemtype) {
                              if (isset($POST['items'][$itemtype])) {
                                 $items[$itemtype] = $POST['items'][$itemtype];
                              }
                           }
                           $POST['items'] = $items;
                        }
                     }
                     $remove_from_post[] = 'dont_filter_for';
                     $remove_from_post[] = 'action_filter';
                  }

                  if (isset($POST['specialize_itemtype'])) {
                     $itemtype = $POST['specialize_itemtype'];
                     if (isset($POST['items'][$itemtype])) {
                        $POST['items'] = array($itemtype => $POST['items'][$itemtype]);
                     } else {
                        $POST['items'] = array();
                     }
                     $remove_from_post[] = 'specialize_itemtype';
                  }

                  if (!isset($POST['processor'])) {
                     $action = explode(self::CLASS_ACTION_SEPARATOR, $POST['action']);
                     if (count($action) == 2) {
                        $POST['processor'] = $action[0];
                        $POST['action']    = $action[1];
                     } else {
                        $POST['processor'] = '';
                        $POST['action']    = $POST['action'];
                     }
                     if ($POST['processor'] == '') {
                        throw new Exception(__('Not re-implemented for the moment !'));
                     }
                  }

                  // Count number of items !
                  foreach ($POST['items'] as $itemtype => $ids) {
                     $this->nb_items += count($ids);
                  }
                  break;
               case 'process':

                  if (isset($POST['initial_items'])) {
                     $_SESSION['glpimassiveactionselected'] = $POST['initial_items'];
                  } else {
                     $_SESSION['glpimassiveactionselected'] = array();
                  }

                  $remove_from_post = array('items', 'action', 'action_name', 'processor',
                                            'massiveaction', 'is_deleted', 'initial_items');

                  $this->identifier  = mt_rand();
                  $this->messages    = array();
                  $this->done        = array();
                  $this->action_name = $POST['action_name'];
                  $this->results     = array('ok'      => 0,
                                             'ko'      => 0,
                                             'noright' => 0);

                  foreach ($POST['items'] as $itemtype => $ids) {
                     $this->nb_items += count($ids);
                  }

                  if (isset($_SERVER['HTTP_REFERER'])) {
                     $this->redirect = $_SERVER['HTTP_REFERER'];
                  } else {
                     $this->redirect = $CFG_GLPI['root_doc']."/front/central.php";
                  }

                 break;
            }
            $this->POST = $POST;
            foreach (array('items', 'action', 'processor') as $field) {
               if (isset($this->POST[$field])) {
                  $this->$field = $this->POST[$field];
               }
            }
            foreach ($remove_from_post as $field) {
               if (isset($this->POST[$field])) {
                  unset($this->POST[$field]);
               }
            }
         }
         if ($this->nb_items == 0) {
            throw new Exception(__('No selected items'));
         }
      } else {
         if (($stage != 'process')
             || (!isset($_SESSION['current_massive_action'][$GET['identifier']]))) {
            Toolbox::logDebug('Implementation error !');
            throw new Exception(__('Implementation error !'));
         }
         $identifier = $GET['identifier'];
         foreach ($this->attributes as $attribute) {
            if (!isset($_SESSION['current_massive_action'][$identifier][$attribute])) {
               $this->error = __('Invalid processus');
               return;
            }
            $this->$attribute = $_SESSION['current_massive_action'][$identifier][$attribute];
         }
         if ($this->identifier != $identifier) {
            $this->error = __('Invalid processus');
            return;
         }
         unset($_SESSION['current_massive_action'][$identifier]);
      }
   }


   function getInput() {
      if (isset($this->POST)) {
         return $this->POST;
      }
      return array();
   }


   function getAction() {
      if (isset($this->action)) {
         return $this->action;
      }
      return NULL;
   }


   function getItems() {
      if (isset($this->items)) {
         return $this->items;
      }
      return array();
   }


   function __destruct() {
      if (isset($this->identifier)) {
         // $this->identifier is unset by self::process() when the massive actions are finished
         $_SESSION['current_massive_action'][$this->identifier] = array();
         foreach ($this->attributes as $attribute) {
            $_SESSION['current_massive_action'][$this->identifier][$attribute] = $this->$attribute;
         }
      }
   }


   function getCheckItem($POST) {
      if (!isset($this->check_item)) {
         if (isset($POST['check_itemtype'])) {
            if (!($this->check_item = getItemForItemtype($POST['check_itemtype']))) {
               exit();
            }
            if (isset($POST['check_items_id'])) {
               if (!$this->check_item->getFromDB($POST['check_items_id'])) {
                  exit();
               } else {
                  $this->check_item->getEmpty();
               }
            }
         } else {
            $this->check_item = NULL;
         }
      }
      return $this->check_item;
   }


   /**
    * Add hidden fields containing all the checked items to the current form
    *
    * @param $input list of input (mainly $_POST or $_GET)
    *
    * @return nothing (display)
   **/
   function addHiddenFields() {
      if (empty($this->hidden_fields_defined)) {
         $this->hidden_fields_defined = true;

         $common_fields = array('action', 'processor', 'is_deleted', 'initial_items',
                                'item_itemtype', 'item_items_id', 'items', 'action_name');

         if (!empty($this->POST['massive_action_fields'])) {
            $common_fields = array_merge($common_fields, $this->POST['massive_action_fields']);
         }

         foreach ($common_fields as $field) {
            if (isset($this->POST[$field])) {
               echo Html::recursiveHidden($field, array('value' => $this->POST[$field]));
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
    * @param $display_selector can we display the itemtype selector ?
    *
    * @return the itemtype or false if we cannot define it (and we cannot display the selector)
   **/
   function getItemtype($display_selector) {

      if (isset($this->items) && is_array($this->items)) {
         $keys = array_keys($this->items);
         if (count($keys) == 1) {
            return $keys[0];
         }

         if (($display_selector) && (count($keys) > 1)) {
            $itemtypes = array(-1 => Dropdown::EMPTY_VALUE);
            foreach ($keys as $itemtype) {
               $itemtypes[$itemtype] = $itemtype::getTypeName(2);
            }

            _e('Select the type of the item on which applying this action')."<br>\n";

            $rand = Dropdown::showFromArray('specialize_itemtype', $itemtypes);

            echo "<br><br>";

            $params                        = $this->POST;
            $params['specialize_itemtype'] = '__VALUE__';
            Ajax::updateItemOnSelectEvent("dropdown_specialize_itemtype$rand", "show_itemtype$rand",
                                          $_SERVER['REQUEST_URI'], $params);

            echo "<span id='show_itemtype$rand'>&nbsp;</span>\n";
            exit();
         }
      }

     return false;
   }


   /**
    *
   **/
   static function getAddTransferList(array &$actions) {

      if (Session::haveRight('transfer', READ)
          && Session::isMultiEntitiesMode()) {
         $actions[__CLASS__.self::CLASS_ACTION_SEPARATOR.'add_transfer_list'] = _x('button', 'Add to transfer list');
      }

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

         Infocom::getMassiveActionsForItemtype($actions, $itemtype, $is_deleted, $checkitem);

         CommonDBConnexity::getMassiveActionsForItemtype($actions, $itemtype,
                                                         $is_deleted, $checkitem);

         // do not take into account is_deleted if items may be dynamic
         if ($item->maybeDeleted()
             && !$item->useDeletedToLockIfDynamic()) {
            if ($candelete) {
               $actions[$self_pref.'delete'] = _x('button', 'Put in dustbin');
            }
         } else if ($canpurge){
            $actions[$self_pref.'purge'] = _x('button', 'Delete permanently');
         }

         Document::getMassiveActionsForItemtype($actions, $itemtype, $is_deleted, $checkitem);
         Contract::getMassiveActionsForItemtype($actions, $itemtype, $is_deleted, $checkitem);

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

      Lock::getMassiveActionsForItemtype($actions, $itemtype, $is_deleted, $checkitem);

      // Manage forbidden actions
      // TODO: can we delete an action on its name (ie. update instead of MassiveAction::update) ?
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
    * @return nothing: display
   **/
   function showSubForm() {
      global $CFG_GLPI;

      if (!empty($this->processor)) {
         $processor = $this->processor;

         if (!$processor::showMassiveActionsSubForm($this)) {
            $this->showDefaultSubForm();
         }
      } else {
         $input['itemtype'] = $ma->getItemType(true);

         $input = $this->POST;
         foreach ($this->items as $itemtype => $ids) {
            $input['item'][$itemtype] = array_fill_keys(array_keys($ids), 1);
         }
         unset($input['items']);

         $split = explode('_', $this->action);

         if (($split[0] == 'plugin') && isset($split[1])) {
            // Normalized name plugin_name_action
            // Allow hook from any plugin on any (core or plugin) type
            $plugin_input = array('action'   => $this->action,
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
            if (!($item = getItemForItemtype($input['itemtype']))) {
               exit();
            }
            if (!$item->showSpecificMassiveActionsParameters($input)) {
               self::showDefaultSubForm($input['action'], $input);
            }
         }
      }
      $this->addHiddenFields();
   }


    /**
    * Class-specific method used to show the fields to specify the massive action
    *
    * @param $action current action
    * @param $input the inputs (mainly $_POST or $_GET)
    * @param $set_hidden_fields do we have to include the hidden fields ?
    *
    * @return nothing (display only)
   **/
   function showDefaultSubForm() {

      echo Html::submit(__s('Post'), array('name' => 'massiveaction'));

   }


   /**
    * @see CommonDBTM::showMassiveActionsSubForm()
   **/
   static function showMassiveActionsSubForm(MassiveAction $ma) {
      global $CFG_GLPI;

      switch ($ma->getAction()) {
         case 'update':
            $itemtype = $ma->getItemType(true);
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
            return true;

      }
      return false;
   }


   /**
    * Merge the results of different massive actions.
    *
    * @param $global (by reference) the result of the different massive actions
    * @param $local the result to add to the global result
    *
    * @return nothing
   **/
   function mergeProcessResult($result) {

      if (is_array($result)) {
         foreach (array('ok', 'ko', 'noright') as $element) {
            if (isset($result[$element])) {
               $this->results[$element] += $result[$element];
            }
         }

         if (isset($result['messages'])) {
            foreach($result['messages'] as $message) {
               $this->addMessage($message);
            }
         }

         if (isset($result['REDIRECT'])) {
           $this->setRedirect($result['REDIRECT']);
         }

         foreach ($ids as $id => $value) {
            $this->itemDone($itemtype, $id, self::NO_ACTION);
         }
      }
   }


   /**
    * Process the massive actions for all passed items. This a switch between different methods:
    * new system, old one and plugins ...
    *
    * @param $input array of input datas
    *
    * @return an array of results (ok, ko, noright counts, may include REDIRECT field to set REDIRECT page)
   **/
   function process() {

      if ($this->processor != __CLASS__) {
         global $CFG_GLPI;
         Toolbox::logDebug($this->POST);

         echo "<div class='center'><img src='".$CFG_GLPI["root_doc"]."/pics/warning.png' alt='".
            __s('Warning')."'><br><br>";
         echo "<span class='b'>".__('Not re-implemented for the moment !')."</span><br>";
         Html::displayBackLink();
         echo "</div>";
         Html::popFooter();
         exit();
      }

      if (!empty($this->items)) {

         if ($this->processor !== NULL) {
            $processor = $this->processor;
            if (method_exists($processor, 'processMassiveActionsForSeveralItemtype')) {
               $processor::processMassiveActionsForSeveralItemtype($this);
            }
            $this->processForSeveralItemtype();
         } else {
            // Manage mainly for the old plugins ...
         }
      }

      $this->results['redirect'] = $this->redirect;

      // unset $this->identifier to ensure the action won't register in $_SESSION
      unset($this->identifier);

      return $this->results;

      $res = array('ok'      => 0,
                   'ko'      => 0,
                   'noright' => 0);

      if (count($action) == 1) {

         // Actually, there should be only one itemtype in old system version
         foreach ($this->POST['item'] as $itemtype => $data) {
            $this->POST['itemtype'] = $itemtype;
            $this->POST['item'] = array();
            foreach ($data as $key => $value) {
               if ($value == 1) {
                  $this->POST['item'][$key] = 1;
               }
            }

            // Check if action is available for this itemtype
            if ($item = getItemForItemtype($itemtype)) {
               $checkitem = NULL;
               if (isset($this->POST['check_itemtype'])) {
                  if ($checkitem = getItemForItemtype($this->POST['check_itemtype'])) {
                     if (isset($this->POST['check_items_id'])) {
                        $checkitem->getFromDB($this->POST['check_items_id']);
                     }
                  }
               }
               $actions = self::getAllMassiveActions($item, $this->POST['is_deleted'], $checkitem);

               if ($this->POST['specific_action'] || isset($actions[$this->POST['action']])) {
                  $itemtype_res   = '';

                  $split = explode('_', $this->POST["action"]);
                  if ($split[0] == 'plugin' && isset($split[1])) {
                     // Normalized name plugin_name_action
                     // Allow hook from any plugin on any (core or plugin) type
                     $itemtype_res = Plugin::doOneHook($split[1], 'MassiveActionsProcess', $this->POST);

                     //} else if ($plug=isPluginItemType($this->POST["itemtype"])) {
                     // non-normalized name
                     // hook from the plugin defining the type
                     //$itemtype_res = Plugin::doOneHook($plug['plugin'], 'MassiveActionsProcess', $this->POST);
                  } else {
                     $itemtype_res = $item->doSpecificMassiveActions($this->POST);
                  }

                  $this->mergeProcessResult($itemtype_res);

               } else {
                  $res['noright'] += count($this->POST['item']);
               }
            }
         }
      }

      return $res;
   }


   /**
    * Process the specific massive actions for severl itemtypes
    * @return array of the results for the actions
   **/
   function processForSeveralItemtype() {
      $processor = $this->processor;
      foreach ($this->items as $itemtype => $ids) {
         if ($item = getItemForItemtype($itemtype)) {
            $processor::processMassiveActionsForOneItemtype($this, $item, $ids);
         }
      }
   }


   /**
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {
      global $CFG_GLPI;

      $action = $ma->getAction();
      $input  = $ma->getInput();

      switch ($action) {
         case 'delete':
            foreach ($ids as $id) {
               if ($item->can($id, DELETE)) {
                  if ($item->delete(array("id" => $id))) {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $id, self::ACTION_KO);
                     $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                  }
               } else {
                  $ma->itemDone($item->getType(), $id, self::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            }
            break;

         case 'restore' :
            foreach ($ids as $id) {
               if ($item->can($id, PURGE)) {
                  if ($item->restore(array("id" => $id))) {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $id, self::ACTION_KO);
                     $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                  }
               } else {
                  $ma->itemDone($item->getType(), $id, self::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            }
            break;

         case 'purge_item_but_devices':
         case 'purge' :
            foreach ($ids as $id) {
               if ($item->can($id, PURGE)) {
                  $force = 1;
                  // Only mark deletion for
                  if ($item->maybeDeleted()
                      && $item->useDeletedToLockIfDynamic()
                      && $item->isDynamic()) {
                     $force = 0;
                  }
                  $delete_array = array('id' => $id);
                  if ($action == 'purge_item_but_devices') {
                     $delete_array['keep_devices'] = true;
                  }
                  if ($item->delete($delete_array, $force)) {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $id, self::ACTION_KO);
                     $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                  }
               } else {
                  $ma->itemDone($item->getType(), $id, self::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            }
            break;

         case 'update' :
            $input['itemtype'] = $ma->getItemtype(false);
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
                  foreach ($ids as $key) {
                     if ($item->getFromDB($key)) {
                        if (($link_entity_type < 0)
                            || ($link_entity_type == $item->getEntityID())
                            || ($ent->fields["is_recursive"]
                                && in_array($link_entity_type,
                                            getAncestorsOf("glpi_entities",
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
                                 $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                              } else {
                                 $ma->itemDone($item->getType(), $key, self::ACTION_KO);
                                 $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                              }
                           } else {
                              $ma->itemDone($item->getType(), $key, self::ACTION_NORIGHT);
                              $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                           }
                        } else {
                           $ma->itemDone($item->getType(), $key, self::ACTION_KO);
                           $ma->addMessage($item->getErrorMessage(ERROR_COMPAT));
                        }
                     } else {
                        $ma->itemDone($item->getType(), $key, self::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
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

                  foreach ($ids as $key) {
                     if ($item->canEdit($key)
                         && $item->canMassiveAction($action, $input['field'],
                                                    $input[$input["field"]])) {
                        if ((count($link_entity_type) == 0)
                            || in_array($item->fields["entities_id"], $link_entity_type)) {
                           if ($item->update(array('id'   => $key,
                                                   $input["field"]
                                                          => $input[$input["field"]]))) {
                              $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                           } else {
                              $ma->itemDone($item->getType(), $key, self::ACTION_KO);
                              $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                           }
                        } else {
                           $ma->itemDone($item->getType(), $key, self::ACTION_KO);
                           $ma->addMessage($item->getErrorMessage(ERROR_COMPAT));
                        }
                     } else {
                        $ma->itemDone($item->getType(), $key, self::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                     }
                  }
               }
            }
            break;

         case 'add_transfer_list' :
            $itemtype = $item->getType();
            if (!isset($_SESSION['glpitransfer_list'])) {
               $_SESSION['glpitransfer_list'] = array();
            }
            if (!isset($_SESSION['glpitransfer_list'][$itemtype])) {
               $_SESSION['glpitransfer_list'][$itemtype] = array();
            }
            foreach ($ids as $id) {
               $_SESSION['glpitransfer_list'][$itemtype][$id] = $id;
               $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
            }
            $ma->setRedirect($CFG_GLPI['root_doc'].'/front/transfer.action.php');
            break;

      }
   }


   function setRedirect($redirect) {
      $this->redirect = $redirect;
   }


   function addMessage($message) {
      $this->messages[] = $message;
   }


   function itemDone($itemtype, $id, $result) {

      switch ($result) {
         case self::ACTION_OK:
            $this->results['ok'] ++;
            break;
         case self::ACTION_KO:
            $this->results['ko'] ++;
            break;
         case self::ACTION_NORIGHT:
            $this->results['noright'] ++;
            break;
      }

      unset($this->items[$itemtype][$id]);
      if (count($this->items[$itemtype]) == 0) {
         unset($this->items[$itemtype]);
      }

      if (!isset($this->done[$itemtype])) {
         $this->done[$itemtype] = array($id);
      } else {
         $this->done[$itemtype][] = $id;
      }

      // TODO: manage the beautiful progress bar ...

      // TODO: change the timeout !
      if ($this->timer->getTime() > 30) {
         Html::redirect($_SERVER['PHP_SELF'].'?identifier='.$this->identifier);
      }
   }
}

?>
