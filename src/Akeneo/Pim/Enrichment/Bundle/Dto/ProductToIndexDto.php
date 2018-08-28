<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Dto;

use Akeneo\Pim\Enrichment\Component\Product\Model\Completeness;
use Doctrine\Common\Collections\Collection;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueCollection;

/**
 * Specifc DTO to gather product data in order to index it.
 *
 * @author    Benoit Jacquemont <benoit.jacquemont@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductToIndexDto
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $identifier;

    /** @var string */
    protected $familyCode;

    /** @var boolean */
    protected $enabled;

    /** @var array */
    protected $values;

    /** @var array */
    protected $categoryCodes;

    /** @var array */
    protected $groupCodes;

    /** @var string */
    protected $created;

    /** @var string */
    protected $updated;

    /** @var array */
    protected $completenesses;

    public function __construct(
        int $id,
        string $identifier,
        string $familyCode,
        bool $enabled,
        ValueCollection $values,
        array $categoryCodes,
        array $groupCodes,
        string $created,
        string $updated,
        Collection $completenesses
    ) {
        $this->id = $id;
        $this->identifier = $identifier;
        $this->familyCode = $familyCode;
        $this->enabled = $enabled;
        $this->values = $values;
        $this->categoryCodes = $categoryCodes;
        $this->groupCodes = $groupCodes;
        $this->created = $created;
        $this->updated = $updated;
        $this->completenesses = $completenesses;
    }

    public function getId() {
        return $this->id;
    }

    public function getIdentifier() {
        return $this->identifier;
    }

    public function getValues() {
        return $this->values;
    }

    public function getCategoryCodes() {
        return $this->categoryCodes;
    }

    public function getGroupCodes() {
        return $this->groupCodes;
    }

    public function getCompletenesses() {
        return $this->completenesses;
    }
}
