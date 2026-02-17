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

// @credits: https://github.com/Open-CSP/WSSlots/blob/master/src/WSSlots.php
// @credits: https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/VisualData/+/refs/heads/master/includes/VisualData.php

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Content\TextContent;
use MediaWiki\Content\ContentHandler;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\User\User;
use MediaWiki\Storage\PageUpdater;
use Psr\Log\LoggerInterface;

class SlotEditor
{
    private SlotRoleRegistry $slotRegistry;

    private User $user;
    private /* WikiPage|MediaWiki\Page\WikiPage */ $wikiPage;
    private $title;
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->slotRegistry = MediaWikiServices::getInstance()->getSlotRoleRegistry();
        $this->logger = LoggerFactory::getInstance("JsonForms");
    }

    private function shouldDeletePage(
        ?RevisionRecord $oldRevision,
        array $slotUpdates
    ): bool {
        if (!$oldRevision) {
            return false;
        }

        foreach ($slotUpdates as $data) {
            if (
                !array_key_exists("content", $data) ||
                $data["content"] !== ""
            ) {
                return false;
            }
        }

        $existingSlots = array_keys($oldRevision->getSlots()->getSlots());
        $updatedSlots = array_keys($slotUpdates);

        return count(array_diff($existingSlots, $updatedSlots)) === 0;
    }

    protected function beforeSave(
        /* WikiPage|MediaWiki\Page\WikiPage */ $wikiPage,
        PageUpdater $pageUpdater,
        array $slotUpdates,
        ?RevisionRecord $oldRevision
    ): bool {
        // Default: no-op

        return true;
    }

    public function editSlots(
        User $user,
        /* WikiPage|MediaWiki\Page\WikiPage */ $wikiPage,
        array $slotUpdates,
        string $summary = "",
        bool $append = false,
        string $watchlist = "",
        bool $prepend = false,
        bool $bot = false,
        bool $minor = false,
        bool $createonly = false,
        bool $nocreate = false,
        bool $suppress = false
    ) {
        $title = $wikiPage->getTitle();

        if (!$title) {
            $this->logger->alert("Invalid WikiPage: missing Title");
            return [wfMessage("jsonforms-error-invalid-wikipage-object")];
        }

        // bind request-scoped state
        $this->user = $user;
        $this->title = $title;
        $this->wikiPage = $wikiPage;

        if ($this->shouldSkipEdit($createonly, $nocreate)) {
            return true;
        }

        $pageUpdater = $wikiPage->newPageUpdater($user);
        $oldRevision = $wikiPage->getRevisionRecord();

        if ($this->shouldDeletePage($oldRevision, $slotUpdates)) {
            $reason = "cleared content";
            \JsonForms::deletePage($wikiPage, $user, $reason);
            return true;
        }

        foreach ($slotUpdates as $slotName => $slotData) {
            $result = $this->applySlotUpdate(
                $pageUpdater,
                $oldRevision,
                $slotName,
                $slotData,
                $append,
                $prepend
            );

            if ($result !== true) {
                return $result;
            }
        }

        $this->ensureMainSlotOnCreate($pageUpdater, $oldRevision, $slotUpdates);

        if (
            !$this->beforeSave(
                $this->wikiPage,
                $pageUpdater,
                $slotUpdates,
                $oldRevision
            )
        ) {
            return false;
        }

        $this->saveRevision($pageUpdater, $summary, $bot, $minor, $suppress);

        $this->updateWatchlist($title, $watchlist);
        $this->maybePurge($pageUpdater);

        return true;
    }

    private function applySlotUpdate(
        PageUpdater $pageUpdater,
        ?RevisionRecord $oldRevision,
        string $slotName,
        array $slotData,
        bool $append,
        bool $prepend
    ) {
        if (!$this->slotRegistry->isDefinedRole($slotName)) {
            return [
                wfMessage("jsonforms-apierror-unknownslot", $slotName),
                "unknownslot",
            ];
        }

        $text = (string) ($slotData["content"] ?? "");
        $model = $slotData["model"] ?? null;

        if ($append || $prepend) {
            $text = $this->applyAppendPrepend(
                $slotName,
                $text,
                $append,
                $prepend
            );

            if ($text === null) {
                return [wfMessage("apierror-appendnotsupported")];
            }
        }

        if ($text === "" && $slotName !== SlotRecord::MAIN) {
            $pageUpdater->removeSlot($slotName);
            return true;
        }

        $modelId =
            $model ??
            $this->resolveModelId($oldRevision, $slotName, $this->title);

        $content = ContentHandler::makeContent($text, $this->title, $modelId);
        $pageUpdater->setContent($slotName, $content);

        if ($slotName !== SlotRecord::MAIN) {
            $pageUpdater->addTag("jsonforms-slot-edit");
        }

        return true;
    }

    private function getSlotContent(string $slot): ?Content
    {
        $revisionRecord = $this->wikiPage->getRevisionRecord();

        if ($revisionRecord === null || !$revisionRecord->hasSlot($slot)) {
            return null;
        }

        return $revisionRecord->getContent($slot);
    }

    private function applyAppendPrepend(
        string $slotName,
        string $text,
        bool $append,
        bool $prepend
    ): ?string {
        $content = $this->getSlotContent($slotName);

        if (!$content) {
            return $text;
        }

        if (!($content instanceof TextContent)) {
            return null;
        }

        $existing = $content->serialize();

        if ($append) {
            return $existing . $text;
        }

        if ($prepend) {
            return $text . $existing;
        }

        return $text;
    }

    private function resolveModelId(
        ?RevisionRecord $oldRevision,
        string $slotName
    ): string {
        if ($oldRevision && $oldRevision->hasSlot($slotName)) {
            return $oldRevision
                ->getSlot($slotName)
                ->getContent()
                ->getContentHandler()
                ->getModelID();
        }

        return $this->slotRegistry
            ->getRoleHandler($slotName)
            ->getDefaultModel($this->title);
    }

    private function ensureMainSlotOnCreate(
        PageUpdater $pageUpdater,
        ?RevisionRecord $oldRevision,
        array $slotUpdates
    ): void {
        if ($oldRevision === null && !isset($slotUpdates[SlotRecord::MAIN])) {
            $content = ContentHandler::makeContent("", $this->title);
            $pageUpdater->setContent(SlotRecord::MAIN, $content);
        }
    }

    private function saveRevision(
        PageUpdater $pageUpdater,
        string $summary,
        bool $bot,
        bool $minor,
        bool $suppress
    ): void {
        $flags = EDIT_INTERNAL;

        if ($bot) {
            $flags |= EDIT_FORCE_BOT;
        }
        if ($minor) {
            $flags |= EDIT_MINOR;
        }
        if ($suppress) {
            $flags |= EDIT_SUPPRESS_RC;
        }

        $comment = CommentStoreComment::newUnsavedComment($summary);
        $pageUpdater->saveRevision($comment, $flags);
    }

    private function shouldSkipEdit(bool $createonly, bool $nocreate): bool
    {
        return ($this->title->exists() && $createonly) ||
            (!$this->title->exists() && $nocreate);
    }

    private function maybePurge(PageUpdater $pageUpdater): void
    {
        if (
            !$pageUpdater->isUnchanged() &&
            MediaWikiServices::getInstance()
                ->getMainConfig()
                ->get("JsonFormsDoPurge")
        ) {
            $comment = CommentStoreComment::newUnsavedComment("");
            $updater = $this->wikiPage->newPageUpdater($this->user);
            $updater->saveRevision(
                $comment,
                EDIT_SUPPRESS_RC | EDIT_AUTOSUMMARY
            );
        }
    }

    private function getWatchlistValue(string $watchlist): bool
    {
        $services = MediaWikiServices::getInstance();

        if (method_exists(MediaWikiServices::class, "getWatchlistManager")) {
            // >=1.37
            $userWatching = $services
                ->getWatchlistManager()
                ->isWatchedIgnoringRights($this->user, $this->title);
        } else {
            $userWatching = $services
                ->getWatchedItemStore()
                ->isWatched($this->user, $this->title);
        }

        $userOptionsLookup = $services->getUserOptionsLookup();

        switch ($watchlist) {
            case "watch":
                return true;

            case "unwatch":
                return false;

            case "preferences":
                // If the user is already watching, don't bother checking
                if ($userWatching) {
                    return true;
                }
                // If the user is a bot, act as 'nochange' to avoid big watchlists on single users
                if ($user->isBot()) {
                    return false;
                }
                return $userOptionsLookup->getBoolOption(
                    $this->user,
                    "watchdefault"
                ) ||
                    ($userOptionsLookup->getBoolOption(
                        $this->user,
                        "watchcreations"
                    ) &&
                        !$title->exists());

            // case 'nochange':
            default:
                return $userWatching;
        }
    }

    private function updateWatchlist(string $watchlist): void
    {
        $watch = $this->getWatchlistValue($watchlist);

        $services = MediaWikiServices::getInstance();

        if (method_exists($services, "getWatchlistManager")) {
            $services
                ->getWatchlistManager()
                ->setWatch($watch, $this->user, $this->title);
            return;
        }

        $store = $services->getWatchedItemStore();

        if ($watch) {
            $store->addWatch($this->user, $this->title);
        } else {
            $store->removeWatch($this->user, $this->title);
        }

        $this->user->invalidateCache();
    }
}

