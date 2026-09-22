<?php

namespace App\Domain\Ssl\Contracts;

interface SslProvider
{
    /** @return array<string, string> */
    public function parseCsr(string $product, string $csr): array;

    /** @return list<string> */
    public function getApproverEmails(string $domain): array;

    /** @param array<string, string|int> $request
     * @return array{order_id: string, price: string|null}
     */
    public function orderCertificate(array $request, string $transactionId): array;

    /** @return array{status: string, provider_status: string, approver_email?: string, issued_at: string|null, expires_at: string|null} */
    public function getCertificateOrder(string $orderId): array;

    public function cancelCertificate(string $orderId, string $transactionId): void;

    public function changeApproverEmail(string $orderId, string $email, string $transactionId): void;

    public function resendApproverEmail(string $orderId, string $transactionId): void;

    public function reissueCertificate(string $orderId, string $csr, string $transactionId): void;

    public function resendFulfillmentEmail(string $orderId, string $transactionId): void;
}
