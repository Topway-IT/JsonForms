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
use MediaWiki\Extension\JsonForms\ResultWrapper;
use MediaWiki\Extension\JsonForms\SlotHelper;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Parser;

class EditSchema extends SubmitForm {

	/**
	 * @param array $data
	 * @return array
	 */
	public function processData( $data ) {
		$services = MediaWikiServices::getInstance();

		$errors = [];

/*
{
  "options": {
    "title": "",
    "content_model": "wikitext",
    "editor": "wikieditor",
    "content": "",
    "categories": [],
    "summary": "",
    "minor": false,
    "buttons": {
      "submit": null
    }
  },
  "config": {
    "schemaUrl": "http://127.0.0.1/mediawiki-1.44.0/index.php/JsonSchema:",
    "isNewPage": true,
    "caneditdata": true,
    "canmanageschemas": true,
    "canmanageforms": true,
    "contentModels": {
      "css": "CSS",
      "GadgetDefinition": "GadgetDefinition",
      "json": "JSON",
      "javascript": "JavaScript",
      "sanitized-css": "Sanitized CSS",
      "Scribunto": "Scribunto module",
      "text": "plain text",
      "twig": "twig",
      "wikitext": "wikitext"
    },
    "roleContentModelMap": {
      "main": "wikitext",
      "jsonforms-data": "json",
      "jsonforms-metadata": "json",
      "jsonschema": "json",
      "jsondata": "json",
      "header": "wikitext",
      "footer": "wikitext"
    },
    "contentModel": "wikitext",
    "VEForAll": true,
    "captchaSiteKey": "6Ld4DYUsAAAAAB7ypPb84qYAXjGBSd9oSjQGK3jB",
    "jsonSlots": [
      "jsonforms-data",
      "jsonforms-metadata",
      "jsonschema",
      "jsondata"
    ],
    "slotRoles": [
      "main",
      "jsonforms-data",
      "jsonforms-metadata",
      "jsonschema",
      "jsondata",
      "header",
      "footer"
    ],
    "jsonContentModels": [
      "json",
      "json",
      "json",
      "json"
    ],
    "jsonforms-show-notice-outdated-version": true
  },
  "processor": "NewArticle"
}
*/

		if ( empty( $data['options']['title'] ) ) {
			return ResultWrapper::failure( $this->context->msg( 'jsonforms-special-submit-notitle' ) );
		}

		$titleStr = $data['options']['title'];
		$targetTitle = TitleClass::newFromText( $titleStr );

		if ( empty( $targetTitle ) ) {
			return ResultWrapper::failure( $this->context->msg( 'jsonforms-special-submit-notitle' )->text() );
		}

		if ( !\JsonForms::checkWritePermissions( $this->user, $targetTitle, $errors ) ) {
			return ResultWrapper::failure( $this->context->msg( 'jsonforms-special-submit-permission-error' )->text() );
		}

		if ( !$targetTitle->isKnown() ) {
			return ResultWrapper::failure( $this->context->msg( 'jsonforms-special-edit-title-unknown',
				$targetTitle->getDBKey() )->parse() );
		}

		// if ( empty( $data['value'] ) ) {
		//	return ResultWrapper::failure( $this->context->msg( 'jsonforms-special-submit-nocontent' )->text() );
		// }

		$deleteSchema = empty( $data['metadata']['schemaName'] );

		$wikiPage = \JsonForms::getWikiPage( $targetTitle );

		$metadataPrevious = \JsonForms::getMetadata( $wikiPage );
		$targetSlot = null;
		if ( $metadataPrevious && is_array( $metadataPrevious['slots'] ) ) {

			// can be either SLOT_ROLE_JSONFORMS_DATA or main
			foreach ( $metadataPrevious['slots'] as $role => $value ) {
				if ( isset( $value['schema'] ) ) {
					$targetSlot = $role;
				}
			}
		}

		if ( !$targetSlot ) {
			$targetSlot = SLOT_ROLE_JSONFORMS_DATA;
		}

		$isDataOnly = $targetSlot === SlotRecord::MAIN;
		
		if ( !$wikiPage ) {
			return ResultWrapper::failure( $this->context->msg( 'jsonforms-special-submit-cannot-create-wikipage' )->text() );
		}

		// @set title for further use of parseWikitext
		$this->context->setTitle( $targetTitle );
		$this->setOutput( $this->context->getOutput() );

		$slots = [];
		$slots_ = \JsonForms::getSlots( $wikiPage );
		foreach ( $slots_ as $role => $slot ) {
			if ( $role === SLOT_ROLE_JSONFORMS_METADATA ) {
				continue;
			}
			$content = \JsonForms::getSlotContent( $wikiPage, $role );

			$slots[$role] = [
				'model' => $slot->getModel(),
				'content' => $content
			];	
		}

		$metadata = $metadataPrevious ?? [];

		if ( $deleteSchema ) {
			unset( $slots[$targetSlot] );
			unset( $metadata['slots'][$targetSlot] );
		}

		if ( !$deleteSchema ) {
			$slots[$targetSlot] = [
				'model' => 'json',
				'content' => json_encode( $data['value'] )
			];
		}

		if ( !isset( $metadata['slots'] ) ) {
			$metadata['slots'] = [];
		}

		if ( !$deleteSchema ) {
			$metadata['slots'][$targetSlot]['model'] = 'json';
			$metadata['slots'][$targetSlot]['schema'] = $data['metadata']['schemaName'];
		}

		if (
			!empty( $data['options']['categories'] ) &&
			is_array( $data['options']['categories'] )
		) {
			$metadata['categories'] = $data['options']['categories'];			
		}

		if ( !$deleteSchema && !$isDataOnly ) {
			$metadataKeys = [
				'show_infobox' => 'showInfobox',
				'infobox_template' => 'infoboxTemplate',
			];

			foreach ( $metadataKeys as $key => $value ) {
				if ( !empty( $data['metadata'][$key] ) ) {
					$metadata['slots'][SLOT_ROLE_JSONFORMS_DATA][$value] = $data['metadata'][$key];
				}
			}
		}

		$slots[SLOT_ROLE_JSONFORMS_METADATA] = [
			'model' => 'json',
			'content' =>  json_encode( $metadata )
		];

		$processedData = [
			'slots' => $slots,
			'targetTitle' => $targetTitle,
			'isNewPage' => false,
			'metadata' => $metadata,
		];

		$returnData = [
			'targetTitle' => $targetTitle->getFullText(),
			'returnUrl' => $targetTitle->getLocalURL()
		];

		return ResultWrapper::success( [ $processedData, $returnData ] );
	}

}
