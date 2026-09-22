<?php

namespace App\Domain\Registrar\Contracts;

use App\Domain\Registrar\DTOs\AuthCodeResult;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainInfo;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\OperationResult;
use App\Domain\Registrar\DTOs\RegistrationResult;
use App\Domain\Registrar\DTOs\RenewalResult;
use App\Domain\Registrar\DTOs\RenewDomainData;
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Domain\Registrar\DTOs\UpdateNameserversData;

interface RegistrarGateway
{
    public function checkDomain(CheckDomainData $data): DomainAvailability;

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice;

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult;

    public function renewDomain(RenewDomainData $data, string $cltrid): RenewalResult;

    public function getDomainInfo(string $domain): DomainInfo;

    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult;

    public function setTransferLock(TransferLockData $data, string $cltrid): OperationResult;

    public function getAuthCode(string $domain): AuthCodeResult;
}
