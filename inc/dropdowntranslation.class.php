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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}
class DropdownTranslation extends CommonDBChild {
   
   static public $itemtype = 'itemtype';
   static public $items_id = 'items_id';
   public $dohistory       = true;
   static $rightname = 'dropdown';


   static function getTypeName($nb = 0) {
      return _n('Translation', 'Translations', $nb);
   }

   /**
    * @since version 0.85
   **/
   function getForbiddenStandardMassiveAction() {

      $forbidden   = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'update';
      return $forbidden;
   }
   
   /**
    * @see CommonGLPI::getTabNameForItem()
   **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      if (self::canBeTranslated($item)) {
         if ($_SESSION['glpishow_count_on_tabs']) {
            return self::createTabEntry(self::getTypeName(2),
                                        self::getNumberOfTranslationsForItem($item));
         }
         return self::getTypeName(2);
      }
      return '';
   }

   /**
    * @param $item            CommonGLPI object
    * @param $tabnum          (default 1)
    * @param $withtemplate    (default 0)
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      if (DropdownTranslation::canBeTranslated($item)) {
         DropdownTranslation::showTranslations($item);
      }
      return true;
   }

   function prepareInputForAdd($input) {
      if ($this->checkBeforeAddorUpdate($input, true)) {
         return $input;
      } else {
         Session::addMessageAfterRedirect(
            __("There's already a translation for this field in this language"), true, ERROR);
         return false;
      }
   }

   function prepareInputForUpdate($input) {
      if ($this->checkBeforeAddorUpdate($input,false)) {
         return $input;
      } else {
         Session::addMessageAfterRedirect(
            __("There's already a translation for this field in this language"), true, ERROR);
         return false;
      }
   }

   function post_purgeItem() {
      if ($this->fields['field'] == 'name') {
         $translation      = new DropdownTranslation();
         //If last translated field is deleted, then delete also completename record
         if ($this->getNumberOfTranslations($this->fields['itemtype'], $this->fields['items_id'],
                                          $this->fields['field'], $this->fields['language']) == 1) {
            if ($completenames_id = self::getTranslationID($this->fields['itemtype'],
                                                      $this->fields['items_id'],
                                                      'completename', $this->fields['language'])) {
               $translation->delete(array('id' => $completenames_id));
            }
         }
         // If only completename for sons : drop
         foreach (getSonsOf(getTableForItemType($this->fields['itemtype']), $this->fields['items_id']) as $son) {
            if ($this->getNumberOfTranslations($this->fields['itemtype'], $son,
                                             'name', $this->fields['language']) == 0) {
               if ($completenames_id = self::getTranslationID($son, $this->fields['itemtype'],
                                                         'completename', $this->fields['language'])) {
                  $translation->delete(array('id' => $completenames_id));
               }
            }
         }
         // Then update all sons records
         if (!isset($this->input['_no_completename'])) {
            $translation->generateCompletename($this->fields, false);
         }
      }
      return true;
   }

   function post_updateItem($history=1) {
      if (!isset($this->input['_no_completename'])) {
         $translation = new DropdownTranslation();
         $translation->generateCompletename($this->fields, false);
      }
   }

   function post_addItem() {
      if (!isset($this->input['_no_completename'])) {
         $translation = new DropdownTranslation();
         $translation->generateCompletename($this->fields, true);
      }
   }
   
   /**
    * Return the number of translations for a field in a language
    *
    * @param itemtype
    * @param items_id
    * @param field
    * @param language
    *
    * @return the number of translations for this field
    */
   static function getNumberOfTranslations($itemtype, $items_id, $field, $language) {
      return countElementsInTable(getTableForItemType(__CLASS__),
                                  "`itemtype`='".$itemtype."'
                                     AND `items_id`='".$items_id."'
                                        AND `field`='".$field."'
                                           AND `language`='".$language."'");

   }

   /**
    * Return the number of translations for an item
    *
    * @param item
    *
    * @return the number of translations for this item
    */
   static function getNumberOfTranslationsForItem($item) {
      return countElementsInTable(getTableForItemType(__CLASS__),
                                  "`itemtype`='".$item->getType()."'
                                     AND `items_id`='".$item->getID()."'
                                     AND `field` <> 'completename'");

   }

   
   /**
    * Check if a field's translation can be added or updated
    *
    * @param input translation's fields
    * @param add true if a transaltion must be added, false if updated
    *
    * @return true if translation can be added/update, false otherwise
    */
   function checkBeforeAddorUpdate($input, $add = true) {
      global $DB;

      $number = $this->getNumberOfTranslations($input['itemtype'], $input['items_id'],
                                               $input['field'], $input['language']);
      if ($add) {
         return ($number == 0);
      } else {
         return ($number > 0);
      }
   }

   /**
    * Generate completename associated with a tree dropdown
    *
    * @param input array of user values
    * @param add true if translation is added, false if update
    *
    * @return nothing
    */
   function generateCompletename($input, $add = true) {
      //If there's already a completename for this language, get it's ID, otherwise 0
      $completenames_id = self::getTranslationID($input['items_id'], $input['itemtype'],
                                                 'completename',  $input['language']);
      $item = new $input['itemtype']();
      //Completename is used only for tree dropdowns !
      if ($item instanceof CommonTreeDropdown && isset($input['language'])) {
         $item->getFromDB($input['items_id']);

         //Regenerate completename : look for item's ancestors
         $completename = "";

         //Get ancestors as an array
         $cache = getAncestorsOf($item->getTable(), $item->getID());

         if (!empty($cache)) {
            foreach ($cache as $ancestor) {
               if ($completename != ''  && $ancestor != $item->getID()) {
                  $completename.= " > ";
               }
               $completename .= self::getTranslatedValue($ancestor, $input['itemtype'], 'name',
                                                         $input['language']);
            }
         }
         
         if ($completename != '') {
            $completename.= " > ";
         }
         $completename .= self::getTranslatedValue($item->getID(), $input['itemtype'], 'name',
                                                   $input['language']);

         //Add or update completename for this language
         $translation     = new self();
         $tmp = array();
         $tmp['items_id'] = $input['items_id'];
         $tmp['itemtype'] = $input['itemtype'];
         $tmp['field']    = 'completename';
         $tmp['value']    = addslashes($completename);
         $tmp['language'] = $input['language'];
         $tmp['_no_completename'] = true;
         if ($completenames_id) {
            $tmp['id']    = $completenames_id;
            $translation->update($tmp);
         } else {
            $translation->add($tmp);
         }
      }
      
      foreach (getSonsOf($item->getTable(), $item->getID()) as $son) {
         //Try to regenerate translated completename only if a completename already exists
         //for this son
         $completenames_id = self::getTranslationID($son, $input['itemtype'],
                                                    'completename', $input['language']);
         if ($son != $item->getID()) {
            $completename .= " > ".self::getTranslatedValue($son, $input['itemtype'], 'name',
                                                            $input['language']);
            unset($tmp['id']);
            $tmp = array();
            $tmp['items_id'] = $son;
            $tmp['itemtype'] = $input['itemtype'];
            $tmp['field']    = 'completename';
            $tmp['value']    = addslashes($completename);
            $tmp['language'] = $input['language'];
            $tmp['_no_completename'] = true;
            if ($completenames_id) {
               $tmp['id']    = $completenames_id;
               $translation->update($tmp);
            } else {
               $translation->add($tmp);
            }
         }
      }
   }

   /**
    * Display all translated field for an dropdown
    *
    * @param item a Dropdown item
    * @return true;
    */
   static function showTranslations(CommonDropdown $item) {
      global $DB, $CFG_GLPI;

      self::showAddTranslationLink($item);
      
      /// TODO : permit to edit translations
      $canedit = $item->canUpdateItem();
      $query = "SELECT * FROM `".getTableForItemType(__CLASS__)."` " .
               "WHERE `itemtype`='".get_class($item)."'
                   AND `items_id`='".$item->getID()."' AND `field`<>'completename'
                      ORDER BY `language` ASC";
      $results = $DB->query($query);
      if ($DB->numrows($results)) {
         if ($canedit) {
            $rand = mt_rand();
            Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
            $paramsma = array('container' => 'mass'.__CLASS__.$rand);
            Html::showMassiveActions(__CLASS__, $paramsma);
         }
         echo "<div class='center'>";
         echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
         echo "<th colspan='4'>".__("List of translations")."</th></tr>";
         if ($canedit) {
            echo "<th width='10'>";
            Html::checkAllAsCheckbox('mass'.__CLASS__.$rand);
            echo "</th>";
         }
         echo "<th>".__("Language")."</th>";
         echo "<th>".__("Field")."</th>";
         echo "<th>".__("Value")."</th></tr>";
         while ($data = $DB->fetch_array($results)) {
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
               echo "<td class='center'>";
               Html::showMassiveActionCheckBox(__CLASS__, $data["id"]);
               echo "</td>";
            }
            
            echo "<td>";
            if (isset($CFG_GLPI['languages'][$data['language']])) {
               echo $CFG_GLPI['languages'][$data['language']][0];
            }
            echo "</td><td>";
            $searchOption = $item->getSearchOptionByField('name', $data['field']);
            echo $searchOption['name'];
            echo "</td><td>";
            echo $data['value'];
            echo "</td></tr>";
         }
         echo "</table>";
         if ($canedit) {
            $paramsma['ontop'] = false;
            Html::showMassiveActions(__CLASS__, $paramsma);
            Html::closeForm();
         }
      } else {
         echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
         echo "<th class='b'>" . __("No translation found")."</th></tr></table>";
      }
      return true;
   }

   /**
    *
    * Display translation form
    * @since 0.85
    * @param field $ID
    * @param $options
    */
   function showForm($ID = -1, $options = array()) {
      global $CFG_GLPI;

      if (isset($options['parent']) && !empty($options['parent'])) {
         $item = $options['parent'];
      }
      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         $this->fields['id']       = -1;
         $this->fields['itemtype'] = get_class($item);
         $this->fields['items_id'] = $item->getID();
         // Create item
         $item->check(-1 , 'w');
      }
      $this->showFormHeader($options);
      echo "<input type='hidden' name='items_id' value='".$item->getID()."'>";
      echo "<input type='hidden' name='itemtype' value='".get_class($item)."'>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Language')."&nbsp;:</td>";
      echo "<td colspan='3'>";
      $rand = Dropdown::showLanguages("language",
                                      array('display_none' => false,
                                            'value'        => $_SESSION['glpilanguage']));
      
      $params = array('language' => '__VALUE__', 'itemtype' => get_class($item),
                      'items_id' => $item->getID());
      Ajax::updateItemOnSelectEvent("dropdown_language$rand", "span_fields",
                                    $CFG_GLPI["root_doc"]."/ajax/updateTranslationFields.php",
                                    $params);
      echo "</td></tr>";
      echo "<tr class='tab_bg_1'>";
      
      echo "<td>".__('Name')."&nbsp;:</td>";
      echo "<td>";
      echo "<span id='span_fields' name='span_fields'>";
      self::dropdownFields($item, $_SESSION['glpilanguage']);
      echo "</span></td>";
      echo "<td>".__('Value')."&nbsp;:</td>";
      echo "<td><input type='text' name='value' value='' size='50'>";
      echo "</td>";
      echo "</tr></span>\n";
      $this->showFormButtons($options);
      return true;
   }

   /**
    * Display a dropdown with fields that can be translated for an itemtype
    *
    * since 0.85
    * @param item a Dropdown item
    * @param language language to look for translations
    * @param value field which must be selected by default
    *
    * @return the dropdown's random identifier
    */
   static function dropdownFields(CommonDBTM $item, $language = '', $value = '') {
      global $DB;

      $options = array();
      foreach (Search::getOptions(get_class($item)) as $id => $field) {
         //Can only translate name, and fields whose datatype is text or string
         if (isset ($field['field'])
             && $field['field'] == 'name'
             && $field['table'] == getTableForItemType(get_class($item))
             || (isset($field['datatype'])
             && in_array($field['datatype'], array('text', 'string')))) {
            $options[$field['field']] = $field['name'];
         }
      }
      
      $used = array();
      if (!empty($options)) {
         $query = "SELECT `field` FROM `".self::getTable()."`
                  WHERE `itemtype`='".get_class($item)."'
                     AND `items_id`='".$item->getID()."' AND `language`='$language'";
         $results = $DB->query($query);
         if ($DB->numrows($results) > 0) {
            while ($data = $DB->fetch_array($results)) {
               $used[$data['field']] = $data['field'];
            }
         }
      }
      //$used = array();
      return Dropdown::showFromArray('field', $options, array('value' => $value, 'used' => $used));
   }

   /**
    * Display link to add a new translation
    *
    * since 0.85
    * @param item a Dropdown item
    * @return nothing
    */
   static function showAddTranslationLink(CommonDropdown $item) {
      global $CFG_GLPI;

      $rand = mt_rand();
      if ($item->can($item->getID(), 'w')) {

         echo "<script type='text/javascript' >\n";
         echo "function addTranslation" . $item->getID() . "$rand() {\n";
         $params = array('type'           => __CLASS__,
                         'parenttype'     => get_class($item),
                         'foreignkey'     => 'items_id',
                         $item->getForeignKeyField() => $item->getID(),
                         'id'             => -1);
         Ajax::updateItemJsCode("viewtranslation" . $item->getID() . "$rand",
                                $CFG_GLPI["root_doc"]."/ajax/viewsubitem.php", $params);
         echo "};";
         echo "</script>\n";
         echo "<div class='center'>".
              "<a class='vsubmit' href='javascript:addTranslation".$item->getID()."$rand();'>".
               __('Add a new translation')."</a></div><br>";
         echo "<div id='viewtranslation" . $item->getID() . "$rand'></div>\n";
      }
   }

   /**
    * Get translated value for a field in a particular language
    *
    * since 0.85
    * @param ID dropdown item's id
    * @param itemtype dropdown itemtype
    * @param field the field to look for
    * @param language get translation for this language
    * @param value default value for the field
    *
    * @return the translated value of the value in the default language
    */
   static function getTranslatedValue($ID, $itemtype, $field = 'name', $language, $value = '') {
      global $DB;

      //If dropdown translation is globally off, or if this itemtype cannot be translated,
      //then original value should be returned
      $item = new $itemtype();
      if (!$ID || !self::isDropdownTranslationActive() || !self::canBeTranslated($item)) {
         return $value;
      }
      //ID > 0 : dropdown item might be translated !
      if ($ID > 0) {
         //There's at least one translation for this itemtype
         if (self::hasItemtypeATranslation($itemtype)) {
            $query = "SELECT `value` FROM `".self::getTable()."`
                   WHERE `itemtype`='".$itemtype."'
                      AND `items_id`='".$ID."'
                      AND `field`='$field'
                      AND `language`='$language'";
            $result_translations = $DB->query($query);
            //The field is already translated in this language
            if ($DB->numrows($result_translations)) {
               return $DB->result($result_translations, 0, 'value');
            }
         }
         //Get the value coming from the dropdown table
         $query   = "SELECT `$field`
                     FROM `".getTableForItemType($itemtype)."`
                     WHERE `id`='$ID'";
         $results = $DB->query($query);
         if ($DB->numrows($results)) {
            return $DB->result($results, 0, $field);
         }
      }
    
      return "";
   }

   /**
    *
    * Get the id of a translated string
    * @since 0.85
    * @param $ID item id
    * @param $itemtype item type
    * @param $field the field for which the translation is needed
    * @param $language the target language
    * @return the row id or 0 if not translation found
    */
   static function getTranslationID($ID, $itemtype, $field, $language) {
      global $DB;
      $query   = "SELECT `id`
                  FROM `".self::getTable()."`
                  WHERE `itemtype`='".$itemtype."'
                     AND `items_id`='".$ID."'
                        AND `language`='$language' AND `field`='$field'";
      $results = $DB->query($query);
      if ($DB->numrows($results)) {
         return $DB->result($results, 0, 'id');
      } else {
         return 0;
      }

   }

   /**
    * Check if an item can be translated
    * It be translated if translation if globally on and item is an instance of CommonDropdown
    * or CommonTreeDropdown and if translation is enabled for this class
    * @since 0.85
    * @param item the item to check
    *
    * @return true if item can be translated, false otherwise
    */
   static function canBeTranslated(CommonGLPI $item) {
      return self::isDropdownTranslationActive()
         && (($item instanceof CommonDropdown) && $item->maybeTranslated());
   }

  
   /**
    * Is dropdown item translation functionnality active
    * since 0.85
    * @return true if active, false if not
    */
   static function isDropdownTranslationActive() {
      global $CFG_GLPI;
      return $CFG_GLPI['translate_dropdowns'];
   }

   /**
    *
    * Get a translation for a value
    * @since 0.85
    * @param $itemtype itemtype
    * @param $field field to query
    * @param $value value to translate
    * @return the value translated if a translation is available, or the same value if not
    */
   static function getTranslationByName($itemtype, $field, $value) {
      global $DB;

      $query   = "SELECT `id`
                  FROM `".getTableForItemType($itemtype)."`
                  WHERE `$field`='".Toolbox::addslashes_deep($value)."'";
      $results = $DB->query($query);
      if ($DB->numrows($results) > 0) {
         return self::getTranslatedValue($DB->result($results, 0, 'id'), $itemtype, $field,
                                         $_SESSION['glpilanguage'], $value);
      } else {
         return $value;
      }
   }

   /**
    *
    * Check if there's at least one translation for this itemtype
    * @since 0.85
    * @param $itemtype itemtype to check
    * @return true if there's at least one translation, otherwise false
    */
   static function hasItemtypeATranslation($itemtype) {
      return countElementsInTable(self::getTable(), "`itemtype`='$itemtype'");
   }

   /**
    *
    * Get available translations for a language
    * @param $language language
    * @since 0.85
    * @return array of table / field translated item
    */
   static function getAvailableTranslations($language) {
      global $DB;
      $tab = array();
      if (self::isDropdownTranslationActive()) {
         $query   = "SELECT DISTINCT `itemtype`, `field`
                     FROM `".self::getTable()."`
                     WHERE `language`='$language'";
         foreach ($DB->request($query) as $data) {
            $tab[$data['itemtype']][$data['field']] = $data['field'];
         }
      }
      return $tab;
   }
}
?>