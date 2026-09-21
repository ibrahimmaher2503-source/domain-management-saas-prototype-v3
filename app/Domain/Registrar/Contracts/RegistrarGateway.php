<?php

namespace App\Domain\Registrar\Contracts;

use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;

interface RegistrarGateway
{
    public function checkDomain(CheckDomainData $data): DomainAvailability;

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice;
}
