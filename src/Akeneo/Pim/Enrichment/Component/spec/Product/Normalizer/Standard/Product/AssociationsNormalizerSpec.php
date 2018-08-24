<?php

namespace spec\Akeneo\Pim\Enrichment\Component\Product\Normalizer\Standard\Product;

use Akeneo\Pim\Enrichment\Component\Product\Normalizer\Standard\Product\AssociationsNormalizer;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Akeneo\Pim\Enrichment\Component\Product\Model\AssociationInterface;
use Akeneo\Pim\Structure\Component\Model\AssociationTypeInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\GroupInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Pim\Component\Catalog\Query\AssociatedProduct\GetAssociatedProductCodesByProduct;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AssociationsNormalizerSpec extends ObjectBehavior
{
    function let(GetAssociatedProductCodesByProduct $getAssociatedProductCodesByProduct)
    {
        $this->beConstructedWith($getAssociatedProductCodesByProduct);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(AssociationsNormalizer::class);
    }

    function it_is_a_normalizer()
    {
        $this->shouldImplement(NormalizerInterface::class);
    }

    function it_supports_standard_format_and_product_only(
        ProductInterface $product
    ) {
        $this->supportsNormalization($product, 'standard')->shouldReturn(true);
        $this->supportsNormalization($product, 'other_format')->shouldReturn(false);
        $this->supportsNormalization(new \stdClass(), 'standard')->shouldReturn(false);
        $this->supportsNormalization(new \stdClass(), 'other_format')->shouldReturn(false);
    }

    function it_normalizes_a_product_with_no_associations(ProductInterface $product)
    {
        $product->getAllAssociations()->willReturn([]);
        $this->normalize($product, 'standard')->shouldReturn([]);
    }

    function it_normalizes_a_product_associations_with_query_to_find_associated_products_codes(
        ProductInterface $product,
        ProductModelInterface $productModel,
        AssociationInterface $association1,
        AssociationInterface $association2,
        AssociationTypeInterface $associationType1,
        AssociationTypeInterface $associationType2,
        GroupInterface $group1,
        ProductInterface $productAssociated,
        $getAssociatedProductCodesByProduct
    ) {
        $group1->getCode()->willReturn('group_code');
        $associationType1->getCode()->willReturn('XSELL');
        $association1->getAssociationType()->willReturn($associationType1);
        $association1->getGroups()->willReturn([$group1]);
        $association1->getProducts()->willReturn(new ArrayCollection());
        $association1->getProductModels()->willReturn(new ArrayCollection());
        $getAssociatedProductCodesByProduct->getCodes($association1)->willReturn([]);


        $productAssociated->getReference()->willReturn('product_code');
        $productModel->getCode()->willReturn('product_model_code');
        $associationType2->getCode()->willReturn('PACK');
        $associationType2->getId()->willReturn(7);
        $association2->getAssociationType()->willReturn($associationType2);
        $association2->getGroups()->willReturn(new ArrayCollection());
        $association2->getProducts()->willReturn([$productAssociated->getWrappedObject()]);
        $association2->getProductModels()->willReturn(new ArrayCollection([$productModel->getWrappedObject()]));

        $getAssociatedProductCodesByProduct->getCodes($association2)->willReturn(['product_code']);

        $product->getAllAssociations()->willReturn([$association1, $association2]);

        $this->normalize($product, 'standard')->shouldReturn(
            [
                'PACK' => [
                    'groups' => [],
                    'products' => ['product_code'],
                    'product_models' => ['product_model_code'],
                ],
                'XSELL' => [
                    'groups' => ['group_code'],
                    'products' => [],
                    'product_models' => [],
                ]
            ]
        );
    }
}
