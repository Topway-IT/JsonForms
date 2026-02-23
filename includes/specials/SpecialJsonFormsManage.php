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

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */
class SpecialJsonFormsManage extends SpecialPage {

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
		$listed = false;
		parent::__construct( 'JsonFormsManage', '', $listed );
	}

	/**
	 * @return string|Message
	 */
	public function getDescription() {
		$msg = $this->msg( 'jsonformsbrowse' . strtolower( (string)$this->par ) );
		if ( version_compare( MW_VERSION, '1.40', '>' ) ) {
			return $msg;
		}
		return $msg->text();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// $this->requireLogin();

		$allowedItems = [ 'Forms', 'Schemas' ];

		if ( !in_array( $par, $allowedItems ) ) {
			$this->displayRestrictionError();
			return;
		}

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

		$this->addJsConfigVars( $out );

		$out->enableOOUI();

		$this->addNavigationLinks( $par );

		$out->addWikiMsg( 'jsonforms-special-browse-' . strtolower( $this->par ) . '-description' );

		$this->localTitle = SpecialPage::getTitleFor( 'JsonFormsManage', $par );

		$action = $this->getRequest()->getVal( 'action' );

		$jsonForm = file_get_contents(  __DIR__ . '/../schemas/PageFormUI.json');
		$jsonForm = json_decode( $jsonForm, true );
		
		// $formDescriptor = file_get_contents(  __DIR__ . '/../schemas/formDescriptors/EditForm.json');
		$formDescriptor = file_get_contents(  __DIR__ . '/../../data/JsonForm/Default.json');
		$formDescriptor = json_decode( $formDescriptor, true );
		$formDescriptor['edit_categories'] = false;
		// $formDescriptor['width'] = '800px';
		$formDescriptor['return_url'] = $this->localTitle->getLocalURL();
		$formDescriptor['create_only_fields'] = [
			'name',
			// 'edit_page'
		];

		if ( !empty( $formDescriptor['edit_page'] ) ) {
			$jsonForm['properties']['form']['properties']['form']['options']['input']['config']['disableFields'] = $formDescriptor['create_only_fields'];
		}

		$pageid = $this->getRequest()->getVal( 'pageid' );
		$startVal = [];
		if ( $pageid ) {
			$title = TitleClass::newFromID( $pageid );
			if ( !$title ) {
				return $this->printError( $out, 'jsonforms-special-browse-error-invalid-article' );
			}

			$formDescriptor['edit_page'] = $title->getFullText();

			$text = \JsonForms::getArticleContent( $title );
			if ( $text ) {
				$startVal = json_decode( $text, true );
			}
		}

		$item = null;
		switch( strtolower($this->par ) ) {
			case 'forms':
				$item = 'form';
				$formDescriptor['pagename_formula'] = 'JsonForm:{{name}}';
				$innerSchema = file_get_contents(  __DIR__ . '/../schemas/CreatePageForm.json');
				$innerSchema = json_decode( $innerSchema, true );
				$jsonForm['properties']['form']['properties']['form']['options']['input']['config']['schema'] = $innerSchema;
				break;

			case 'schemas':
				$message = new \OOUI\MessageWidget( [
					'type' => 'error',
					'label' =>  new \OOUI\HtmlSnippet( 'This feature is under development! Create/edit <b><a target="_blank" href="https://json-schema.org/draft-07">draft-07 (2018)</a></b> schemas manually or via an IA assistant until it\'s completed!' )
				] );
				$out->addHTML( $message );
				$out->addHTML( '<br />' );
		
				$item = 'schema';
				$formDescriptor['pagename_formula'] = 'JsonSchema:{{name}}';				
				$innerSchema = file_get_contents(  __DIR__ . '/../schemas/MetaSchema.json');
				$innerSchema = json_decode( $innerSchema, true );
				$jsonForm['properties']['form']['properties']['form']['options']['input']['config']['schema'] = $innerSchema;
				break;
		}
		
		switch ( $action ) {
			case 'edit':				
				$formData = [
					'schema' => $jsonForm,
					'name' => 'PageForm',
					'editorOptions' => 'MediaWiki:DefaultEditorOptions',
					'editorScript'=> 'MediaWiki:DefaultEditorScript',
					'startval'=> [
						'form' => [
							'form' => $startVal
						]
					],
					'formDescriptor' => $formDescriptor
				];

				$formData = \JsonForms::prepareFormData( $out, $formData );

				$data = [];
				$res_ = \JsonForms::getJsonFormHtml( $formData, [
					'width' => '100%'
				] );

				if ( !$res_->ok ) {
					 return $this->printError( $out, $res_->error );
					// return $this->printError( $out, 'jsonforms-special-browse-error-invalid-form' );
				}

				$html = $res_->value;

				// $html = \JsonForms::getJsonForm( $out, $formName, $data, $errorMessage );
				$out->addModules( 'ext.JsonForms.pageForms' );

				\JsonForms::addJsConfigVars( $out );

				$out->addHTML( $html );
				break;

			default:
				$layout = new OOUI\PanelLayout(
					[ 'id' => 'jsonforms-panel-layout', 'expanded' => false, 'padded' => false, 'framed' => false ]
				);

				// @TODO remove condition as soon as the metaschema editor works
				// if ( $item === 'form' ) {
					$layout->appendContent(
						new OOUI\ButtonWidget(
							[
								'href' => wfAppendQuery( $this->localTitle->getLocalURL(), [ 'action' => 'edit' ] ),
								'label' => $this->msg( 'jsonforms-manage-form-button-add-' . $item )->text(),
								'infusable' => true,
								'flags' => [ 'progressive', 'primary' ],
							]
						)
					);

					$out->addHTML( $layout );
				// }

				$options = $this->showOptions( $request );

				if ( $options ) {
					$out->addHTML( '<br />' );
					$out->addHTML( $options );
					$out->addHTML( '<br />' );
				}
				
				$class = null;
				switch ( $item ) {
					case 'schema':
						$this->namespace = NS_JSONSCHEMA;
						$class = 'ManagePager';
						break;

					case 'form':
					default:
						$this->namespace = NS_JSONFORM;
						$class = 'ManagePager';
				}

				$class = "MediaWiki\\Extension\\JsonForms\\Specials\\$class";
				$pager = new $class(
					$this,
					$request,
					$this->getLinkRenderer()
				);

				if ( $pager->getNumRows() ) {
					$parserOptions = ( version_compare( MW_VERSION, '1.44', '>=' ) ?
						ParserOptions::newFromContext( $this->getContext() ) :
						[]
					);
					$out->addParserOutputContent( $pager->getFullOutput(), $parserOptions );
			
				} else {
					$out->addWikiMsg( 'jsonforms-special-browse-table-empty' );
				}
		}
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
	 * @param Output $out
	 */
	protected function addJsConfigVars( $out ) {
		$context = $this->getContext();
		$out->addJsConfigVars( [] );
	}

	/**
	 * @see AbuseFilterSpecialPage
	 * @param string $pageType
	 */
	protected function addNavigationLinks( $pageType ) {
		$linkDefs = [
			'forms' => 'JsonFormsManage/Forms',
			'schemas' => 'JsonFormsManage/Schemas',
		];

		$links = [];

		foreach ( $linkDefs as $name => $page ) {
			// Give grep a chance to find the usages:
			// abusefilter-topnav-home, abusefilter-topnav-recentchanges, abusefilter-topnav-test,
			// abusefilter-topnav-log, abusefilter-topnav-tools, abusefilter-topnav-examine
			$msgName = "jsonformsbrowse$name";

			$msg = $this->msg( $msgName )->parse();

			if ( $name === $pageType ) {
				$links[] = Xml::tags( 'strong', null, $msg );
			} else {
				$links[] = $this->getLinkRenderer()->makeLink(
					new TitleValue( NS_SPECIAL, $page ),
					new HtmlArmor( $msg )
				);
			}
		}

		$linkStr = $this->msg( 'parentheses' )
			->rawParams( $this->getLanguage()->pipeList( $links ) )
			->text();
		$linkStr = $this->msg( 'jsonformsbrowsedata-topnav' )->parse() . " $linkStr";

		$linkStr = Xml::tags( 'div', [ 'class' => 'mw-jsonforms-browsedata-navigation' ], $linkStr );

		$this->getOutput()->setSubtitle( $linkStr );
	}

	/**
	 * @param Request $request
	 * @return string
	 */
	protected function showOptions( $request ) {
		$formDescriptor = [];

		switch ( $this->par ) {
			case 'Schemas':
			case 'Forms':
			default:
				$schemaname = $request->getVal( 'schemaname' );
				$formDescriptor['schema'] = [
					'label-message' => 'jsonforms-special-browse-form-search-schema-label',
					'type' => 'select',
					'name' => 'schemaname',
					'type' => 'title',
					'namespace' => $this->namespace,
					'relative' => true,
					'required' => false,

					// @fixme this has no effect, create a custom widget
					'limit' => 20,
					'help-message' => 'jsonforms-special-browse-form-search-schema-help',
					'default' => $schemaname ?? null,
				];

		}

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );

		$htmlForm
			->setMethod( 'get' )
			->setWrapperLegendMsg( 'jsonforms-special-browse-form-search-legend' )
			->setSubmitText( $this->msg( 'jsonforms-special-browse-form-search-submit' )->text() );

		return $htmlForm->prepareForm()->getHTML( false );
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'jsonforms';
	}
}
