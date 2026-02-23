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

namespace MediaWiki\Extension\JsonForms\SubmitProcessors;

use MediaWiki\Extension\JsonForms\SubmitForm;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\JsonForms\SlotEditor;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Parser;

class PageForms extends SubmitForm {

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
    "name": "aaaa"
  },
  "options": {
    "categories": [
      "Ab"
    ]
  },
  "structuredValue": {
    "name": {
      "value": "aaaa",
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
    "@type": "JsonForms default schema",
    "name": "Add person",
    "schema": "Person",
    "uischema": "",
    "edit_categories": true,
    "default_categories": [],
    "default_data_slot": "main",
    "edit_data_slot_role": false,
    "edit_main_slot_content_model": false,
    "edit_main_slot_content": false,
    "default_main_slot_content_model": "wikitext",
    "edit_page": "",
    "pagename_formula": "JsonData:Person/#count",
    "create_only_fields": [],
    "overwrite_existing_article_on_create": false,
    "view": "popup",
    "popup_button_label": "Add person",
    "callback": "",
    "preload": "",
    "preload_data": "",
    "preload_data_separator": "",
    "return_page": "",
    "return_url": "",
    "popup_size": "medium",
    "css_class": "",
    "editor_options": "MediaWiki:DefaultEditorOptions",
    "editor_script": "MediaWiki:DefaultEditorScript",
    "width": "800px"
  },
  "config": {
    "schemaUrl": "http://127.0.0.1/mediawiki-1.43.0/index.php/JsonSchema:",
    "isNewPage": false,
    "caneditdata": false,
    "canmanageschemas": false,
    "canmanageforms": false,
    "contentModels": {
      "css": "CSS",
      "GadgetDefinition": "GadgetDefinition",
      "json": "JSON",
      "javascript": "JavaScript",
      "sanitized-css": "Sanitized CSS",
      "Scribunto": "Scribunto module",
      "translate-messagebundle": "Translatable message bundle",
      "html": "html",
      "pageproperties-jsondata": "pageproperties-jsondata",
      "pageproperties-semantic": "pageproperties-semantic",
      "text": "plain text",
      "twig": "twig",
      "visualdata-jsondata": "visualdata-jsondata",
      "wikitext": "wikitext"
    },
    "roleContentModelMap": {
      "main": "wikitext",
      "jsondata": "visualdata-jsondata"
    },
    "contentModel": "wikitext",
    "VEForAll": true,
    "jsonSlots": [
      "jsondata"
    ],
    "slotRoles": [
      "main",
      "jsondata"
    ],
    "jsonContentModels": [
      "visualdata-jsondata"
    ],
    "jsonforms-show-notice-outdated-version": true
  },
  "processor": "Pageforms"
}


FORM OPTIONS

title:
data_slot: 
main_slot_content_model: 
main_slot_content: 
categories: 
summary: 
*/	


/*
metadata can be stored:
-- using an outer schema (VisualData)
-- as a reference in the data schema itself (OSL)
-- using a meta schema in a different slot
-- using page_props  (output page setProperty/getProperty)
*/

		$output = $this->output;

		// if ( $data['options']['action'] === 'delete' ) {
					
		// }

		// determine targetTitle
		$isNewPage = false;
		$titleStr = null;
		$targetTitle = null;
		if ( !empty( $data['options']['title'] ) ) {
			$titleStr = $data['options']['title'];

		} elseif ( !empty( $data['formDescriptor']['edit_page'] ) ) {
			$titleStr = $data['formDescriptor']['edit_page'];

		} elseif ( !empty( $data['formDescriptor']['pagename_formula'] ) ) {
			$targetTitle = $data['formDescriptor']['pagename_formula'];
			$targetTitle = $this->parseWikitext( $targetTitle );
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
			return [
				'errors' => $errors
			];
		}

		$contentModel = 'wikitext';
		if ( !empty( $data['options']['main_slot_content_model'] ) ) {
			$contentModel = $data['options']['main_slot_content_model'];
			
		} else if ( !empty( $data['formDescriptor']['default_main_slot_content_model'] ) ) {
			$contentModel = $data['formDescriptor']['default_main_slot_content_model'];

		// $contentModel = $data['config']['contentModel'];
		} else if ( $targetTitle->isKnown() ) {
			$contentModel = $targetTitle->getContentModel();
		}

		$main_slot_content = $data['options']['main_slot_content'] ?? null;
		
		if ( $targetTitle->isKnown() ) {
			if ( empty( $data['formDescriptor']['edit_page'] ) ) {
				if ( $data['formDescriptor']['overwrite_existing_article_on_create'] !== true ) {
					$errors[] = $this->context->msg( 'jsonforms-special-submit-article-exists',
						$targetTitle->getDBKey() )->parse();

					return [
						'errors' => $errors
					];
				}
			// page edit through $data['formDescriptor']['edit_page']
			} else {
			}
		} else {
			$isNewPage = true;

			// *** create new revision if necessary
			// if ( !$this->createInitialRevision( $targetTitle, $main_slot_content, $contentModel, $errors ) ) {
			// 	$errors[] = $this->context->msg( 'jsonforms-special-submit-cannot-initialize-new-revision',
			// 		$targetTitle->getDBKey(), $contentModel )->parse();

			// 	return [
			// 		'errors' => $errors
			// 	];
			// }
			// }
		}

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );
		
		if ( !$wikiPage ) {
			$errors[] = $this->context->msg( 'jsonforms-special-submit-cannot-create-wikipage' );
			return [
				'errors' => $errors
			];
		}

		// @set title for further use of parseWikitext
		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

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
		
		if ( !empty( $data['options']['data_slot'] ) ) {
			$targetSlot = $data['options']['data_slot'];
			
		} else if ( !empty( $data['formDescriptor']['default_data_slot'] ) ) {
			 $targetSlot = $data['formDescriptor']['default_data_slot'];

		} else if ( $isNewPage && $main_slot_content === null ) {
			$targetSlot = 'main';

		} else {
			$targetSlot = \JsonForms::getFirstJsonSlot( $wikiPage );		
		}

		if ( !$targetSlot ) {
			$targetSlot = SLOT_ROLE_JSONFORMS_DATA;
		}

		$slots = [
			$targetSlot => [
				'model' => 'json',
				'content' => json_encode( $data['value'] )
			]
		];

		// determine freetext
		if ( $isNewPage &&
			!array_key_exists( 'main_slot_content', $data['options'] ) &&
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
		
		// set metadata
		// JsonFormsHooks::$PageUpdate[$targetTitle->getFullText()] = $metadata;
		$metadata = [
			'slots' => [
				$targetSlot => [
					'editor' => 'JsonForms',
					'model' => $targetSlot === 'main' ? $contentModel : 'json',
					'schema' => $data['formDescriptor']['schema']
				]
			]
		];

		if ( !empty( $data['options']['categories'] ) &&
			is_array( $data['options']['categories'] )
		) {
			$metadata['categories'] = $data['options']['categories'];			
		}

		// $previousMetadata = \JsonForms::getSlotContent( $wikiPage, SLOT_ROLE_JSONFORMS_METADATA );
		
		// $previousData = \JsonForms::getSlotContent( $wikiPage, SLOT_ROLE_JSONFORMS_METADATA );
		// if ( $previousData ) {
		// 	$metadata = \JsonForms::array_merge_recursive( $previousData, $metadata, true );
		// }

		$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
			'model' => 'json',
			'content' =>  json_encode( $metadata )
		];

		// keep existing slots
		$previousMetadata = \JsonForms::getMetadata( $wikiPage );
		if ( $previousMetadata && isset( $previousMetadata['slots'] ) ) {
			// $previousMetadata = json_decode( $previousMetadata, true );
			$slots = $slots + $previousMetadata['slots'];

			// foreach ( $previousMetadata as $key => $value ) {
			// 	if ( !isset( $slots[$key] ) ) {
			// 		$slots[$key] = $value;
			// 	}
			// }
		}

		$slotEditor = new SlotEditor();

		$summary = $data['options']['summary'] ?? '';
		$minor = $data['options']['minor'] ?? false;
		$append = false;
		$watchlist = "";
		$prepend = false;
		$bot = false;
		$createonly = false;
		$nocreate = false;
		$suppress = false;
		$updateStrategy = 'replace';

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
			$suppress,
			$updateStrategy
		);

		if ( $ret !== true ) {
			$errors = $ret;
			return [
				'errors' => $errors
			];	
		}

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );

		// \JsonForms::setMetadata( $this->context, $wikiPage, $metadata );

		if ( !$isNewPage ) {
			$wikiPage->doPurge();
		}

		$processedData = [
			'slots' => $slots,
			'targetTitle' => $targetTitle,
			'isNewPage' => $isNewPage,
			'contentModel' => $contentModel,
			'main_slot_content' => $main_slot_content,
			'metadata' => $metadata,
			'returnUrl' => $returnUrl,
			'targetUrl' => $targetUrl,
		];

		$services->getHookContainer()->run( 'JsonForms::OnFormSubmitSuccess', [
			$this->user,
			$data,
			$processedData,
		] );

		$localUrl = $targetTitle->getLocalURL();
		$targetUrl = (string)$services->getUrlUtils()->expand( $localUrl, PROTO_FALLBACK );
			
		$message = null;
		if ( !$returnUrl ) {
			$messageKey = 'jsonforms-jsmodule-return-message-' . ( $isNewPage ? 'create' : 'edit' );
			$message = $this->context->msg( $messageKey,
					$targetTitle->getFullText(),
					$targetUrl
				)->text();
		}

		return [
			// return url (location refresh)
			'returnUrl' => $returnUrl,

			// non-return target url
			'targetUrl' => $targetUrl,
			'targetTitle' => $targetTitle->getFullText(),
			'message' => $message,
			'errors' => [],
		];
	}

}
