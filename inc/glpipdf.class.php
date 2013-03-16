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

require_once(GLPI_TCPDF_DIR.'/tcpdf.php');

/**
 *  GLPIPDF class extends TCPDF
 *
 *  @since version 0.85
**/
class GLPIPDF extends TCPDF {


   /**
    * Page header
    *
    * @see TCPDF::Header()
   */
   public function Header() {
      // Title
      $this->Cell(0, 15, $this->title, 0, false, 'C', 0, '', 0, false, 'M', 'M');
   }


   /**
    * Page footer
    *
    * @see TCPDF::Footer()
   **/
   public function Footer() {
      // Position at 15 mm from bottom
      $this->SetY(-15);
      $text = "GLPI PDF export - ".Html::convDate(date("Y-m-d")).
              " - ".$this->getAliasNumPage()."/".$this->getAliasNbPages();

      // Page number
      $this->Cell(0, 10, $text, 0, false, 'C', 0, '', 0, false, 'T', 'M');
   }

}
?>