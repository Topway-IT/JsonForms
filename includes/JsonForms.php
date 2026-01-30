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

use MediaWiki\Extension\JsonForms\Aliases\Html as HtmlClass;
use MediaWiki\Extension\JsonForms\Aliases\Linker as LinkerClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Content\JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Page\PageIdentityValue;

use MediaWiki\Extension\JsonForms\QueryLinkParameters;

if ( is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
	include_once __DIR__ . '/../vendor/autoload.php';
}


class JsonForms {

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

		if ( empty( $argv ) ) {
			echo 'enter a form name';
			exit;
		}

		$function = 'form';
		$formName = $argv[0];
		$data = [];
		$errorMessage = null;
		$html = self::getJsonForm( $parserOutput, $formName, $data, $errorMessage );

		if ( $html === false ) {
			echo $errorMessage;
			exit;
		}
		
		return [
			$html,
			'noparse' => true,
			'isHTML' => true
		];
		
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

	public static function parseWikitext( $output, $value ) {
		// return $this->parser->recursiveTagParseFully( $str );
		return Parser::stripOuterParagraph( $output->parseAsContent( $value ) );
	}

	/**
	 * @param string $formName
	 * @param array $data
	 * @param string &$errorMessage
	 * @return string|false
	 */
	public static function getJsonForm( $output, $formName, $data, &$errorMessage ) {
		$formData = self::prepareJsonForms( $output, $formName, $errorMessage );
		return self::getJsonFormHtml( $formData, $data );
	}

	/**
	 * @param array $data
	 * @param array $startval
	 * @return string
	 */
	public static function getJsonFormHtml( $data, $startval = [] ) {
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

		return HtmlClass::rawElement( 'div', [
			'data-form-data' => json_encode( [
				'formDescriptor' => $data['formDescriptor'],
				'schema' => (array)$data['schema'],
				'schemaName' =>$data['schemaName'],
				'data' => $startval,
				'editorOptions' => $data['editorOptions']
			] ),
			'class' => 'jsonforms-form jsonforms-form-wrapper'
		], $loadingContainer . $loadingPlaceholder );		
	}

	/**
	 * @param string $formName
	 * @param array $data
	 * @param string &$errorMessage
	 * @return string|false
	 */
	public static function prepareJsonForms( $output, $formName, &$errorMessage ) {
		if ( empty( $formName ) ) {
			$errorMessage = 'enter a form name';
			return false;
		}
	
		$formDescriptor = self::getJsonSchema( 'JsonForm:' . $formName  );
		if ( empty( $formDescriptor ) ) {
			$errorMessage = 'enter a valid form name';
			return false;
		}

		$schema = [];
		$schemaName = [];
		if ( !empty( $formDescriptor['schema'] ) ) {
			$schemaName = $formDescriptor['schema'];
			$schema = self::getJsonSchema( 'JsonSchema:' . $schemaName  );	
		}

		if ( !empty( $schema ) && class_exists( 'Opis\JsonSchema\Validator' ) ) {
			// $errorMessage = 'invalid schema in form descriptor';
			// return false;
			$editor = new \MediaWiki\Extension\JsonForms\JsonSchemaEditor();

			$editor->traverse($schema, function (&$s) use ($output) {
   				if ( isset( $s['options']['wikitext']['description'] ) ) {  
					$s['description'] = self::parseWikitext(
						$output,
						$s['options']['wikitext']['description']
					);
				}
			} );
			
		}

		$editorOptions = '';
		if ( !empty( $formDescriptor['editor_options'] ) ) {

			$title_ = TitleClass::newFromText( $formDescriptor['editor_options'], NS_MEDIAWIKI );
			if ( $title_ && $title_->isKnown() ) {
				$editorOptions = self::getWikipageContent( $title_ );
			}
		}

		return [
			'formDescriptor' => $formDescriptor,
			'schema' => (array)$schema,
			'schemaName' => $schemaName,
			'editorOptions' => $editorOptions
		];
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
	 * @param array $obj
	 * @return void
	 */
	public static function addJsConfigVars( $out, $obj ) {
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
			'contentModel' => $title->getContentModel(),
			'VEForAll' => $VEForAll,
			'jsonSlots' => self::getJsonSlots(),
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
	 * @return array
	 */
	public static function getJsonSlots() {
		$services = MediaWikiServices::getInstance();
		$contentHandlerFactory = $services->getContentHandlerFactory();
		$slotRegistry = $services->getSlotRoleRegistry();

		$page = new PageIdentityValue( 0, NS_MAIN, 'Dummy', false );

		$ret = [];
		foreach ( $slotRegistry->getKnownRoles() as $slot ) {
		    $roleHandler = $slotRegistry->getRoleHandler( $slot );
		  	$model = $roleHandler->getDefaultModel( $page );

			$contentHandler = $contentHandlerFactory->getContentHandler( $model );
			$content = $contentHandler->makeEmptyContent();

			if ( $content instanceof JsonContent ) {
				// $jsonLikeSlots[$slot] = get_class($content);
				$ret[] = $slot;
			}
		}
		return $ret;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @return string|null
	 */
	public static function getJsonSlot( $wikiPage ) {
		$revision = $wikiPage->getRevisionRecord();
		$slots = $revision->getSlots()->getSlots();
		
		foreach ( $slots as $role => $slot ) {
			$content = $slots[$role]->getContent();

			if ( $content instanceof JsonContent ) {
				return $role;
			}
		}

		return null;
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
