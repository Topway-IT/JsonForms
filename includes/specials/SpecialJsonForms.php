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
 * @copyright Copyright ©2021-2024, https://wikisphere.org
 */

use MediaWiki\Extension\JsonForms\Aliases\Html as HtmlClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;


class SpecialJsonForms extends SpecialPage {

	/** @inheritDoc */
	public function __construct() {
		$listed = true;

		// https://www.mediawiki.org/wiki/Manual:Special_pages
		parent::__construct( 'JsonForms', '', $listed );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( $this->getRobotPolicy() );

		$user = $this->getUser();
		$this->setHeaders();
		$this->outputHeader();

		$securityLevel = $this->getLoginSecurityLevel();

		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			$this->displayRestrictionError();
			return;
		}

		if ( !$user->isAllowed( 'edit' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->addHelpLink( 'Extension:JsonForms' );

		$out->addModules( 'ext.JsonForms.newArticle' );
		// $context = RequestContext::getMain();

		$jsonForm = \JsonForms::getSourceSchema( 'NewArticle', 'JsonSchema/Core' );
		$jsonForm = \JsonForms::processSchema( $out, $jsonForm );

		$formData = [
			'schema' => $jsonForm,
			'name' => 'SlotManager',
			'editorOptions' => 'MediaWiki:DefaultEditorOptions',
			'editorScript'=> 'MediaWiki:DefaultEditorScript',
		];

		if ( !empty( $startValInnerForm ) ) {
			$formData['startval'] = [
				'editor' => json_encode( $startValInnerForm )
			];
		}

		$formData = \JsonForms::prepareFormData( $out, $formData );

		$res_ = \JsonForms::getJsonFormHtml( $formData, [
			'width' => '100%'
		] );

		if ( !$res_->ok ) {
			return $this->printError( $out, $res_->error );
		}

		$html = $res_->value;

		\JsonForms::addJsConfigVars( $out );
		
		$out->addHTML( $html );
	}

	/**
	 * @param Output $out
	 * @param string $msg
	 */
	private function printError( $out, $msg ) {
		$out->addHTML( new \OOUI\MessageWidget( [
			'type' => 'error',
			'label' => new \OOUI\HtmlSnippet( $this->msg( $msg )->parse() )
		] ) );
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'jsonforms';
	}

}
