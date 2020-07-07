<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\SemanticVersionChecker\Analyzer\Mftf;

use Magento\SemanticVersionChecker\MftfReport;
use Magento\SemanticVersionChecker\Operation\Mftf\Metadata\MetadataAdded;
use Magento\SemanticVersionChecker\Operation\Mftf\Metadata\MetadataChildRemoved;
use Magento\SemanticVersionChecker\Operation\Mftf\Metadata\MetadataRemoved;
use Magento\SemanticVersionChecker\Scanner\MftfScanner;
use PHPSemVerChecker\Registry\Registry;
use PHPSemVerChecker\Report\Report;

class MetadataAnalyzer extends AbstractEntityAnalyzer
{
    const MFTF_DATA_TYPE = 'operation';
    const MFTF_DATA_DIRECTORY = '/Mftf/Test/';

    /**
     * MFTF test.xml analyzer
     *
     * @param Registry $registryBefore
     * @param Registry $registryAfter
     * @return Report
     */
    public function analyze(Registry $registryBefore, Registry $registryAfter)
    {
        $beforeEntities = $registryBefore->data[MftfScanner::MFTF_ENTITY] ?? [];
        $afterEntities = $registryAfter->data[MftfScanner::MFTF_ENTITY] ?? [];

        foreach ($beforeEntities as $module => $entities) {
            $this->findAddedEntitiesInModule(
                $entities,
                $afterEntities[$module] ?? [],
                self::MFTF_DATA_TYPE,
                $this->getReport(),
                MetadataAdded::class,
                $module . '/ActionGroup'
            );
            foreach ($entities as $entityName => $beforeEntity) {
                if ($beforeEntity['type'] !== self::MFTF_DATA_TYPE) {
                    continue;
                }
                $operationTarget = $module . '/Metadata/' . $entityName;
                $filenames = implode(", ", $beforeEntity['filePaths']);

                // Validate section still exists
                if (!isset($afterEntities[$module][$entityName])) {
                    $operation = new MetadataRemoved($filenames, $operationTarget);
                    $this->getReport()->add(MftfReport::MFTF_REPORT_CONTEXT, $operation);
                    continue;
                }

                // Build simple metadata tree for comparison
                $this->recursiveCompare(
                    $beforeEntity,
                    $afterEntities[$module][$entityName],
                    $operationTarget,
                    $filenames,
                    $this->getReport()
                );
            }
        }
        return $this->getReport();
    }

    /**
     * Compares child xml elements of entity for parity, as well as child of child elements
     *
     * @param array $beforeEntity
     * @param array $afterEntity
     * @param string $operationTarget
     * @param string $filenames
     * @param Report $report
     * @return void
     */
    public function recursiveCompare($beforeEntity, $afterEntity, $operationTarget, $filenames, $report)
    {
        $beforeChildren = $beforeEntity['value'] ?? [];
        if (!is_array($beforeChildren)) {
            return;
        }
        foreach ($beforeChildren as $beforeChild) {
            $beforeType = $beforeChild['name'];
            $beforeFieldKey = $beforeChild['attributes']['key'] ?? null;
            $afterFound = null;
            $afterChildren = $afterEntity['value'] ?? [];
            foreach ($afterChildren as $afterChild) {
                if ($afterChild['name'] !== $beforeType) {
                    continue;
                }
                $afterFieldKey = $afterChild['attributes']['key'] ?? null;
                if ($afterFieldKey === $beforeFieldKey) {
                    $afterFound = $afterChild;
                    break;
                }
            }
            if ($afterFound === null) {
                $operation = new MetadataChildRemoved($filenames, $operationTarget . '/' . $beforeFieldKey);
                $report->add(MftfReport::MFTF_REPORT_CONTEXT, $operation);
            } else {
                $this->recursiveCompare(
                    $beforeChild,
                    $afterFound,
                    $operationTarget . '/' . $beforeFieldKey,
                    $filenames,
                    $report
                );
            }
        }
    }
}
