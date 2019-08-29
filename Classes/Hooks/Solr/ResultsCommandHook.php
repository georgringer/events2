<?php
declare(strict_types = 1);
namespace JWeiland\Events2\Hooks\Solr;

/*
 * This file is part of the events2 project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetProcessor;
use ApacheSolrForTypo3\Solr\GarbageCollector;
use JWeiland\Events2\Service\EventService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Remove event records from result set, if they are not current anymore.
 */
class ResultsCommandHook implements SearchResultSetProcessor
{
    /**
     * @var EventService
     */
    protected $eventService;

    /**
     * ResultsCommandHook constructor.
     */
    public function __construct()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->eventService = $objectManager->get(EventService::class);
    }

    /**
     * Remove event records from result set, if they are not current anymore.
     * Modifies the given document and returns the modified document as result.
     *
     * @param SearchResultSet $resultSet
     * @return SearchResultSet
     */
    public function process(SearchResultSet $resultSet): SearchResultSet
    {
        if ($resultSet->getAllResultCount() === 0) {
            // when the search does not produce a ResultSet, do nothing
            return $resultSet;
        }

        /** @var SearchResult $searchResult */
        $searchResults = $resultSet->getSearchResults()->getArrayCopy();
        foreach ($searchResults as $key => $searchResult) {
            $uidField = $searchResult->getField('uid');
            $typeField = $searchResult->getField('type');
            if ($typeField['value'] === 'tx_events2_domain_model_event') {
                $nextDate = $this->eventService->getNextDayForEvent((int)$uidField['value']);
                if (!$nextDate instanceof \DateTime) {
                    /** @var GarbageCollector $garbageCollector */
                    $garbageCollector = GeneralUtility::makeInstance(GarbageCollector::class);
                    $garbageCollector->collectGarbage('tx_events2_domain_model_event', (int)$uidField['value']);
                    $resultSet->getSearchResults()->offsetUnset($key);
                } else {
                    $searchResult->setField('nextDay', (int)$nextDate->format('U'));
                    $resultSet->getSearchResults()->offsetSet($key, $searchResult);
                }
            }
        }

        return $resultSet;
    }
}
