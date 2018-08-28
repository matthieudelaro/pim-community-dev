<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Command;

use Akeneo\Pim\Enrich\Dto\Loader\ProductToIndexDtoLoasder;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Refresh;
use Akeneo\Tool\Component\StorageUtils\Indexer\BulkIndexerInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Index products into Elasticsearch
 *
 * @author    Anael Chardan <anael.chardan@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class IndexProductCommand extends ContainerAwareCommand
{
    public const NAME = 'pim:product:index';
    private const BULK_SIZE = 100;
    private const ERROR_CODE_USAGE = 1;

    /** @var ProductToIndexDtoLoader */
    private $productToIndexDtoLoader;

    /** @var BulkIndexerInterface */
    private $bulkProductIndexer;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->addArgument(
                'identifiers',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'List of product identifiers to index',
                []
            )
            ->addOption(
                'all',
                true,
                InputOption::VALUE_NONE,
                'Index all existing products into Elasticsearch'
            )
            ->setDescription('Index all or some products into Elasticsearch');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkIndexesExist();

        $this->productToIndexDtoLoader = $this->getContainer()->get('akeneo.pim.enrichment.dto.loader.product_to_index');
        $this->bulkProductIndexer = $this->getContainer()->get('pim_catalog.elasticsearch.indexer.product');

        $isIndexAll = $input->getOption('all');
        $productIdentifiers = $input->getArgument('identifiers');

        if ($isIndexAll) {
            $totalIndexedProducts = $this->indexAll($output);
        } elseif (0 < count($productIdentifiers)) {
            $totalIndexedProducts = $this->index($output, $productIdentifiers);
        } else {
            $output->writeln('<error>Please specify a list of product identifiers to index or use the flag --all to index all products</error>');

            return self::ERROR_CODE_USAGE;
        }

        $message = sprintf('<info>%d products indexed</info>', $totalIndexedProducts);

        $output->writeln($message);
    }

    /**
     * Indexes all the products in elasticsearch.
     *
     * @param OutputInterface $output
     *
     * @return int
     */
    private function indexAll(OutputInterface $output): int
    {
        $totalElements = (int) $this->productToIndexDtoLoader->countAll();

        $output->writeln(sprintf('<info>%s products to index</info>', $totalElements));

        $lastProductId = 0;
        $progress = 0;

        while (!empty($products = $this->productToIndexDtoLoader->loadAllPaginated($lastProductId, self::BULK_SIZE))) {
            $output->writeln(sprintf(
                'Indexing products %d of %d',
                $progress,
                $totalElements
            ));

//            $this->bulkProductIndexer->indexAll($products, ['index_refresh' => Refresh::disable()]);
            var_dump($products);

            $lastProduct = end($products);
            $lastProductId = $lastProduct->getId();
            $progress += count($products);
        }

        return $totalElements;
    }

    /**
     * Indexes the given list of product identifiers in Elasticsearch.
     *
     * @param OutputInterface $output
     * @param array           $identifiers
     *
     * @return int
     */
    private function index(OutputInterface $output, array $identifiers): int
    {
        $offset = 0;
        $identifierBulk = [];
        $totalProductsIndexed = 0;

        while (count($identifierBulk = array_slice($identifiers, $offset, self::BULK_SIZE)) > 0) {

            $offset += self::BULK_SIZE;

            $products = $this->productToIndexDtoLoader->loadByIdentifiers($identifierBulk);
            $productsCount = count($products);

            if ($productsCount !== count($identifierBulk)) {
                $identifiersFound = [];
                foreach ($products as $product) {
                    $identifiersFound[] = $product->getIdentifier();
                }

                $notFoundIdentifiers = array_diff($identifierBulk, $identifiersFound);
                $output->writeln(sprintf(
                    '<error>Some products were not found for the given identifiers: %s</error>',
                    implode(', ', $notFoundIdentifiers)
                ));
            }

            var_dump($products);
            //$this->bulkProductIndexer->indexAll($products, ['index_refresh' => Refresh::disable()]);
            $totalProductsIndexed += count($products);

            $output->writeln(sprintf(
                '%d products indexed',
                $totalProductsIndexed
            ));
        }

        return $totalProductsIndexed;
    }

    /**
     * @throws \RuntimeException
     */
    private function checkIndexesExist()
    {
        $productClient = $this->getContainer()->get('akeneo_elasticsearch.client.product');
        if (!$productClient->hasIndex()) {
            throw new \RuntimeException(
                sprintf(
                    'The index "%s" does not exist in Elasticsearch.',
                    $this->getContainer()->getParameter('product_index_name')
                )
            );
        }

        $productAndProductModelClient = $this->getContainer()->get(
            'akeneo_elasticsearch.client.product_and_product_model'
        );
        if (!$productAndProductModelClient->hasIndex()) {
            throw new \RuntimeException(
                sprintf(
                    'The index "%s" does not exist in Elasticsearch.',
                    $this->getContainer()->getParameter('product_and_product_model_index_name')
                )
            );
        }
    }
}
