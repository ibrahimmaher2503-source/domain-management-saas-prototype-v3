<?php

namespace App\Domain\Registrar\Contracts;

use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainInfo;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\RegistrationResult;

interface RegistrarGateway
{
    public function checkDomain(CheckDomainData $data): DomainAvailability;

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice;

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult;

    public function getDomainInfo(string $domain): DomainInfo;
}
