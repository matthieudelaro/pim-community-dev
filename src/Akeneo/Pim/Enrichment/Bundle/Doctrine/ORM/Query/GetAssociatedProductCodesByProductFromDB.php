<?php

namespace Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Query;

use Akeneo\Pim\Enrichment\Component\Product\Model\AssociationInterface;
use Doctrine\ORM\EntityManagerInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\GetAssociatedProductCodesByProduct;

class GetAssociatedProductCodesByProductFromDB implements GetAssociatedProductCodesByProduct
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var string */
    private $associationClass;

    public function __construct(EntityManagerInterface $entityManager, $associationClass)
    {
        $this->entityManager = $entityManager;
        $this->associationClass = $associationClass;
    }

    /**
     * {@inheritdoc}
     */
    public function getCodes(AssociationInterface $association)
    {
        $associations = $this->entityManager->createQueryBuilder()
            ->select('p.identifier')
            ->from(get_class($association), 'a')
            ->innerJoin('a.products', 'p')
            ->andWhere('a.id = :associationId')
            ->setParameters([
                'associationId' => $association->getId(),
            ])
            ->orderBy('p.identifier')
            ->getQuery()
            ->getResult();

        return array_map(function (array $association) {
            return $association['identifier'];
        }, $associations);
    }
}
