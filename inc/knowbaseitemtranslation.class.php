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

// CLASSE KnowbaseItemTranslation

class KnowbaseItemTranslation extends CommonDBChild {
   static public $itemtype = 'KnowbaseItem';
   static public $items_id = 'knowbaseitems_id';
   public $dohistory       = true;

   static $rightname   = 'knowbase';
   
   static function getTypeName($nb = 0) {
      return _n('Translation', 'Translations', $nb);
   }

   /**
    * @see CommonGLPI::getTabNameForItem()
   **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      if (self::canBeTranslated($item)) {
         return self::getTypeName();
      }
      return '';
   }

   /**
    * @param $item            CommonGLPI object
    * @param $tabnum          (default 1)
    * @param $withtemplate    (default 0)
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      if (self::canBeTranslated($item)) {
         self::showTranslations($item);
      }
      return true;
   }

   /**
    * Display all translated field for an KnowbaseItem
    *
    * @param item a KnowbaseItem item
    * @return true;
    */
   static function showTranslations(KnowbaseItem $item) {
      global $DB, $CFG_GLPI;

      ///TODO : unable to edit translations : permit to do it !
      ///TODO : show content in tooltip
      ///TODO : add several translations for the same language is possible ! do not permit it
      
      
      $obj = new self;
      $found = $obj->find("`knowbaseitems_id`='".$item->getID()."'", "`language` ASC");
      if (count($found) > 0) {
          echo "<form action='".Toolbox::getItemTypeFormURL(__CLASS__).
            "' method='post' name='translation_form'
                id='translation_form'>";
         echo "<div class='center'>";
         echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
         echo "<th colspan='4'>".__("List of translations")."</th></tr>";
         echo "<th>&nbsp;</th>";
         echo "<th>".__("Language")."</th>";
         echo "<th>".__("Subject")."</th>";
         foreach($found as $data) {
            echo "<tr class='tab_bg_1'><td class='center' width='10'>";
            if (isset ($_GET["select"]) && $_GET["select"] == "all") {
               $sel = "checked";
            }
            $sel ="";
            echo "<input type='checkbox' name='item[" . $data["id"] . "]' value='1' $sel>";
            echo "</td><td>";
            if (isset($CFG_GLPI['languages'][$data['language']])) {
               echo $CFG_GLPI['languages'][$data['language']][0];
            }
            echo "</td><td>";
            echo  $data["name"];
            echo "</td></tr>";
         }
         echo "</table>";
         Html::openArrowMassives("translation_form", true);
         Html::closeArrowMassives(array('delete_translation' => _sx('button', 'Delete')));
         Html::closeForm();
      } else {
         echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
         echo "<th class='b'>" . __("No translation found")."</th></tr></table>";
      }

      self::showAddTranslationLink($item);
      return true;
   }

   /**
    * Display link to add a new translation
    *
    * since 0.85
    * @param item a KnowbaseItem item
    * @return nothing
    */
   static function showAddTranslationLink(KnowbaseItem $item) {
      global $CFG_GLPI;

      $rand = mt_rand();
      if ($item->can($item->getID(), 'w')) {
         echo "<div id='viewtranslation" . $item->getID() . "$rand'></div>\n";

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
              "<a href='javascript:addTranslation".$item->getID()."$rand();'>";
         echo __("Add a new translation")."</a></div><br>\n";
      }
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
         $this->fields['knowbaseitems_id'] = $item->getID();
         // Create item
         $item->check(-1 , 'w');
      }
      $this->showFormHeader($options);
      echo "<input type='hidden' name='knowbaseitems_id' value='".$item->getID()."'>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Language')."&nbsp;:</td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='3'>";
      $rand = Dropdown::showLanguages("language",
                                      array('display_none' => false,
                                            'value'        => $_SESSION['glpilanguage']));


      /// TODO : Do not understand that it do here
      $params = array('language' => '__VALUE__', 'itemtype' => get_class($item),
                      'knowbaseitems_id' => $item->getID());
      Ajax::updateItemOnSelectEvent("dropdown_language$rand", "span_fields",
                                    $CFG_GLPI["root_doc"]."/ajax/updateTranslationFields.php",
                                    $params);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>";

      echo "<div id='contenukb'>";
      echo "<fieldset>";
      echo "<legend>".__('Subject')."</legend>";
      echo "<div class='center'>";
      echo "<textarea cols='80' rows='2' name='name'></textarea>";
      echo "</div></fieldset>";

      Html::initEditorSystem('answer');
      echo "<fieldset>";
      echo "<legend>".__('Content')."</legend>";
      echo "<div class='center spaced'>";
      echo "<textarea cols='80' rows='30' id='answer' name='answer'></textarea>";
      echo "</div></fieldset>";

      echo "</td></tr>\n";


      $this->showFormButtons($options);
      return true;
   }


   /**
    *
    * Get a translation for a value
    * @since 0.85
    * @param $item item to translate
    * @param $field field to return
    * @return the field translated if a translation is available, or the original field if not
    */
   static function getTranslatedValue(KnowbaseItem $item, $field = "name") {
      global $DB;

      $obj = new self;
      $found = $obj->find("`knowbaseitems_id` = '".$item->getID().
                          "' AND `language` = '".$_SESSION['glpilanguage']."'");

      if (count($found) > 0 && in_array($field, array('name', 'answer'))) {
         $first = array_shift($found);
         return $first[$field];
      } else {
         return $item->fields[$field];
      }
   }


   /**
    * Is kb item translation functionnality active
    * since 0.85
    * @return true if active, false if not
    */
   static function isKbTranslationActive() {
      global $CFG_GLPI;
      return $CFG_GLPI['translate_kb'];
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
      return self::isKbTranslationActive()
         && $item instanceof KnowbaseItem;
   }
}