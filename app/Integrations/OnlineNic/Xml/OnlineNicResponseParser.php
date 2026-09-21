<?php

namespace App\Integrations\OnlineNic\Xml;

use App\Integrations\OnlineNic\Exceptions\InvalidProviderResponse;
use App\Integrations\OnlineNic\OnlineNicResponse;
use SimpleXMLElement;

final class OnlineNicResponseParser
{
    public function parse(string $xml): OnlineNicResponse
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);
        if (! $root instanceof SimpleXMLElement || $root->getName() !== 'response') {
            throw new InvalidProviderResponse('OnlineNIC returned malformed XML.');
        }

        $data = [];
        foreach ($root->resData->data ?? [] as $item) {
            $name = (string) ($item['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $value = (string) $item;
            if (array_key_exists($name, $data)) {
                $data[$name] = is_array($data[$name]) ? [...$data[$name], $value] : [$data[$name], $value];
            } else {
                $data[$name] = $value;
            }
        }

        return new OnlineNicResponse(
            (int) $root->code,
            (string) $root->msg,
            (string) $root->cltrid,
            (string) $root->svtrid,
            $data,
            in_array((int) $root->code, [1001, 1300, 1301], true) ? 'pending' : ((int) $root->code === 1000 ? 'completed' : 'rejected'),
        );
    }
}
