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
 * @copyright Copyright ©2025, https://wikisphere.org
 */

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;

define( 'SLOT_ROLE_JSONFORMS_DATA', 'jsonforms-data' );
define( 'SLOT_ROLE_JSONFORMS_METADATA', 'jsonforms-metadata' );

class JsonFormsHooks {

	/** @var array */
	public static $PageUpdate = [];

	/**
	 * @param array $credits
	 * @return void
	 */
	public static function initExtension( $credits = [] ) {
	}

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'jsonforms', [ \JsonForms::class, 'parserFunctionForm' ] );
		$parser->setFunctionHook( 'jsonformsquerylink', [ \JsonForms::class, 'parserFunctionQueryLink' ] );
	}

	/**
	 * @param DatabaseUpdater|null $updater
	 */
	public static function onLoadExtensionSchemaUpdates( ?DatabaseUpdater $updater = null ) {
	}

	/**
	 * @param MediaWikiServices $services
	 * @return void
	 */
	public static function onMediaWikiServices( $services ) {
		$services->addServiceManipulator( 'SlotRoleRegistry', static function ( \MediaWiki\Revision\SlotRoleRegistry $registry ) {
			if ( !$registry->isDefinedRole( SLOT_ROLE_JSONFORMS_DATA ) ) {
				$registry->defineRoleWithModel( SLOT_ROLE_JSONFORMS_DATA, 'json', [
					'display' => 'none',
					'region' => 'center',
					'placement' => 'append'
				] );
			}
			if ( !$registry->isDefinedRole( SLOT_ROLE_JSONFORMS_METADATA ) ) {
				$registry->defineRoleWithModel( SLOT_ROLE_JSONFORMS_METADATA, 'json', [
					'display' => 'none',
					'region' => 'center',
					'placement' => 'append'
				] );
			}
		} );
	}

	/**
	 * @param Content $content
	 * @param Title|Mediawiki\Title\Title $title
	 * @param ParserOutput &$parserOutput
	 * @return void
	 */
	public static function onContentAlterParserOutput( Content $content, $title, ParserOutput &$parserOutput ) {
		// $key = $title->getFullText();
		// if ( self::$PageUpdate[$key] ) {
		//	$parserOutput->setExtensionData( 'JsonForms', self::$PageUpdate[$key] );
		//}

		$wikiPage = \JsonForms::getWikiPage( $title );
		if ( !$wikiPage ) {
			return;
		}

		$data = \JsonForms::getSlotContent( $wikiPage, SLOT_ROLE_JSONFORMS_METADATA );

		// $data = $parserOutput->getExtensionData( 'JsonForms' );
		if ( !$data ) {
			return;
		}

		$data = json_decode( $data, true );

		// this includes annotated categories and tracking categories
		$getCategoriesMethod = ( version_compare( MW_VERSION, '1.38', '>=' ) ?
			'getCategoryNames' : 'getCategoryLinks' );

		$categoryNames = $parserOutput->$getCategoriesMethod();

		foreach ( $categoryNames as $category ) {
			$parserOutput->addCategory( $category );
		}

		if ( $data && !empty( $data['categories'] ) ) {
			foreach ( $data['categories'] as $category ) {
				$parserOutput->addCategory( $category );
			}
		}
	}

	/**
	 * @param OutputPage $outputPage
	 * @param Skin $skin
	 * @return void
	 */
	public static function onBeforePageDisplay( OutputPage $outputPage, Skin $skin ) {
	}

	/**
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 * @return void
	 */
	public static function onSkinTemplateNavigation( SkinTemplate $skinTemplate, array &$links ) {
		$user = $skinTemplate->getUser();
		$title = $skinTemplate->getTitle();

		if ( !$title->canExist() ) {
			return;
		}

		$errors = [];
		if ( \JsonForms::checkWritePermissions( $user, $title, $errors )
			// && $user->isAllowed( 'jsonforms-caneditdata' )
			&& !$title->isSpecialPage()
			// && in_array( $title->getNamespace(), $GLOBALS['wgVisualDataEditDataNamespaces'] )
		 ) {
			$link = [
				'class' => ( $skinTemplate->getRequest()->getVal( 'action' ) === 'slotedit' ? 'selected' : '' ),
				'text' => wfMessage( 'jsonforms-slotedit-label' )->text(),
				'href' => $title->getLocalURL( 'action=slotedit' )
			];

			$keys = array_keys( $links['views'] );
			$pos = array_search( 'edit', $keys );

			$links['views'] = array_intersect_key( $links['views'], array_flip( array_slice( $keys, 0, $pos + 1 ) ) )
				+ [ 'slotedit' => $link ] + array_intersect_key( $links['views'], array_flip( array_slice( $keys, $pos + 1 ) ) );
		}
	}

	/**
	 * @param Title|Mediawiki\Title\Title &$title
	 * @param null $unused
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki $mediaWiki
	 * @return void
	 */
	public static function onBeforeInitialize(
		&$title,
		$unused,
		OutputPage $output,
		User $user,
		WebRequest $request,
		/* MediaWiki|MediaWiki\Actions\ActionEntryPoint */ $mediaWiki
	) {
		\JsonForms::initialize();
	}

	/**
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 * @return void
	 */
	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $parserOutput ) {
		$title = $out->getTitle();
		$user = $out->getUser();

		if ( $parserOutput->getExtensionData( 'jsonforms' ) !== null ) {
			\JsonForms::addJsConfigVars( $out, [
				'context' => 'parserfunction'
			] );

			$out->addModules( 'ext.JsonForms.pageForms' );
		}
	}

	/**
	 * @param Skin $skin
	 * @param array &$bar
	 * @return void
	 */
	public static function onSkinBuildSidebar( $skin, &$bar ) {
	}

	/**
	 * @param array &$vars
	 * @param string $skin
	 * @param Config $config
	 * @return void
	 */
	public static function onResourceLoaderGetConfigVars( &$vars, $skin, $config ) {
	}
}
