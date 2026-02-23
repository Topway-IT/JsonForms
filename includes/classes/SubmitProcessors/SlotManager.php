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
use MediaWiki\Extension\JsonForms\SlotHelper;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Parser;

class SlotManager extends SubmitForm {

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
    "title": "abc",
    "content_model": "wikitext",
    "editor": "source",
    "content": "ab",
    "jsonforms-data": {
      "content_model": "json",
      "editor": "JsonForms",
      "content": "[{\"name\":\"a\",\"age\":22}]"
    }
  },
  "structuredValue": {
    "title": {
      "value": "abc",
      "schema": {
        "type": "string",
        "minLength": 1,
        "title": "Title",
        "options": {
          "input": {
            "name": "title"
          }
        }
      },
      "pathNoIndex": "title",
      "isArrayValue": false
    },
    "content_model": {
      "value": "wikitext",
      "schema": {
        "type": "string",
        "title": "Content model",
        "default": "wikitext",
        "watch": {
          "roleProperty": "role"
        },
        "enumSource": [
          {
            "source": "contentModelByRoleSource"
          }
        ]
      },
      "pathNoIndex": "content_model",
      "isArrayValue": false
    },
    "editor": {
      "value": "source",
      "schema": {
        "type": "string",
        "title": "Editor",
        "default": "source",
        "enum": [
          "source",
          "VisualEditor",
          "JSON Editor",
          "JsonForms"
        ]
      },
      "pathNoIndex": "editor",
      "isArrayValue": false
    },
    "content": {
      "value": "ab",
      "schema": {
        "type": "string",
        "format": "textarea",
        "options": {
          "compact": true,
          "format": "textarea",
          "input": {
            "config": {
              "autosize": true,
              "rows": 6
            }
          }
        }
      },
      "pathNoIndex": "content",
      "isArrayValue": false
    },
    "jsonforms-data.content_model": {
      "value": "json",
      "schema": {
        "type": "string",
        "title": "Content model",
        "default": "wikitext",
        "watch": {
          "roleProperty": "role"
        },
        "enumSource": [
          {
            "source": "contentModelByRoleSource"
          }
        ]
      },
      "pathNoIndex": "jsonforms-data.content_model",
      "isArrayValue": false
    },
    "jsonforms-data.editor": {
      "value": "JsonForms",
      "schema": {
        "type": "string",
        "title": "Editor",
        "default": "source",
        "enum": [
          "source",
          "VisualEditor",
          "JSON Editor",
          "JsonForms"
        ]
      },
      "pathNoIndex": "jsonforms-data.editor",
      "isArrayValue": false
    },
    "jsonforms-data.content": {
      "value": "[{\"name\":\"a\",\"age\":22}]",
      "schema": {
        "type": "string",
        "format": "textarea",
        "options": {
          "compact": true,
          "format": "json",
          "input": {
            "name": "JsonForms",
            "config": {
              "schemaSelector": true
            }
          }
        }
      },
      "pathNoIndex": "jsonforms-data.content",
      "isArrayValue": false,
      "structuredValue": {
        "0.name": {
          "value": "a",
          "schema": {
            "type": "string",
            "description": "First and Last name",
            "minLength": 4
          },
          "pathNoIndex": "name",
          "isArrayValue": false
        },
        "0.age": {
          "value": 22,
          "schema": {
            "type": "integer",
            "default": 21,
            "minimum": 18,
            "maximum": 99
          },
          "pathNoIndex": "age",
          "isArrayValue": false
        }
      },
      "schemaName": "Ajax"
    }
  },
  "config": {
    "schemaUrl": "http://127.0.0.1/mediawiki-1.44.0/index.php/JsonSchema:",
    "isNewPage": true,
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
      "jsondata": "visualdata-jsondata",
      "jsonforms-data": "json",
      "jsonforms-metadata": "json",
      "jsonschema": "json",
      "header": "wikitext",
      "footer": "wikitext"
    },
    "contentModel": "wikitext",
    "VEForAll": true,
    "jsonSlots": [
      "jsondata",
      "jsonforms-data",
      "jsonforms-metadata",
      "jsonschema"
    ],
    "slotRoles": [
      "jsondata",
      "jsonforms-data",
      "jsonforms-metadata",
      "jsonschema",
      "header",
      "footer"
    ],
    "jsonContentModels": [
      "visualdata-jsondata",
      "json",
      "json",
      "json"
    ],
    "jsonforms-show-notice-outdated-version": true
  },
  "processor": "SlotManager"
}
*/
		$output = $this->output;

		// if ( $data['options']['action'] === 'delete' ) {
		// }

		// determine targetTitle
		$isNewPage = false;
		$titleStr = null;
		$targetTitle = null;
		
		if ( !empty( $data['options']['editPage'] ) ) {
			$titleStr = $data['options']['editPage'];

		} elseif ( !empty( $data['value']['title'] ) ) {
			$titleStr = $data['value']['title'];
		}

		if ( !empty( $titleStr ) ) {
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
		if ( !empty( $data['value']['content_model'] ) ) {
			$contentModel = $data['value']['content_model'];

		// $contentModel = $data['config']['contentModel'];
		} else if ( $targetTitle->isKnown() ) {
			$contentModel = $targetTitle->getContentModel();
		}

		$main_slot_content = $data['value']['content'] ?? null;
		if ( $targetTitle->isKnown() ) {
			if ( empty( $data['options']['editPage'] ) ) {				
				$errors[] = $this->context->msg( 'jsonforms-special-submit-article-exists',
					$targetTitle->getDBKey() )->parse();

				return [
					'errors' => $errors
				];
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

		// update content model if necessary
		if (
			$targetTitle &&
			!$isNewPage &&
			$contentModel &&
			$contentModel !== $targetTitle->getContentModel()
		) {
			$this->updateContentModel( $targetTitle, $wikiPage, $contentModel, $errors );
		}

		if ( count( $errors ) ) {
			return [
				'errors' => $errors
			];	
		}

		$slots = [
			SlotRecord::MAIN => [
				'model' => $contentModel,
				'content' => $main_slot_content
			]
		];

		$metadata = [
			'slots' => [
				SlotRecord::MAIN => [
					'model' => $contentModel,
					'editor' => $data['value']['editor']
				]			
			]
		];

		if ( $data['value']['editor'] === 'JsonForms' ) {
			$metadata['slots'][SlotRecord::MAIN]['schema'] = $data['structuredValue']['content']['schemaName'];
		}

		if ( !empty( $data['value']['categories'] ) &&
			is_array( $data['value']['categories'] )
		) {
			$metadata['categories'] = $data['value']['categories'];			
		}

		$roles = SlotHelper::getSlotRoles();
		foreach ( $data['value'] as $key => $value ) {
			// if ( is_array( $value ) ) {
			if ( in_array( $key, $roles ) ) {

				// ignore metadata slot
				if ( $key === SLOT_ROLE_JSONFORMS_METADATA ) {
					continue;
				}

				$slots[$key] = [
					'model' => $value['content_model'],
					'content' => $value['content'],
				];

				$metadata['slots'][$key] = [
					'model' => $value['content_model'],
					'editor' => $value['editor'],			
				];

				if ( $value['editor'] === 'JsonForms' ) {
					$metadata['slots'][$key]['schema'] = $data['structuredValue']["$key.content"]['schemaName'];
				}
			}
		}

		$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
			'model' => 'json',
			'content' => json_encode( $metadata )
		];

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
			'metadata' => $metadata,
		];

		$services->getHookContainer()->run( 'JsonForms::OnFormSubmitSuccess', [
			$this->user,
			$data,
			$processedData,
		] );

		$returnUrl = $targetTitle->getLocalURL();

		return [
			'targetTitle' => $targetTitle->getFullText(),
			'returnUrl' => $returnUrl,
			'message' => $message,
			'errors' => [],
		];
	}

}
