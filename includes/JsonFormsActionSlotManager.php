<?php

/**
 * This file is part of the MediaWiki extension JsonForms.
 *
 * JsonForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * JsonForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JsonForms.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2026, https://wikisphere.org
 */

class JsonFormsActionSlotManager extends Action {

	/**
	 * @return string lowercase
	 */
	public function getName() {
		return 'slotedit';
	}

	/**
	 * @throws ErrorPageError
	 * @return false
	 */
	public function show() {
		$article = $this->getArticle();
		$output = $this->getOutput();
		$title = $article->getTitle();

		$specialEditData = new SpecialJsonFormsSlotManager();
		$specialEditData->execute( $title->getFullText() );

		return false;
	}

	/**
	 * @return bool
	 */
	public function execute() {
		return true;
	}

}
