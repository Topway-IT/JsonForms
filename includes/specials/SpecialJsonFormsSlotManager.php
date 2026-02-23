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

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Revision\SlotRecord;

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */
class SpecialJsonFormsSlotManager extends SpecialPage {

	/** @var user */
	public $user;

	/** @var Request */
	public $request;

	/** @var string */
	public $par;

	/** @var int */
	public $namespace;

	/** @var string */
	public $localTitle;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$listed = true;
		parent::__construct( 'JsonFormsSlotManager', '', $listed );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// $this->requireLogin();

		$this->par = $par;

		$this->setHeaders();
		$this->outputHeader();

		$user = $this->getUser();

		// if ( !$user->isAllowed( 'jsonforms-canmanageschemas' )
		// 	&& !$user->isAllowed( 'jsonforms-caneditschema' ) ) {
		// 	$this->displayRestrictionError();
		// 	return;
		// }

		$out = $this->getOutput();

		$out->addModuleStyles( 'mediawiki.special' );
		$this->addHelpLink( 'Extension:JsonForms' );

		$request = $this->getRequest();
		$this->request = $request;
		$this->user = $user;

		$out->enableOOUI();

		$jsonForm = file_get_contents(  __DIR__ . '/../schemas/SimpleFormUI.json');
		$jsonForm = json_decode( $jsonForm, true );

		$innerSchema = file_get_contents(  __DIR__ . '/../schemas/SlotManager.json');
		$innerSchema = json_decode( $innerSchema, true );

		// $jsonForm['properties']['form']['options']['input']['config']['schema'] = 'JsonSchema:ArticleForm d';
		$jsonForm['properties']['form']['options']['input']['config']['schema'] = $innerSchema;

		$editTitle = null;
		if ( !empty( $par ) ) {
			$editTitle = TitleClass::newFromText( $par );
		}

		$startValInnerForm = [];
		$editPage = null;
		if ( $editTitle && $editTitle->isKnown() ) {	
			$jsonForm['properties']['form']['options']['input']['config']['disableFields'] = [ 'title' ];

			$editPage = $editTitle->getFullText();
			$wikiPage = \JsonForms::getWikiPage( $editTitle );
			$metadata = \JsonForms::getMetadata( $wikiPage );

			// $startVal['categories'] = \JsonForms::getCategories($editTitle);
			if ( isset( $metadata['categories'] ) ) {
				$startValInnerForm['categories'] = (array)$metadata['categories'];
			}

			$slots = \JsonForms::getSlots( $wikiPage );

			$setStartVal = static function( &$val, $role, $slot ) use ( $metadata, $wikiPage, $editTitle ) {
				$val['content_model'] = $slot->getContent()->getContentHandler()->getModelID();
				if ( isset( $metadata['slots'][$role]['editor'] ) ) {
					$val['editor'] = $metadata['slots'][$role]['editor'];
				}
				$val['content'] = \JsonForms::getSlotContent( $wikiPage, $role );
			};

			$startValInnerForm['title'] = $par;
			
			if ( array_key_exists( SlotRecord::MAIN, $slots ) ) {
				$setStartVal( $startValInnerForm, SlotRecord::MAIN, $slots[SlotRecord::MAIN] );
				unset( $slots[SlotRecord::MAIN] );
			}

			foreach ( $slots as $role => $slot ) {
				if ( $role === SLOT_ROLE_JSONFORMS_METADATA ) {
					continue;
				}
				$val = [];
				$setStartVal( $val, $role, $slot );
				$startValInnerForm[$role] = $val;
			}
		}
		
		$startVal = [
			'form' => json_encode( $startValInnerForm )
		];

		$formData = [
			'schema' => $jsonForm,
			'name' => 'SlotManager',
			'editorOptions' => 'MediaWiki:DefaultEditorOptions',
			'editorScript'=> 'MediaWiki:DefaultEditorScript',
			'startval'=> $startVal,
			'metadata'=> $metadata,
			'editPage' => $editPage,
		];

		$formData = \JsonForms::prepareFormData( $out, $formData );

		$res_ = \JsonForms::getJsonFormHtml( $formData, [
			'width' => '100%'
		] );

		if ( !$res_->ok ) {
			return $this->printError( $out, $res_->error );
		}

		$html = $res_->value;

		// $html = \JsonForms::getJsonForm( $out, $formName, $data, $errorMessage );
		$out->addModules( 'ext.JsonForms.slotManager' );
		
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
