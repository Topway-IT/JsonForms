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
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2025-2026, https://wikisphere.org
 */

use MediaWiki\Content\JsonContent;
use MediaWiki\Extension\JsonForms\Aliases\Html as HtmlClass;
use MediaWiki\Extension\JsonForms\Aliases\Linker as LinkerClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\JsonForms\FormParameters;
use MediaWiki\Extension\JsonForms\QueryLinkParameters;
use MediaWiki\Extension\JsonForms\ResultWrapper;
use MediaWiki\Extension\JsonForms\SlotHelper;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

if ( is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
	include_once __DIR__ . '/../vendor/autoload.php';
}


class JsonForms {

	/** @var array */
	private static $slotsCache = [];

	/** @var int */
	public static $queryLimit = 500;

	public static function initialize() {
	}

	/**
	 * @param Parser $parser
	 * @param mixed ...$argv
	 * @return array
	 */
	public static function parserFunctionForm( Parser $parser, ...$argv ) {
		$parserOutput = $parser->getOutput();
		$parserOutput->setExtensionData( 'jsonforms', true );

		$functionReturn = static function( $value ) {
			return [
				$value,
				'noparse' => true,
				'isHTML' => true
			];
		};

		if ( empty( $argv[0] ) ) {
			// echo 'enter a form name';
			return $functionReturn(self::printError( $parserOutput, 'jsonforms-parserfunction-error-no-form-name'));
		}

		$function = 'form';
		$formName = $argv[0];
		$data = [];
		$errorMessage = null;

		$formSchema = file_get_contents(  __DIR__ . '/schemas/CreatePageForm.json');
		$formSchema = json_decode( $formSchema, true );

		$parameters = new FormParameters($argv, $formSchema );

		$named = $parameters->getOptions();
		// $unnamed = $parameters->getValues();
		// $query = $parameters->getQuery();	

		$context = RequestContext::getMain();
		$output = $context->getOutput();

		// $html = self::getJsonForm( $parserOutput, $formName, $data, $errorMessage );

		if ( empty( $formName ) ) {
			return $functionReturn(self::printError( $parserOutput, 'jsonforms-parserfunction-error-no-form-name' ) );
		}

		$formDescriptor = self::getJsonSchema( 'JsonForm:' . $formName  );
		if ( empty( $formDescriptor ) ) {
			return $functionReturn(self::printError( $parserOutput, 'jsonforms-parserfunction-error-no-form' ) );
		}

		// formDescriptor prevails over default parameters but
		// inline parameters prevail over formDescriptor
		foreach ( $named as $k => $v ) {
			if (
				!array_key_exists( $k, $formDescriptor ) ||
				in_array($k, $parameters->initialKnown )
			)  {
				$formDescriptor[$k] = $v;
			}
		}

		$jsonForm = file_get_contents(  __DIR__ . '/schemas/PageFormUI.json');
		$jsonForm = json_decode( $jsonForm, true );

		if ( !empty( $formDescriptor['schema'] ) ) {
			$jsonForm['properties']['form']['properties']['form']['options']['input']['config']['schema'] = 'JsonSchema:' . $formDescriptor['schema'];

			if ( !empty( $formDescriptor['edit_page'] ) && is_array( $formDescriptor['create_only_fields'] ) ) {
				$jsonForm['properties']['form']['properties']['form']['options']['input']['config']['disableFields'] = $formDescriptor['create_only_fields'];
			}
		}

		$startVal = [];
		// or ParserOptions::newFromAnon()
		if ( !empty( $formDescriptor['edit_page'] ) ) {
			$editTitle = TitleClass::newFromText( $formDescriptor['edit_page'] );
			
			if ( $editTitle && $editTitle->isKnown() ) {
				$wikiPage = self::getWikiPage( $editTitle );

				if ( !empty( $formDescriptor['schema'] ) ) {
					$metadata = self::getSlotContent( $wikiPage, SLOT_ROLE_JSONFORMS_METADATA );

					if ( $metadata && is_array( $metadata['slots'] ) ) {
						// *** or use $renderedRevision->getSlotParserOutput( $role )
						foreach ( $metadata['schemas'] as $role => $schema ) {
							if ( $schema === $formDescriptor['schema'] ) {
								$content = self::getSlotContent( $wikiPage, $role );
								if ( $content ) {
									$startVal['form']['form'] = $content;
								}
								break;
							}
						}
					}
				}

				if ( $formDescriptor['edit_categories'] === true ) {
					$categories = self::getNonAnnotatedCategories( $editTitle );
					$startVal['form']['options']['categories'] = $categories;
				}

				// @TODO add page content
				// if ( $formDescriptor['edit_main_slot_content'] === true ) {
			}
		}
// print_r($startVal);
// exit;
		$formData = [
			'schema' => $jsonForm,
			'name' => 'PageForm',
			'editorOptions' => 'MediaWiki:DefaultEditorOptions',
			'editorScript'=> 'MediaWiki:DefaultEditorScript',
			'startval'=> $startVal,
			'formDescriptor' => $formDescriptor
		];

		$formData = \JsonForms::prepareFormData( $output, $formData );

		$data = [];
		$res_ = \JsonForms::getJsonFormHtml( $formData );

		if ( !$res_->ok ) {
			return $functionReturn(self::printError( $parserOutput, $res_->error ),);
		}

		return $functionReturn($res_->value );
		
/*
		$parser->addTrackingCategory( "jsonforms-trackingcategory-parserfunction-$function" );
		$title = $parser->getTitle();
		
		$spinner = HtmlClass::rawElement(
			'div',
			[ 'class' => 'mw-rcfilters-spinner mw-rcfilters-spinner-inline', 'style' => 'display:none' ],
			HtmlClass::element(
				'div',
				[ 'class' => 'mw-rcfilters-spinner-bounce' ]
			)
		);

		$errorMessage = '';
		return [
			$errorMessage . HtmlClass::rawElement(
				'div',
				[
					'class' => 'VisualDataFormItem VisualDataFormWrapper',
					'data-form-data' => json_encode( $formData )
				],
				wfMessage( "visualdata-parserfunction-$function-placeholder" )->text()
			),
			'noparse' => true,
			'isHTML' => true
		];
*/
	}

	/**
	 * @param Context $context
	 * @param string $titleStr
	 * @return mixed
	 */
	public static function getArticleMetadata( $context, $titleStr ) {
		$parserOptions = ParserOptions::newFromContext( $context );
		$title = TitleClass::newFromText( $titleStr );
		$wikiPage = self::getWikiPage( $title );
		$parserOutput = $wikiPage->getParserOutput( $parserOptions );
		return $parserOutput->getExtensionData( 'JsonForms' );
	}

	/**
	 * @param Context $context
	 * @param WikiPage $wikiPage
	 * @return mixed
	 */
	public static function getMetadata( $context, $wikiPage ) {
		return self::getSlotContent( $wikiPage, SLOT_ROLE_JSONFORMS_METADATA );
	}

	/**
	 * @see https://www.php.net/manual/en/function.array-merge-recursive.php
	 * @param array $arr1
	 * @param array $arr2
	 * @param bool $replaceLists false
	 * @return array
	 */
	public static function array_merge_recursive( $arr1, $arr2, $replaceLists = false ) {
		$ret = $arr1;

		if ( self::isList( $arr1 ) && self::isList( $arr2 ) ) {
			if ( $replaceLists ) {
				return $arr2;
			}

			// append values to list
			foreach ( $arr2 as $value ) {
				$ret[] = $value;
			}

			return $ret;
		}

		foreach ( $arr2 as $key => $value ) {
			if ( is_array( $value ) && isset( $ret[$key] )
				&& is_array( $ret[$key] )
			) {
				$ret[$key] = self::array_merge_recursive( $ret[$key], $value, $replaceLists );
			} else {
				$ret[$key] = $value;
			}
		}

		return $ret;
	}

	/**
	 * @param array $arr
	 * @see https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
	 * @return bool
	 */
	public static function isList( $arr ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}
		if ( $arr === [] ) {
			return true;
		}
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/**
	 * @return array
	 */
	public static function getDefaultTrackingCategories() {
		$services = MediaWikiServices::getInstance();
		if ( method_exists( $services, 'getTrackingCategories' ) ) {
			$trackingCategoriesClass = $services->getTrackingCategories();
			$trackingCategories = $trackingCategoriesClass->getTrackingCategories();

		} else {
			$context = RequestContext::getMain();
			$config = $context->getConfig();
			$trackingCategories = new TrackingCategories( $config );
		}

		$ret = [];
		foreach ( $trackingCategories as $value ) {
			foreach ( $value['cats'] as $title_ ) {
				$ret[] = $title_->getText();
			}
		}
		return $ret;
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return array
	 */
	public static function getTrackingCategories( $title ) {
		$ret = self::getCategories( $title );

		$trackingCategories = self::getDefaultTrackingCategories();
		foreach ( $ret as $key => $category ) {
			if ( !in_array( $category, $trackingCategories ) ) {
				unset( $ret[$key] );
			}
		}

		return array_values( $ret );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return array
	 */
	public static function getNonAnnotatedCategories( $title ) {
		$ret = self::getCategories( $title );
		
		// remove tracking categories
		$trackingCategories = self::getTrackingCategories( $title );
		foreach ( $ret as $key => $category ) {
			if ( in_array( $category, $trackingCategories ) ) {
				unset( $ret[$key] );
			}
		}

		// remove categories annotated on the page,
		// since we will not tinker with wikitext
		// necessary only if content model is wikitext
		$wikiPage = self::getWikiPage( $title );
		if ( $wikiPage->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
			// $jsonData = self::getJsonData( $title );
			$context = RequestContext::getMain();
			$data = self::getMetadata( $context, $wikiPage );
			if ( $data && !empty( $data['categories'] ) ) {
				foreach ( $ret as $key => $category ) {
					if ( !in_array( $category, $data['categories'] ) ) {
						unset( $ret[$key] );
					}
				}
			}
		}

		return array_values( $ret );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @param int $mode 2
	 * @return array
	 */
	public static function getCategories( $title ) {
		if ( !$title || !$title->isKnown() ) {
			return [];
		}

		$wikiPage = self::getWikiPage( $title );

		// a special page
		if ( !$wikiPage ) {
			return [];
		}

		$arr = $wikiPage->getCategories();
		$ret = [];
		foreach ( $arr as $title_ ) {
			$ret[] = $title_->getText();
		}
		
		return $ret;
	}

	/**
	 * @param MediaWiki\Parser\ParserOutput $parserOutput
	 * @param string $msg
	 * @return string
	 */
	public static function printError( $parserOutput, $msg ) {
		$parserOutput->setEnableOOUI();
		\OOUI\Theme::setSingleton( new \OOUI\WikimediaUITheme() );

		return new \OOUI\MessageWidget( [
			'type' => 'error',
			'label' => new \OOUI\HtmlSnippet( wfMessage( $msg )->parse() )
		] );
	}

	public static function parseWikitext( $output, $value ) {
		// return $this->parser->recursiveTagParseFully( $str );
		return Parser::stripOuterParagraph( $output->parseAsContent( $value ) );
	}

	/**
	 * @param Output $output
	 * @param array $formParameters
	 * @param array $data
	 * @return ResultWrapper
	 */
	public static function getJsonForm( $output, $formParameters = [], $data = null ) {
		$res = self::prepareJsonForms( $output, $formParameters );
		if ( !$res->ok ) {
			return ResultWrapper::failure( $res->error );
		}

		return self::getJsonFormHtml( $res->value, $data );
	}

	/**
	 * @param Output $output
	 * @param array $formParameters
	 * @param array $schemaObj
	 * @return ResultWrapper
	 */
	public static function prepareFormData( $output, $data ) {
		$schema = &$data['schema'];

		if ( !empty( $schema ) && class_exists( 'Opis\JsonSchema\Validator' ) ) {
			// $errorMessage = 'invalid schema in form descriptor';
			// return false;
			$editor = new \MediaWiki\Extension\JsonForms\JsonSchemaEditor();
			$wikitextKeys = [ 'title', 'description' ];
			$editor->traverse($schema, function (&$s) use ($output, $wikitextKeys) {
				foreach ( $wikitextKeys as $key ) {
   					if ( isset( $s['options']['wikitext'][$key] ) ) {  
						$s[$key] = self::parseWikitext(
							$output,
							$s['options']['wikitext'][$key]
						);
					}
				}
			} );
		}

		if ( !empty( $data['editorOptions'] ) ) {
			$title_ = TitleClass::newFromText( $data['editorOptions'], NS_MEDIAWIKI );
			if ( $title_ && $title_->isKnown() ) {
				$data['editorOptions'] = self::getWikipageContent( $title_ );
			}
		}

		if ( !empty( $data['editorScript'] ) ) {
			$title_ = TitleClass::newFromText( $data['editorScript'], NS_MEDIAWIKI );
			if ( $title_ && $title_->isKnown() ) {
				$data['editorScript'] = self::getWikipageContent( $title_ );
			}
		}
			
		return $data;
	}

	/**
	 * @param array $data
	 * @return ResultWrapper
	 */
	public static function getJsonFormHtml( $data ) {
		// $requiredKeys = [ 'schema','schemaName', 'editorOptions' ];
		// if ( count( array_intersect_key( (array)$data, array_flip( $requiredKeys ) ) ) !== 3 ) {
		// 	return ResultWrapper::failure('jsonforms-parserfunction-error-invalid-data');
		// }

		$loadingContainer = HtmlClass::rawElement(
			'div',
			[ 'class' => 'rcfilters-head mw-rcfilters-head', 'id' => 'mw-rcfilters-spinner-wrapper', 'style' => 'position: relative' ],
			HtmlClass::rawElement(
				'div',
				[ 'class' => 'initb mw-rcfilters-spinner', 'style' => 'margin-top: auto; top: 25%' ],
				HtmlClass::element(
					'div',
					[ 'class' => 'inita mw-rcfilters-spinner-bounce' ],
				)
			)
		);

		$loadingPlaceholder = HtmlClass::rawElement(
			'div',
			[ 'class' => 'jsonforms-form-placeholder' ],
			// $this->msg( 'jsonforms-loading-placeholder' )->text()
			wfMessage( 'jsonforms-loading-placeholder' )->text()
		);

		$ret = HtmlClass::rawElement( 'div', [
			'data-form-data' => json_encode( $data ),
			'class' => 'jsonforms-form jsonforms-form-wrapper',
			'style' => !isset( $data['schema']['width'] ) ? '' : 'width:' . $data['schema']['width']
		], $loadingContainer . $loadingPlaceholder );
		
		return ResultWrapper::success($ret);
	}

	/**
	 * @param Parser $parser
	 * @param mixed ...$argv
	 * @return array
	 */
	public static function parserFunctionQueryLink( Parser $parser, ...$argv ) {
		$parserOutput = $parser->getOutput();

/*
{{#querylink: pagename
|label
|class=
|class-attr-name=class
|target=
|target-attr-name=target
|a=b
|c=d
|...
}}
*/

		$ql = new QueryLinkParameters($argv);

		// unnamed
		$values = $ql->getValues();
		
		// known named
		$options = $ql->getOptions();

		// unknown named
		$query = $ql->getQuery();		

		$attributes = $ql->getAttributes();
		$text = $ql->getText();
		$title = $ql->getTitle();

		// *** alternatively use $linkRenderer->makePreloadedLink
		// or $GLOBALS['wgArticlePath'] and wfAppendQuery
		$ret = LinkerClass::link( $title, $text, $attributes, $query );

		return [
			$ret,
			'noparse' => true,
			'isHTML' => true
		];
	}

	/**
	 * @param OutputPage $out
	 * @return void
	 */
	public static function addJsConfigVars( $out ) {
		$title = $out->getTitle();
		$user = $out->getUser();
		$context = $out->getContext();

		$schemaUrl = self::getFullUrlOfNamespace( NS_JSONSCHEMA );
		$VEForAll = false;
		if ( ExtensionRegistry::getInstance()->isLoaded( 'VEForAll' )
			&& self::VEenabledForUser( $user )
		) {
			$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
			$userOptionsManager->setOption( $user, 'visualeditor-enable', true );
			$VEForAll = true;
			$out->addModules( 'ext.veforall.main' );
		}

		$groups = [ 'sysop', 'bureaucrat', 'jsonforms-admin' ];
		$showOutdatedVersion = empty( $GLOBALS['wgJsonFormsDisableVersionCheck'] )
			&& (
				$user->isAllowed( 'canmanageschemas' )
				|| count( array_intersect( $groups, self::getUserGroups( $user ) ) )
			);

		$config = [
			'schemaUrl' => $schemaUrl,
			// 'actionUrl' => SpecialPage::getTitleFor( 'VisualDataSubmit', $title->getPrefixedDBkey() )->getLocalURL(),
			'isNewPage' => $title->getArticleID() === 0 || !$title->isKnown(),
			// 'allowedMimeTypes' => $allowedMimeTypes,
			'caneditdata' => $user->isAllowed( 'jsonforms-caneditdata' ),
			'canmanageschemas' => $user->isAllowed( 'jsonforms-canmanageschemas' ),
			'canmanageforms' => $user->isAllowed( 'jsonforms-canmanageforms' ),
			'contentModels' => array_flip( self::getContentModels() ),
			'roleContentModelMap' => SlotHelper::getRoleContentModelMap(),
			'contentModel' => $title->getContentModel(),
			'VEForAll' => $VEForAll,
			'jsonSlots' => SlotHelper::getJsonSlots(),
			'slotRoles' => SlotHelper::getSlotRoles(),
			'jsonContentModels' => SlotHelper::getJsonContentModels(),
			// 'maptiler-apikey' => $GLOBALS['wgJsonFormsMaptilerApiKey']
			'jsonforms-show-notice-outdated-version' => $showOutdatedVersion,
		];

		$out->addJsConfigVars( [
			// @see VEForAll ext.veforall.target.js -> getPageName
			'wgPageFormsTargetName' => ( $title && $title->canExist() ? $title
				: TitleClass::newMainPage() )->getFullText(),

			'jsonforms' => $config,
		] );
	}

	/**
	 * @see includes/api/ApiBase.php
	 * @param User $user
	 * @param Title|MediaWiki\Title\Title $title
	 * @param array &$errors
	 * @return bool
	 */
	public static function checkWritePermissions( $user, $title, &$errors ) {
		$services = MediaWikiServices::getInstance();

		$actions = [ 'edit' ];
		if ( !$title->isKnown() ) {
			$actions[] = 'create';
		}

		if ( class_exists( 'MediaWiki\Permissions\PermissionStatus' ) ) {
			$status = new MediaWiki\Permissions\PermissionStatus();
			foreach ( $actions as $action ) {
				$user->authorizeWrite( $action, $title, $status );
			}
			if ( !$status->isGood() ) {
				return false;
			}
			return true;
		}

		$PermissionManager = $services->getPermissionManager();
		$errors = [];
		foreach ( $actions as $action ) {
			$errors = array_merge(
				$errors,
				$PermissionManager->getPermissionErrors( $action, $user, $title )
			);
		}

		return ( count( $errors ) === 0 );
	}

	/**
	 * @param int $ns
	 * @return string
	 */
	public static function getFullUrlOfNamespace( $ns ) {
		global $wgArticlePath;

		$formattedNamespaces = MediaWikiServices::getInstance()
			->getContentLanguage()->getFormattedNamespaces();
		$namespace = $formattedNamespaces[$ns];

		$schemaUrl = str_replace( '$1', "$namespace:", $wgArticlePath );
		if ( method_exists( MediaWikiServices::class, 'getUrlUtils' ) ) {
			// MW 1.39+
			return MediaWikiServices::getInstance()->getUrlUtils()->expand( $schemaUrl );
		}
		return wfExpandUrl( $schemaUrl );
	}

	/**
	 * @see \MediaWiki\Extension\VisualEditor\Services\VisualEditorAvailabilityLookup::isEnabledForUser
	 * @param User $user
	 * @return bool
	 */
	private static function VEenabledForUser( $user ) {
		$services = MediaWikiServices::getInstance();
		$veConfig = $services->getConfigFactory()->makeConfig( 'visualeditor' );
		$userOptionsLookup = $services->getUserOptionsLookup();
		$isBeta = ( $veConfig->has( 'VisualEditorEnableBetaFeature' ) && $veConfig->get( 'VisualEditorEnableBetaFeature' ) );

		return ( $isBeta ?
			$userOptionsLookup->getOption( $user, 'visualeditor-enable' ) :
			!$userOptionsLookup->getOption( $user, 'visualeditor-betatempdisable' ) ) &&
			!$userOptionsLookup->getOption( $user, 'visualeditor-autodisable' );
	}

	/**
	 * @param User $user
	 * @return array
	 */
	public static function getUserGroups( $user ) {
		$UserGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();

		$user_groups = array_unique( array_merge(
			$UserGroupManager->getUserEffectiveGroups( $user ),
			$UserGroupManager->getUserImplicitGroups( $user )
		) );
		// $key = array_search( '*', $user_groups );
		// $user_groups[ $key ] = 'all';
		return $user_groups;
	}

	/**
	 * @see includes/specials/SpecialChangeContentModel.php
	 * @return array
	 */
	public static function getContentModels() {
		$services = MediaWiki\MediaWikiServices::getInstance();
		$contentHandlerFactory = $services->getContentHandlerFactory();
		$models = $contentHandlerFactory->getContentModels();
		$options = [];

		foreach ( $models as $model ) {
			$handler = $contentHandlerFactory->getContentHandler( $model );

			if ( !$handler->supportsDirectEditing() ) {
				continue;
			}

			$options[ ContentHandler::getLocalizedName( $model ) ] = $model;
		}

		ksort( $options );

		return $options;
	}

   /**
     * @param WikiPage $wikiPage
     * @return string|null
     */
    public static function getFirstJsonSlot(WikiPage $wikiPage): ?string {
    	$revisionRecord = $wikiPage->getRevisionRecord();
    	if ( !$revisionRecord ) {
    		return null;
    	}

        foreach ( $revisionRecord->getSlots()->getSlots() as $role => $slot) {
			if ($slot->getContent() instanceof JsonContent) {
				return $role;
			}
		}

		return null;
    }

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return MediaWiki\Revision\RevisionRecord|null
	 */
	public static function revisionRecordFromTitle( $title ) {
		$wikiPage = self::getWikiPage( $title );
		if ( !$wikiPage ) {
			return null;
		}
		return $wikiPage->getRevisionRecord();
	}

	/**
     * @param WikiPage $wikiPage
     * @param string $role
	 * @return null|string
	 */
	public static function getSlotContent( $wikiPage, $role ) {
		$slots = self::getSlots( $wikiPage );
		foreach ( $slots as $role_ => $slot ) {
			if ( $role_ === $role ) {
				$content = $slot->getContent();
				$ret = $content->getNativeData();
				if ( $content instanceof JsonContent) {
					$ret = json_decode( $ret, true );
					$ret = $ret ?? [];
				}
				return $ret;
			}
		}
		return null;
	}

	/**
     * @param WikiPage $wikiPage
	 * @return null|array
	 */
	public static function getSlots( $wikiPage ) {
		$title = $wikiPage->getTitle();
		$key = $title->getFullText();

		if ( array_key_exists( $key, self::$slotsCache ) ) {
			return self::$slotsCache[$key];
		}

		$revision = $wikiPage->getRevisionRecord();

		if ( !$revision ) {
			return null;
		}

		self::$slotsCache[$key] = $revision->getSlots()->getSlots();

		return self::$slotsCache[$key];
	}

	/**
	 * @param string $titleText
	 * @return array
	 */
	public static function getJsonSchema( $titleText ) {
		$title = TitleClass::newFromText( $titleText );

		if ( !$title || !$title->isKnown() ) {
			return [];
		}

		$text = self::getArticleContent( $title );

		if ( !$text ) {
			return [];
		}

		return json_decode( $text, true );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return WikiPage|null
	 */
	public static function getWikiPage( $title ) {
		if ( !$title || !$title->canExist() ) {
			return null;
		}
		// MW 1.36+
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		}
		return WikiPage::factory( $title );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return string|null
	 */
	public static function getArticleContent( $title ) {
		$wikiPage = self::getWikiPage( $title );
		if ( !$wikiPage ) {
			return null;
		}
		$content = $wikiPage->getContent( \MediaWiki\Revision\RevisionRecord::RAW );
		if ( !$content ) {
			return null;
		}
		return $content->getNativeData();
	}

	/**
	 * @see specials/SpecialPrefixindex.php -> showPrefixChunk
	 * @param string $prefix
	 * @param int $namespace
	 * @return array
	 */
	public static function getPagesWithPrefix( $prefix, $namespace = NS_MAIN ) {
		$dbr = self::getDB( DB_REPLICA );

		$conds = [
			'page_namespace'   => $namespace,
			'page_is_redirect' => 0,
		];

		if ( !empty( $prefix )  ) {
			$conds[] = 'page_title ' . $dbr->buildLike( $prefix, $dbr->anyString() );
		}

		$options = [
			'LIMIT' => self::$queryLimit,
			'ORDER BY' => 'page_title',
			'USE INDEX' => version_compare( MW_VERSION, '1.36', '<' )
				? 'name_title'
				: 'page_name_title',
		];

		$res = $dbr->select(
			'page',
			[ 'page_namespace', 'page_title', 'page_id' ],
			$conds,
			__METHOD__,
			$options
		);

		if ( !$res->numRows() ) {
			return [];
		}

		$ret = [];
		foreach ( $res as $row ) {
			$title = TitleClass::newFromRow( $row );
			if ( $title && $title->isKnown() ) {
				$ret[] = $title;
			}
		}

		return $ret;
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @param User $user
	 * @param string $reason
	 * @return void
	 */
	public static function deleteArticle( $title, $user, $reason ) {
		$wikiPage = self::getWikiPage( $title );
		return self::deletePage( $wikiPage, $user, $reason );
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string $reason
	 */
	public static function deletePage( $wikiPage, $user, $reason ) {
		if ( !( $wikiPage instanceof WikiPage ) ) {
			return;
		}
		if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
			$error = '';
			$wikiPage->doDeleteArticle( $reason, false, null, null, $error, $user );
		} else {
			$wikiPage->doDeleteArticleReal( $reason, $user );
		}
	}

	/**
	 * @param string &$titleStr
	 * @return null|Title
	 */
	public static function parseTitleCounter( &$titleStr ) {
		if ( !preg_match( '/#count\s*$/', $titleStr ) ) {
			return TitleClass::newFromText( $titleStr );
		}

		$titleStr = preg_replace( '/#count\s*$/', '', $titleStr );
		$nsIndex = self::getRegisteredNamespace( $titleStr );
		$title = TitleClass::newFromText( $titleStr, $nsIndex );

		if ( !$title || !$title->canExist() ) {
			return null;
		}

		$dbr = self::getDB( DB_REPLICA );

		$conds = [
			'page_title REGEXP ' . $dbr->addQuotes( $title->getDbKey() . '\d+' ),
			'page_namespace' => $nsIndex
		];

		$options = [
			'USE INDEX' => ( version_compare( MW_VERSION, '1.36', '<' ) ? 'name_title' : 'page_name_title' ),
			'ORDER BY' => 'substr_count DESC',
			'LIMIT' => 1
		];

		$row = $dbr->selectRow(
			'page',
			[ 'page_title', 'SUBSTRING(page_title, ' . ( strlen( $title->getDbKey() ) + 1 ) . ') + 0 as substr_count' ],
			$conds,
			__METHOD__,
			$options
		);

		if ( $row !== false ) {
			$titleStr .= (string)( (int)$row->substr_count + 1 );
		} else {
			$titleStr .= '1';
		}

		return TitleClass::newFromText( $titleStr, $nsIndex );
	}

	/**
	 * @param string &$titleStr
	 * @return int
	 */
	public static function getRegisteredNamespace( &$titleStr ) {
		$arr = explode( ':', $titleStr, 2 );
		if ( count( $arr ) < 2 ) {
			return NS_MAIN;
		}
		$formattedNamespaces = MediaWikiServices::getInstance()
			->getContentLanguage()->getFormattedNamespaces();

		$nameSpace = array_shift( $arr );
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
		$nsIndex = array_search( $nameSpace, $formattedNamespaces );
		if ( $nsIndex === false ) {
			return NS_MAIN;
		}
		$titleStr = implode( ':', $arr );
		return $nsIndex;
	}

	/**
	 * @param int $db
	 * @return \Wikimedia\Rdbms\DBConnRef
	 */
	public static function getDB( $db ) {
		if ( !method_exists( MediaWikiServices::class, 'getConnectionProvider' ) ) {
			// @see https://gerrit.wikimedia.org/r/c/mediawiki/extensions/PageEncryption/+/1038754/comment/4ccfc553_58a41db8/
			return MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( $db );
		}
		$connectionProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		switch ( $db ) {
			case DB_PRIMARY:
				return $connectionProvider->getPrimaryDatabase();
			case DB_REPLICA:
			default:
				return $connectionProvider->getReplicaDatabase();
		}
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return string|null
	 */
	public static function getWikipageContent( $title ) {
		$wikiPage = self::getWikiPage( $title );
		if ( !$wikiPage ) {
			return null;
		}
		$content = $wikiPage->getContent( \MediaWiki\Revision\RevisionRecord::RAW );
		if ( !$content ) {
			return null;
		}
		return $content->getNativeData();
	}

	/**
	 * @param string $titletText
	 * @return Title|null
	 */
	public static function getTitleIfKnown( $titletText ) {
		$title = TitleClass::newFromText( $titletText );
		if ( $title && $title->isKnown() ) {
			return $title;
		}
		return null;
	}

	/**
	 * @param Title $title
	 * @param array slots
	 * @param array &$errors []
	 * @return
	 */
	public static function traverseSchema( array $schema, callable $callback ): array {
		$it = new RecursiveIteratorIterator(
			new RecursiveArrayIterator( $schema ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $it as $key => $value ) {
			$parent =& $schema;
			for ( $depth = 0; $depth < $it->getDepth(); $depth++ ) {
				$parent =& $parent[ $it->getSubIterator( $depth )->key() ];
			}

			$callback( $parent, $key, $value );
		}

		return $schema;
	}

	/**
	 * @param Title $title
	 * @param array slots
	 * @param array &$errors []
	 * @return
	 */
	public static function importRevision( $title, $slots, &$errors = [] ) {
		$services = MediaWikiServices::getInstance();
		$wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );

		$wikiPage = new WikiPage( $title );
		if ( !$wikiPage ) {
			$errors[] = 'cannot create wikipage';
			return false;
		}

		$slotsData = [];
		foreach ( $slots as $value ) {
			$slotsData[$value['role']] = $value;
		}

		if ( !array_key_exists( SlotRecord::MAIN, $slotsData ) ) {
			$slotsData = array_merge( [ SlotRecord::MAIN => [
				'model' => 'wikitext',
				'text' => ''
			] ], $slotsData );
		}

		$oldRevisionRecord = $wikiPage->getRevisionRecord();
		$slotRoleRegistry = $services->getSlotRoleRegistry();
		$contentHandlerFactory = $services->getContentHandlerFactory();
		$contentModels = $contentHandlerFactory->getContentModels();

		$revision = new WikiRevision();
		$revision->setTitle( $title );

		// $content = $this->makeContent( $title, $revId, $revisionInfo );
		// $revision->setContent( SlotRecord::MAIN, $content );
		foreach ( $slotsData as $role => $value ) {
			if ( empty( $value['text'] ) && $role !== SlotRecord::MAIN ) {
				continue;
			}

			if ( !empty( $value['model'] ) && in_array( $value['model'], $contentModels ) ) {
				$modelId = $value['model'];

			} elseif ( $slotRoleRegistry->getRoleHandler( $role ) ) {
		 	   $modelId = $slotRoleRegistry->getRoleHandler( $role )->getDefaultModel( $title );

			} elseif ( $oldRevisionRecord !== null && $oldRevisionRecord->hasSlot( $role ) ) {
    			$modelId = $oldRevisionRecord->getSlot( $role )
					->getContent()
					->getContentHandler()
					->getModelID();
			} else {
				$modelId = CONTENT_MODEL_WIKITEXT;
			}

			if ( !isset( $modelId ) ) {
				$errors[] = "cannot determine content model for role $role";
				continue;
			}

			$content = ContentHandler::makeContent( $value['text'], $title, $modelId );
			$revision->setContent( $role, $content );
		}

		return $revision->importOldRevision();
	}
}
