<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductListingLoader
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        SystemConfigService $systemConfigService,
        Connection $connection
    ) {
        $this->repository = $repository;
        $this->systemConfigService = $systemConfigService;
        $this->connection = $connection;
    }

    public function load(Criteria $origin, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = clone $origin;

        $this->addGrouping($criteria);
        $this->handleAvailableStock($criteria, $context);

        $ids = $this->repository->searchIds($criteria, $context);

        // no products found, no need to continue
        if (empty($ids->getIds())) {
            return new EntitySearchResult(
                0,
                new ProductCollection(),
                new AggregationResultCollection(),
                $origin,
                $context->getContext()
            );
        }

        $aggregations = $this->repository->aggregate($criteria, $context);

        $variantIds = $ids->getIds();

        if (!$this->hasOptionFilter($criteria)) {
            $variantIds = $this->resolvePreviews($ids->getIds(), $context);
        }

        $read = $criteria->cloneForRead($variantIds);

        $entities = $this->repository->search($read, $context);

        return new EntitySearchResult(
            $ids->getTotal(),
            $entities->getEntities(),
            $aggregations,
            $origin,
            $context->getContext()
        );
    }

    private function hasOptionFilter(Criteria $criteria): bool
    {
        $fields = $criteria->getFilterFields();

        $fields = array_map(function (string $field) {
            return preg_replace('/^product./', '', $field);
        }, $fields);

        if (\in_array('options.id', $fields, true)) {
            return true;
        }

        if (\in_array('optionIds', $fields, true)) {
            return true;
        }

        return false;
    }

    private function addGrouping(Criteria $criteria): void
    {
        $criteria->addGroupField(new FieldGrouping('displayGroup'));

        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('displayGroup', null)]
            )
        );
    }

    private function handleAvailableStock(Criteria $criteria, SalesChannelContext $context): void
    {
        $salesChannelId = $context->getSalesChannel()->getId();

        $hide = $this->systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if (!$hide) {
            return;
        }

        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('product.isCloseout', true),
                    new EqualsFilter('product.available', false),
                ]
            )
        );
    }

    private function resolvePreviews(array $ids, SalesChannelContext $context): array
    {
        $ids = array_combine($ids, $ids);

        $config = $this->connection->fetchAll(
            'SELECT parent.configurator_group_config,
                        LOWER(HEX(parent.main_variant_id)) as mainVariantId,
                        LOWER(HEX(child.id)) as id
             FROM product as child
                INNER JOIN product as parent
                    ON parent.id = child.parent_id
                    AND parent.version_id = child.version_id
             WHERE child.version_id = :version
             AND child.id IN (:ids)',
            [
                'ids' => Uuid::fromHexToBytesList(array_values($ids)),
                'version' => Uuid::fromHexToBytes($context->getContext()->getVersionId()),
            ],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $mapping = [];

        foreach ($config as $item) {
            if ($item['mainVariantId']) {
                $mapping[$item['id']] = $item['mainVariantId'];
            }
        }

        // now we have a mapping for "child => main variant"
        if (empty($mapping)) {
            return $ids;
        }

        // filter inactive and not available variants
        $criteria = new Criteria(array_values($mapping));
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannel()->getId()));
        $this->handleAvailableStock($criteria, $context);

        $available = $this->repository->searchIds($criteria, $context);

        // replace existing ids with main variant id
        $sorted = [];
        foreach ($ids as $id) {
            // id has no mapped main_variant - keep old id
            if (!isset($mapping[$id])) {
                $sorted[] = $id;

                continue;
            }

            // get access to main variant id over the fetched config mapping
            $main = $mapping[$id];

            // main variant is configured but not active/available - keep old id
            if (!$available->has($main)) {
                $sorted[] = $id;

                continue;
            }

            // main variant is configured and available - add main variant id
            if (!in_array($main, $sorted, true)) {
                $sorted[] = $main;
            }
        }

        return $sorted;
    }
}
