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

namespace MediaWiki\Extension\JsonForms;

use CommentStoreComment;
use ContentHandler;
use ContentModelChange;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\JsonForms\SlotEditor;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Parser;
use RawMessage;
use RequestContext;
use Status;

class SubmitForm {

	/** @var Output */
	private $output;

	/** @var Context */
	private $context;

	/** @var User */
	private $user;

	/** @var MediaWikiServices */
	private $services;

	/**
	 * @param User $user
	 * @param Context|null $context can be null
	 */
	public function __construct( $user, $context = null ) {
		$this->user = $user;
		// @ATTENTION ! use always Main context, in api
		// context OutputPage -> parseAsContent will work
		// in a different way !
		$this->context = $context ?? RequestContext::getMain();
		$this->output = $this->context->getOutput();
		$this->services = MediaWikiServices::getInstance();
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( $output ) {
		$this->output = $output;
	}

	/**
	 * @param string|array $value
	 * @return string
	 */
	private function parseWikitext( $value ) {
		// return $this->parser->recursiveTagParseFully( $str );
		$values = is_array( $value ) ? $value : [ $value ];

		$parsed = array_map(
			fn ( $v ) => Parser::stripOuterParagraph(
				$this->output->parseAsContent( $v )
			),
			$values
		);

		return is_array( $value ) ? $parsed : $parsed[0];
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @param string $content
	 * @param string $contentModel
	 * @param array &$errors
	 * @return bool
	 */
	private function createInitialRevision( $title, $content, $contentModel, &$errors = [] ) {
		// "" will trigger an error by ContentHandler::makeContent
		// if ( empty( $contentModel ) ) {
		// 	$contentModel = null;
		// }

		// @see https://github.com/wikimedia/mediawiki/blob/master/includes/page/WikiPage.php
		$flags = EDIT_SUPPRESS_RC | EDIT_AUTOSUMMARY | EDIT_INTERNAL;
		$summary = 'JsonForms initial revision';

		$wikiPage = \JsonForms::getWikiPage( $title );
		$pageUpdater = $wikiPage->newPageUpdater( $this->user );
		
		$services = MediaWikiServices::getInstance();
		$contentHandlerFactory = $services->getContentHandlerFactory();
		$contentHandler = $contentHandlerFactory->getContentHandler( $contentModel );
		
		$main_content = !empty( $content ) ?
			ContentHandler::makeContent( (string)$content, $title, $contentModel ) :
			$contentHandler->makeEmptyContent();

		$pageUpdater->setContent( SlotRecord::MAIN, $main_content );
		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$revisionRecord = $pageUpdater->saveRevision( $comment, $flags );
		$status = $pageUpdater->getStatus();
		return $status->isOK();
	}

	/**
	 * @see includes/specials/SpecialChangeContentModel.php
	 * @param WikiPage $page
	 * @param string $model
	 * @return Status
	 */
	public function changeContentModel( $page, $model ) {
		// $page = $this->wikiPageFactory->newFromTitle( $title );
		// ***edited
		$performer = ( method_exists( RequestContext::class, 'getAuthority' ) ? $this->context->getAuthority()
			: $this->user );
		// ***edited
		$services = $this->services;
		$contentModelChangeFactory = $services->getContentModelChangeFactory();
		$changer = $contentModelChangeFactory->newContentModelChange(
			// ***edited
			$performer,
			$page,
			// ***edited
			$model
		);
		// MW 1.36+
		if ( method_exists( ContentModelChange::class, 'authorizeChange' ) ) {
			$permissionStatus = $changer->authorizeChange();
			if ( !$permissionStatus->isGood() ) {
				// *** edited
				$out = $this->output;
				$wikitext = $out->formatPermissionStatus( $permissionStatus );
				// Hack to get our wikitext parsed
				return Status::newFatal( new RawMessage( '$1', [ $wikitext ] ) );
			}
		} else {
			$errors = $changer->checkPermissions();
			if ( $errors ) {
				// *** edited
				$out = $this->output;
				$wikitext = $out->formatPermissionsErrorMessage( $errors );
				// Hack to get our wikitext parsed
				return Status::newFatal( new RawMessage( '$1', [ $wikitext ] ) );
			}
		}
		// Can also throw a ThrottledError, don't catch it
		$status = $changer->doContentModelChange(
			// ***edited
			$this->context,
			// $data['reason'],
			'',
			true
		);
		return $status;
	}

	/**
	 * @param Title|MediaWiki\Title\Title $targetTitle
	 * @param \WikiPage $wikiPage
	 * @param string $contentModel
	 * @param array &$errors
	 * @return bool
	 */
	private function updateContentModel( $targetTitle, $wikiPage, $contentModel, &$errors ) {
		$status = $this->changeContentModel( $wikiPage, $contentModel );
		if ( !$status->isOK() ) {
			$errors_ = $status->getErrorsByType( 'error' );
			foreach ( $errors_ as $error ) {
				$msg = array_merge( [ $error['message'] ], $error['params'] );
				// @see SpecialVisualData -> getMessage
				$errors[] = \Message::newFromSpecifier( $msg )->setContext( $this->context )->parse();
			}
		}
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$services = MediaWikiServices::getInstance();

		// this should happen only if hacked
		// if ( !$this->user->isAllowed( 'jsonforms-caneditdata' ) ) {
		// 	echo $this->context->msg( 'jsonforms-jsmodule-forms-cannot-edit-form' )->text();
		// 	exit();
		// }

		$errors = [];

/*
{
  "value": {
    "name": "abcd"
  },
  "editors": {
    "name": {
      "value": "abcd",
      "schema": {
        "type": "string",
        "description": "First and Last name",
        "minLength": 4
      },
      "pathNoIndex": "name",
      "isArrayValue": false
    }
  },
  "formDescriptor": {
    "name": "Add person",
    "schema": "Person",
    "edit_categories": false,
    "default_data_slot": "main",
    "overwrite_existing_article_on_create": false,
    "view": "popup",
    "pagename_formula": "JsonData:Person/#count",
    "editor_options": "MediaWiki:DefaultEditorOptions"
  },
  "formValue": {}
}

{
  "value": {
    "accidentDetails": {
      "Severity": "Injury",
      "_localId": "b4d553d1-872f-477a-8866-26a27f120b15"
    }
  },
  "editors": {
    "accidentDetails.Severity": {
      "value": "Injury",
      "schema": {
        "enum": [
          "Fatal",
          "Injury",
          "Property"
        ],
        "type": "string",
        "isSearchable": true,
        "default": ""
      },
      "pathNoIndex": "accidentDetails.Severity",
      "isArrayValue": false
    },
    "accidentDetails._localId": {
      "value": "b4d553d1-872f-477a-8866-26a27f120b15",
      "schema": {
        "type": "string",
        "format": "uuid",
        "options": {
          "hidden": true,
          "cleave": {
            "delimiters": [
              "-"
            ],
            "blocks": [
              8,
              4,
              4,
              4,
              12
            ]
          }
        },
        "pattern": "^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$",
        "default": "b4d553d1-872f-477a-8866-26a27f120b15"
      },
      "pathNoIndex": "accidentDetails._localId",
      "isArrayValue": false
    }
  },
  "formDescriptor": {
    "name": "Create Article",
    "edit_categories": true,
    "edit_data_slot": true,
    "default_data_slot": "jsondata",
    "edit_main_slot_content_model": true,
    "edit_main_slot_content": true,
    "default_main_slot_content_model": "wikitext",
    "overwrite_existing_article_on_create": false,
    "view": "inline",
    "editor_options": "MediaWiki:DefaultEditorOptions"
  },
  "formValue": {
    "title": "a"
  }
  config:
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
			
}


FORM DESCRIPTOR OPTIONS

		"name": 
		"schema": 
		"uischema": 
		"edit_categories":
		"default_categories":
		"default_data_slot":
		"edit_data_slot": 
		"edit_main_slot_content_model":
		"edit_main_slot_content": 
		"default_main_slot_content_model":
		"pagename_formula":
		"overwrite_existing_article_on_create": 
		"view": 
		"callback": 
		"edit_page":
		"preload": 
		"preload_data": 
		"preload_data_separator":
		"return_page": 
		"return_url":
		"popup_size":
		"css_class":
		"editor_options":
		"width"


FORM OPTIONS

title:
data_slot: 
main_slot_content_model: 
main_slot_content: 
categories: 
summary: 
							
*/	
		$extensionData = [];
		$output = $this->output;

		if ( !empty( $data['formValue']['categories'] ) &&
			is_array( $data['formValue']['categories'] )
		) {
			$extensionData['categories'] = $data['formValue']['categories'];			
		}

		// if ( $data['formValue']['action'] === 'delete' ) {
					
		// }

		// determine targetTitle
		$isNewPage = false;
		$titleStr = null;
		if ( !empty( $data['formValue']['title'] ) ) {
			$titleStr = $data['formValue']['title'];

		} elseif ( !empty( $data['formDescriptor']['edit_title'] ) ) {
			$titleStr = $data['formDescriptor']['edit_title'];

		} elseif ( !empty( $data['formDescriptor']['pagename_formula'] ) ) {
			$targetTitle = $data['formDescriptor']['pagename_formula'];
			$targetTitle = \JsonForms::parseTitleCounter( $targetTitle );
			
			if ( empty( $targetTitle ) ) {
				$errors[] = $this->context->msg( 'jsonforms-special-submit-title-counter-error' )->text();
				return [
					'errors' => $errors
				];
			}
		}

		if ( !$targetTitle ) {
			$targetTitle = TitleClass::newFromText( $titleStr );
		}
		
		if ( empty( $targetTitle ) ) {
			$errors[] = $this->context->msg( 'jsonforms-special-submit-notitle' )->text();
			return [
				'errors' => $errors
			];
		}
		
		if ( !\JsonForms::checkWritePermissions( $this->user, $targetTitle, $errors ) ) {
			$errors[] = $this->context->msg( 'jsonforms-special-submit-permission-error' )->text();
		}

		$contentModel = 'wikitext';
		if ( !empty( $data['formValue']['main_slot_content_model'] ) ) {
			$contentModel = $data['formValue']['main_slot_content_model'];
			
		} else if ( !empty( $data['formDescriptor']['default_main_slot_content_model'] ) ) {
			$contentModel = $data['formDescriptor']['default_main_slot_content_model'];

		// $contentModel = $data['config']['contentModel'];
		} else if ( $targetTitle->isKnown() ) {
			$contentModel = $targetTitle->getContentModel();
		}
		
		
		$main_slot_content = $data['formValue']['main_slot_content'] ?? null;
		

		if ( $targetTitle->isKnown() ) {
			if ( empty( $data['formDescriptor']['edit_title'] ) ) {
				if ( $data['formDescriptor']['overwrite_existing_article_on_create'] !== true ) {
					$errors[] = $this->context->msg( 'jsonforms-special-submit-article-exists',
						$targetTitle->getDBKey() )->parse();

					return [
						'errors' => $errors
					];
				}

			} else {
				$isNewPage = true;
				// if ( !$this->createInitialRevision( $targetTitle, $main_slot_content, $contentModel, $errors ) ) {
				// 	$errors[] = $this->context->msg( 'jsonforms-special-submit-cannot-initialize-new-revision',
				// 		$targetTitle->getDBKey(), $contentModel )->parse();

				// 	return [
				// 		'errors' => $errors
				// 	];
				// }
			}
		}

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );
		
		if ( !$wikiPage ) {
			$errors[] = $this->context->msg( 'jsonforms-special-submit-cannot-create-wikipage' );
			return [
				'errors' => $errors
			];
		}

		$returnUrl = null;
		$localUrl = null;
		if ( !empty( $data['formDescriptor']['return_url'] ) ) {
			$localUrl = $data['formDescriptor']['return_url'];
			
		} else if ( !empty( $data['formDescriptor']['return_page'] ) ) {
			$title_ = TitleClass::newFromText( $data['formDescriptor']['return_page'] );
			if ( $title_ ) {
				$localUrl = $title_->getLocalURL();
			}
		}

		if ( $localUrl ) {
			$returnUrl = (string)$services->getUrlUtils()->expand( $localUrl, PROTO_FALLBACK );
			
			if ( filter_var( $returnUrl, FILTER_VALIDATE_URL ) === false ) {
				$errors[] = $this->context->msg( 'jsonforms-special-submit-return-url-error', $targetUrl )->text();
			}
		}

		// update content model if necessary
		if ( $targetTitle
			&& !$isNewPage
			&& $contentModel
			&& $contentModel !== $targetTitle->getContentModel()
		) {
			$this->updateContentModel( $targetTitle, $wikiPage, $contentModel, $errors );
		}

		if ( count( $errors ) ) {
			return [
				'errors' => $errors
			];	
		}
		
		if ( !empty( $data['formValue']['data_slot'] ) ) {
			$targetSlot = $data['formValue']['data_slot'];
			
		} else if ( !empty( $data['formDescriptor']['default_data_slot'] ) ) {
			 $targetSlot = $data['formDescriptor']['default_data_slot'];

		} else if ( $isNewPage && $main_slot_content === null ) {
			$targetSlot = 'main';

		} else {
			$targetSlot = \JsonForms::getJsonSlot( $wikiPage );		
		}
		
		if ( !$targetSlot ) {
			$targetSlot = 'jsondata';
		}

		$slots = [
			$targetSlot => [
				'model' => 'json',
				'content' => json_encode( $data['value'] )
			]
		];

		// determine freetext
		if ( $isNewPage &&
			!array_key_exists( 'main_slot_content', $data['formValue'] ) &&
			!empty( $data['formDescriptor']['preload'] )
		) {
			$title_ = \JsonForms::getTitleIfKnown( $data['formDescriptor']['preload'] );
			if ( $title_ ) {
				$main_slot_content = \JsonForms::getWikipageContent( $title_ );
			}
		}

		// trigger_error('$targetSlot ' . print_r($slots,1));

		// @ATTENTION !! if freetext is NULL the slot content
		// must not be edited in order to keep it unchanged
		if ( $targetSlot !== 'main' && $main_slot_content !== null ) {
			$slots[SlotRecord::MAIN] = [
				'model' => $contentModel,
				'content' => $freetext
			];
		}

		$slotEditor = new SlotEditor();

		$summary = $data['formValue']['summary'] ?? '';
		$minor = $data['formValue']['minor'] ?? false;
		$append = false;
		$watchlist = "";
		$prepend = false;
		$bot = false;
		$createonly = false;
		$nocreate = false;
		$suppress = false;

		$ret = $slotEditor->editSlots(
			$this->user,
			$wikiPage,
			$slots,
			$summary,
			$append,
			$watchlist,
			$prepend,
			$bot,
			$minor,
			$createonly,
			$nocreate,
			$suppress
		);

		if ( $ret !== true ) {
			$errors = $ret;
			return [
				'errors' => $errors
			];	
		}

		if ( !empty( $extensionData ) ) {
			$parserOutput->setExtensionData( 'jsonforms', $extensionData );
		}

		if ( !$isNewPage ) {
			$wikiPage->doPurge();
		}

		$localUrl = $targetTitle->getLocalURL();
		$targetUrl = (string)$services->getUrlUtils()->expand( $localUrl, PROTO_FALLBACK );

		return [
			// return url (location refresh)
			'returnUrl' => $returnUrl,

			// non-return target url
			'targetUrl' => $targetUrl,
			'targetTitle' => $targetTitle->getFullText(),
			'errors' => [],
		];
	}

}
