<?php

namespace Akeneo\Pim\Enrichment\Component\Product\Model;

use Akeneo\Channel\Component\Model\ChannelInterface;
use Akeneo\Channel\Component\Model\LocaleInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\CompletenessInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Doctrine\Common\Collections\Collection;

/**
 * Abstract product completeness entity
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
abstract class AbstractCompleteness implements CompletenessInterface
{
    /** @var int|string */
    protected $id;

    /** @var int */
    protected $product;

    /** @var int */
    protected $locale;

    /** @var int */
    protected $channel;

    /** @var int */
    protected $ratio;

    /** @var int */
    protected $missingCount;

    /** @var int */
    protected $requiredCount;

    /** @var Collection */
    protected $missingAttributes;

    public function __construct(
        int $productId,
        int $channelId,
        int $localeId,
        Collection $missingAttributes,
        int $missingCount,
        int $requiredCount
    ) {
        $this->product = $productId;
        $this->channel = $channelId;
        $this->locale = $localeId;
        $this->missingAttributes = $missingAttributes;
        $this->missingCount = $missingCount;
        $this->requiredCount = $requiredCount;

        $this->ratio = (int) floor(100 * ($this->requiredCount - $this->missingCount) / $this->requiredCount);
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * {@inheritdoc}
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * {@inheritdoc}
     */
    public function getRatio()
    {
        return $this->ratio;
    }

    /**
     * {@inheritdoc}
     */
    public function getMissingCount()
    {
        return $this->missingCount;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredCount()
    {
        return $this->requiredCount;
    }

    /**
     * {@inheritdoc}
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * {@inheritdoc}
     */
    public function getMissingAttributes()
    {
        return $this->missingAttributes;
    }
}
