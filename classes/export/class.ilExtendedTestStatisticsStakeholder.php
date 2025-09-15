<?php

declare(strict_types=1);

use ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

class ilExtendedTestStatisticsStakeholder extends AbstractResourceStakeholder
{
    public function getId(): string
    {
        return 'extended_test_statistics';
    }

    public function getOwnerOfNewResources(): int
    {
        return $this->default_owner;
    }
}
