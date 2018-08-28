<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Dto\Loader;

use Akeneo\Pim\Enrichment\Bundle\Dto\ProductToIndexDto;
use Akeneo\Pim\Enrichment\Component\Product\Factory\ValueCollectionFactoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\Completeness;
use Akeneo\Tool\Bundle\StorageUtilsBundle\Doctrine\TableNameBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueCollection;
use Doctrine\DBAL\Connection;

/**
 * Specifc DTO to gather product data in order to index it.
 *
 * @author    Benoit Jacquemont <benoit.jacquemont@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductToIndexDtoLoader
{
    /** @var string */
    protected $productTable;

    /** @var string */
    protected $familyTable;

    /** @var string */
    protected $categoryJoinTable;

    /** @var string */
    protected $categoryTable;

    /** @var string */
    protected $groupJoinTable;

    /** @var string */
    protected $groupTable;

    /** @var string */
    protected $completenessTable;

    /** @var Connection */
    protected $connection;

    /** @var ValueCollectionFactoryInterface */
    protected $valueCollectionFactory;

    public function __construct(
        Connection $connection,
        ValueCollectionFactoryInterface $valueCollectionFactory,
        TableNameBuilder $tableNameBuilder
    ) {
        $this->connection = $connection;
        $this->valueCollectionFactory = $valueCollectionFactory;

        $this->productTable = $tableNameBuilder->getTableName('pim_catalog.entity.product.class');
        $this->familyTable = $tableNameBuilder->getTableName('pim_catalog.entity.family.class');

        $this->categoryJoinTable = $tableNameBuilder->getTableName('pim_catalog.entity.product.class', 'categories');
        $this->categoryTable = $tableNameBuilder->getTableName('pim_catalog.entity.category.class');

        $this->groupJoinTable = $tableNameBuilder->getTableName('pim_catalog.entity.product.class', 'groups');
        $this->groupTable = $tableNameBuilder->getTableName('pim_catalog.entity.group.class');

        $this->completenessTable = $tableNameBuilder->getTableName('pim_catalog.entity.completeness.class');
    }

    /**
     * Load one product to index DTO from DB from the provided identifier
     *
     * If no product is found, return null
     */
    public function loadByIdentifier(string $identifier): ?ProductToIndexDto
    {
        $results = $this->loadByIdentifiers([$identifier]);

        if (count($results) === 1) {
            return reset($results);
        } else {
            return null;
        }
    }

    /**
     * Load several DTO based on their idendifiers
     */
    public function loadByIdentifiers(array $identifiers): array
    {
        $dtos = [];

        $productDataSql = sprintf("
            SELECT
                p.id, p.identifier, p.is_enabled, p.raw_values, p.created, p.updated,
                f.code AS family_code,
                JSON_ARRAYAGG(c.code) AS category_codes,
                JSON_ARRAYAGG(g.code) AS group_codes,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'locale_id', comp.locale_id,
                        'channel_id', comp.channel_id,
                        'missing_count', comp.missing_count,
                        'required_count', comp.required_count
                    )
                ) AS completenesses
              FROM %s p
                JOIN %s f ON f.id = p.family_id
                LEFT JOIN %s cp ON cp.product_id = p.id
                LEFT JOIN %s c ON c.id = cp.category_id
                LEFT JOIN %s gp ON gp.product_id = p.id
                LEFT JOIN %s g ON g.id = gp.group_id
                LEFT JOIN %s comp ON comp.product_id = p.id
              WHERE p.identifier IN (?)
              GROUP BY p.id",
            $this->productTable,
            $this->familyTable,
            $this->categoryJoinTable,
            $this->categoryTable,
            $this->groupJoinTable,
            $this->groupTable,
            $this->completenessTable
        );

        $stmt = $this->connection->executeQuery($productDataSql, [$identifiers], [Connection::PARAM_STR_ARRAY]);

        while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $dtos[] = $this->convertRowToDto($row);
        }

        return $dtos;
    }

    /**
     * Load all dtos from the provided id up to size elements
     */
    public function loadAllPaginated(int $fromId, int $size): array
    {
        $dtos = [];

        $productDataSql = sprintf("
            SELECT
                p.id, p.identifier, p.is_enabled, p.raw_values, p.created, p.updated,
                f.code AS family_code,
                JSON_ARRAYAGG(c.code) AS category_codes,
                JSON_ARRAYAGG(g.code) AS group_codes,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'locale_id', comp.locale_id,
                        'channel_id', comp.channel_id,
                        'missing_count', comp.missing_count,
                        'required_count', comp.required_count
                    )
                ) AS completenesses
              FROM %s p
                JOIN %s f ON f.id = p.family_id
                LEFT JOIN %s cp ON cp.product_id = p.id
                LEFT JOIN %s c ON c.id = cp.category_id
                LEFT JOIN %s gp ON gp.product_id = p.id
                LEFT JOIN %s g ON g.id = gp.group_id
                LEFT JOIN %s comp ON comp.product_id = p.id
              WHERE p.id > ?
              GROUP BY p.id
              ORDER BY p.id
              LIMIT ?",
            $this->productTable,
            $this->familyTable,
            $this->categoryJoinTable,
            $this->categoryTable,
            $this->groupJoinTable,
            $this->groupTable,
            $this->completenessTable
        );

        $stmt = $this->connection->executeQuery(
            $productDataSql,
            [$fromId, $size],
            [\PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $dtos[] = $this->convertRowToDto($row);
        }

        return $dtos;
    }

    public function countAll():int
    {
        $productCountSql = sprintf("
            SELECT
                COUNT(1) AS products_count
              FROM %s p",
            $this->productTable
        );

        return (int) $this->connection->fetchColumn($productCountSql);
    }

    /**
     * Convert a result row to a ProductToIndexDto
     */
    protected function convertRowToDto(array $row): ProductToIndexDto
    {
        $categoryCodes = $this->cleanupArray(json_decode($row['category_codes'], true));
        $groupCodes = $this->cleanupArray(json_decode($row['group_codes'], true));

        $rawCompletenesses = json_decode($row['completenesses'], true);
        $rawValues = json_decode($row['raw_values'], true);

        return new ProductToIndexDto(
            (int) $row['id'],
            $row['identifier'],
            $row['family_code'],
            ($row['is_enabled'] == 1),
            $this->populateValues($rawValues),
            $categoryCodes,
            $groupCodes,
            $row['created'],
            $row['updated'],
            $this->populateCompletenesses((int) $row['id'], $rawCompletenesses)
        );
    }

    /**
     * Populate completeness objects for indexing purpose
     */
    protected function populateCompletenesses(int $productId, array $rawCompletenesses): Collection
    {
        $completenesses = [];

        foreach ($rawCompletenesses as $rawCompleteness) {
            if (null != $rawCompleteness) {
                $completeness = new Completeness(
                    $productId,
                    $rawCompleteness['channel_id'],
                    $rawCompleteness['locale_id'],
                    new ArrayCollection(),
                    $rawCompleteness['missing_count'],
                    $rawCompleteness['required_count']
                );

                $completenesses[] = $completeness;
            }
        }

        return new ArrayCollection($completenesses);
    }

    /**
     * Populate value object for indexing purpose
     */
    protected function populateValues(array $rawValues): ValueCollection
    {
//        return new ValueCollection();
        return $this->valueCollectionFactory->createFromStorageFormat($rawValues);
    }

    /**
     * Cleanup arrays to remove duplicate and null value
     */
    protected function cleanupArray(array $toClean): array {
        $toClean = array_unique($toClean);

        return array_filter($toClean, function($v) { return null !== $v; });
    }
}
