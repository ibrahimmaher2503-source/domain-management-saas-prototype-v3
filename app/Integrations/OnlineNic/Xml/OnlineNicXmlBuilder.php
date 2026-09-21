<?php

namespace App\Integrations\OnlineNic\Xml;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

final class OnlineNicXmlBuilder
{
    /** @param array<string, scalar|null> $payload */
    public function build(string $category, string $action, array $payload, string $transactionId, string $checksum): string
    {
        if ($category === '' || $action === '' || $transactionId === '') {
            throw new InvalidArgumentException('OnlineNIC envelope fields cannot be empty.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;
        $request = $document->createElement('request');
        $document->appendChild($request);
        $this->append($document, $request, 'category', $category);
        $this->append($document, $request, 'action', $action);
        $params = $document->createElement('params');
        $request->appendChild($params);
        foreach ($payload as $name => $value) {
            $param = $document->createElement('param');
            $param->setAttribute('name', $name);
            $param->appendChild($document->createTextNode((string) $value));
            $params->appendChild($param);
        }
        $this->append($document, $request, 'cltrid', $transactionId);
        $this->append($document, $request, 'chksum', $checksum);

        return $document->saveXML();
    }

    private function append(DOMDocument $document, DOMElement $parent, string $name, string $value): void
    {
        $parent->appendChild($document->createElement($name))->appendChild($document->createTextNode($value));
    }
}
