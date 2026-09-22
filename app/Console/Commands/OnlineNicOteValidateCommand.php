<?php

namespace App\Console\Commands;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\RenewDomainData;
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Commands\GetAccountBalanceCommand;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\OnlineNicClient;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Integrations\OnlineNic\Ote\OteSafetyPolicy;
use App\Integrations\OnlineNic\Ote\OteValidationReporter;
use App\Integrations\OnlineNic\Ssl\OnlineNicSslProvider;
use Illuminate\Console\Command;
use Throwable;

final class OnlineNicOteValidateCommand extends Command
{
    protected $signature = 'onlinenic:ote-validate {--read-only} {--writes} {--section=} {--test-domain=} {--csr-file=} {--report=docs/validation/onlinenic-ote-results.md}';

    protected $description = 'Run controlled, server-side OnlineNIC OTE validation';

    public function handle(OteSafetyPolicy $policy): int
    {
        $writes = (bool) $this->option('writes');
        if ($writes && $this->option('read-only')) {
            $this->error('Choose either --read-only or --writes.');

            return self::FAILURE;
        }
        $safety = $policy->check($writes);
        if (! $safety->safe() || ($writes && ! $safety->writesAllowed())) {
            $this->error('Safety gate failed; no OnlineNIC connection was attempted.');
            foreach ($safety->failures() as $failure) {
                $this->line('[FAIL] '.$failure);
            }

            return self::FAILURE;
        }

        $report = new OteValidationReporter;
        $section = strtolower((string) ($this->option('section') ?: 'all'));
        if (! in_array($section, ['all', 'account', 'domain', 'renew', 'ssl', 'writes'], true)) {
            $this->error('Unknown section. Use account, domain, renew, ssl, or writes.');

            return self::FAILURE;
        }
        $client = app(OnlineNicClient::class);
        $registrar = app(RegistrarGateway::class);
        $ssl = app(OnlineNicSslProvider::class);
        $domain = (string) ($this->option('test-domain') ?: config('onlinenic.ote.test_domain'));
        try {
            $client->connect();
            $report->add('Login / session', 'PASS', $client->login()->code, 'session authenticated and reusable');
            $this->readPhase($report, $client, $registrar, $ssl, $domain, $section, (string) ($this->option('csr-file') ?: ''));
            if ($writes && in_array($section, ['all', 'renew', 'writes'], true)) {
                $this->writePhase($report, $registrar, $domain);
            }
        } catch (Throwable $exception) {
            $report->add('Harness execution', 'FAIL', $exception instanceof OnlineNicException ? $exception->providerCode : null, 'validation stopped safely', 'provider/application error; sensitive details omitted');
        } finally {
            try {
                $client->logout();
                $report->add('Logout', 'PASS', null, 'session closed safely');
            } catch (Throwable) {
                $report->add('Logout', 'FAIL', null, 'session close was not confirmed', 'sensitive details omitted');
            }
        }

        $path = base_path((string) $this->option('report'));
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $report->markdown());
        $this->info('Validation report written to '.$this->option('report'));

        return collect($report->rows())->contains('result', 'FAIL') ? self::FAILURE : self::SUCCESS;
    }

    private function readPhase(OteValidationReporter $report, OnlineNicClient $client, RegistrarGateway $registrar, OnlineNicSslProvider $ssl, string $domain, string $section, string $csrFile): void
    {
        if (in_array($section, ['all', 'account'], true)) {
            $this->attempt($report, 'GetAccountBalance', fn () => $client->execute(new GetAccountBalanceCommand), 'balance returned successfully');
        }
        if ($section === 'account') {
            return;
        }
        if ($domain === '') {
            foreach (array_merge(in_array($section, ['all', 'domain'], true) ? ['CheckDomain', 'GetDomainPrice reg', 'GetDomainPrice renew', 'GetDomainPrice transfer', 'InfoDomain', 'InfoDomainExtra', 'GetAuthcode', 'QueryRegTransfer'] : [], in_array($section, ['all', 'ssl'], true) ? ['GetApproverEmailList', 'ParseCSR', 'SSL Info'] : []) as $operation) {
                $report->add($operation, 'BLOCKED', null, 'not attempted', 'No dedicated OTE test resource configured.');
            }

            return;
        }
        if (in_array($section, ['all', 'domain', 'renew'], true)) {
            $this->attempt($report, 'CheckDomain', fn () => $registrar->checkDomain(new CheckDomainData($domain)), 'availability parsed without permanence claim');
            foreach ([['registration', 'GetDomainPrice reg', 1], ['renewal', 'GetDomainPrice renew', 1], ['renewal', 'GetDomainPrice renew 2', 2], ['transfer', 'GetDomainPrice transfer', 1]] as [$operation, $label, $period]) {
                $this->attempt($report, $label, fn () => $registrar->getDomainPrice(new DomainPriceQuery($domain, $operation, $period)), 'price parsed without hardcoded comparison');
            }
            $this->attempt($report, 'InfoDomain / InfoDomainExtra', fn () => $registrar->getDomainInfo($domain), 'domain read parsed; sensitive IDs omitted');
            $this->attempt($report, 'GetAuthcode', fn () => $registrar->getAuthCode($domain), 'auth code returned successfully; value omitted');
        }
        if (in_array($section, ['all', 'domain', 'renew'], true)) {
            $report->add('QueryRegTransfer', 'BLOCKED', null, 'not attempted', 'No separate eligible transfer resource configured.');
        }
        if (in_array($section, ['all', 'ssl'], true)) {
            $this->attempt($report, 'GetApproverEmailList', fn () => $ssl->getApproverEmails($domain), 'approver list parsed; addresses omitted');
            $report->add('SSL Info', 'BLOCKED', null, 'not attempted', 'No OTE SSL order ID configured.');
            if ($csrFile === '' || ! is_file($csrFile)) {
                $report->add('ParseCSR', 'BLOCKED', null, 'not attempted', 'Pass --csr-file with a disposable CSR for this read.');
            } else {
                $csr = file_get_contents($csrFile);
                if (! is_string($csr) || str_contains($csr, 'PRIVATE KEY')) {
                    $report->add('ParseCSR', 'BLOCKED', null, 'not attempted', 'CSR file was missing or contained private-key material.');
                } else {
                    $this->attempt($report, 'ParseCSR', fn () => $ssl->parseCsr((string) config('ssl.products.dv.provider_product', 'RapidSSL'), $csr), 'CSR fields parsed; CSR material omitted');
                }
            }
        }
    }

    private function writePhase(OteValidationReporter $report, RegistrarGateway $registrar, string $domain): void
    {
        if ($domain === '' || ! preg_match('/^domain-saas-ote-[a-z0-9-]+\.com$/i', $domain)) {
            foreach (['CreateDomain', 'UpdateDomainDns', 'UpdateDomainStatus lock', 'UpdateDomainStatus unlock', 'RenewDomain', 'RequestRegTransfer', 'CancelRegTransfer', 'SSL Order', 'SSL maintenance commands'] as $operation) {
                $report->add($operation, 'BLOCKED', null, 'not attempted', 'A dedicated disposable OTE domain/resource is required.');
            }

            return;
        }
        $transactions = app(OnlineNicTransactionIdGenerator::class);
        if ((string) $this->option('section') === 'renew') {
            try {
                $registrar->getDomainInfo($domain);
                $renewal = $registrar->renewDomain(new RenewDomainData($domain, 1), $transactions->generate());
                $report->add('RenewDomain', 'PASS', $renewal->providerCode, 'renewal response returned; expiration must be confirmed by InfoDomain');
                $report->add('InfoDomain after renewal', 'PASS', $registrar->getDomainInfo($domain)->providerCode, 'expiration read after one renewal');
            } catch (Throwable $exception) {
                $report->add('RenewDomain', 'FAIL', $exception instanceof OnlineNicException ? $exception->providerCode : null, 'write stopped; no retry was attempted', 'provider/application error; reconcile with InfoDomain before any retry');
            }
            $report->add('RequestRegTransfer', 'BLOCKED', null, 'not attempted', 'No external eligible transfer resource configured.');
            $report->add('CancelRegTransfer', 'BLOCKED', null, 'not attempted', 'No external eligible transfer resource configured.');
            $report->add('SSL Order', 'BLOCKED', null, 'not attempted', 'SSL ordering requires a separately authorized lifecycle resource.');
            $report->add('SSL maintenance commands', 'BLOCKED', null, 'not attempted', 'No suitable OTE SSL order configured.');

            return;
        }
        $nameservers = array_values(array_filter([(string) config('onlinenic.ote.test_nameserver_1'), (string) config('onlinenic.ote.test_nameserver_2')]));
        $password = (string) config('onlinenic.ote.test_domain_password');
        if (count($nameservers) < 2 || $password === '') {
            $report->add('CreateDomain', 'BLOCKED', null, 'not attempted', 'Two OTE nameservers and a disposable-domain password are required.');

            return;
        }

        try {
            $availability = $registrar->checkDomain(new CheckDomainData($domain));
            if (! $availability->available) {
                $report->add('CreateDomain', 'BLOCKED', null, 'not attempted', 'CheckDomain did not report the disposable name as available.');

                return;
            }
            $registrar->getDomainPrice(new DomainPriceQuery($domain, 'registration', 1));
            $result = $registrar->registerDomain(new DomainRegistrationData($domain, 1, $nameservers, [
                'registrant' => (string) config('onlinenic.registrant_contact_id'),
                'administrative' => (string) config('onlinenic.admin_contact_id'),
                'technical' => (string) config('onlinenic.tech_contact_id'),
                'billing' => (string) config('onlinenic.billing_contact_id'),
            ], $password), $transactions->generate());
            $report->add('CreateDomain', 'PASS', $result->providerCode, 'registration response confirmed; dates omitted');
            $report->add('InfoDomain after registration', 'PASS', $registrar->getDomainInfo($domain)->providerCode, 'registered domain visible to provider');
        } catch (Throwable $exception) {
            $report->add('CreateDomain', 'FAIL', $exception instanceof OnlineNicException ? $exception->providerCode : null, 'write stopped; no retry was attempted', 'provider/application error; reconcile with InfoDomain before any retry');

            return;
        }
        try {
            $registrar->updateNameservers(new UpdateNameserversData($domain, $nameservers), $transactions->generate());
            $report->add('UpdateDomainDns', 'PASS', $registrar->getDomainInfo($domain)->providerCode, 'nameserver write accepted and read back');
            $registrar->setTransferLock(new TransferLockData($domain, true), $transactions->generate());
            $report->add('UpdateDomainStatus lock', 'PASS', $registrar->getDomainInfo($domain)->providerCode, 'lock write accepted and read back');
            $registrar->setTransferLock(new TransferLockData($domain, false), $transactions->generate());
            $report->add('UpdateDomainStatus unlock', 'PASS', $registrar->getDomainInfo($domain)->providerCode, 'unlock write accepted and read back');
            $registrar->getAuthCode($domain);
            $report->add('GetAuthcode after registration', 'PASS', null, 'auth code returned successfully; value omitted');
        } catch (Throwable $exception) {
            $report->add('Domain write follow-up', 'FAIL', $exception instanceof OnlineNicException ? $exception->providerCode : null, 'write sequence stopped; no retry was attempted', 'reconcile with InfoDomain before any retry');
        }
        if (in_array((string) $this->option('section'), ['renew', 'all'], true)) {
            try {
                $renewal = $registrar->renewDomain(new RenewDomainData($domain, 1), $transactions->generate());
                $report->add('RenewDomain', 'PASS', $renewal->providerCode, 'renewal response returned; expiration must be confirmed by InfoDomain');
                $report->add('InfoDomain after renewal', 'PASS', $registrar->getDomainInfo($domain)->providerCode, 'expiration read after one renewal');
            } catch (Throwable $exception) {
                $report->add('RenewDomain', 'FAIL', $exception instanceof OnlineNicException ? $exception->providerCode : null, 'write stopped; no retry was attempted', 'reconcile with InfoDomain before any retry');
            }
        }
        $report->add('RequestRegTransfer', 'BLOCKED', null, 'not attempted', 'No external eligible transfer resource configured.');
        $report->add('CancelRegTransfer', 'BLOCKED', null, 'not attempted', 'No external eligible transfer resource configured.');
        $report->add('SSL Order', 'BLOCKED', null, 'not attempted', 'SSL ordering requires a separately authorized lifecycle resource.');
        $report->add('SSL maintenance commands', 'BLOCKED', null, 'not attempted', 'No suitable OTE SSL order configured.');
    }

    private function attempt(OteValidationReporter $report, string $operation, callable $call, string $success): void
    {
        try {
            $call();
            $report->add($operation, 'PASS', null, $success);
        } catch (Throwable $exception) {
            $report->add($operation, 'FAIL', $exception instanceof OnlineNicException ? $exception->providerCode : null, 'operation failed safely', 'provider/application error; sensitive details omitted');
        }
    }
}
