<?php

namespace QbilSoftware\RpcClient;

use IXR\Client\Client;
use IXR\Client\ClientSSL;
use JsonException;

class RpcClient implements RemoteServerCommunicationClient
{
    private const END_POINT_PREFIX = 'qbil.bwmanage.';

    private const HASHING_ALGORITHM = 'sha256';

    protected Client $client;

    protected string $rpcServerSecret;

    public function __construct(string $rpcServerUri, string $rpcServerSecret)
    {
        if (self::isSecureUri($rpcServerUri)) {
            $this->client = new ClientSSL($rpcServerUri);
            $this->client->setTimeOut(15);
        } else {
            $this->client = new Client($rpcServerUri);
        }
        $this->rpcServerSecret = $rpcServerSecret;
    }

    final public function query(string $route, ?array $parameters = null): array
    {
        $parametersWithSecret = \array_merge(
            $parameters ?? [],
            ['command_secret' => $this->getCommandSecret($parameters ?? [])]
        );

        $this->client->query(self::END_POINT_PREFIX.$route, $parametersWithSecret);

        if ($this->client->isError()) {
            return [
                'status' => 'FAIL',
                'message' => $this->client->getErrorMessage(),
            ];
        }

        $response = $this->client->getResponse() ?? [];

        if (is_scalar($response)) {
            return [$response];
        }

        return $response;
    }

    private static function isSecureUri(string $uri): bool
    {
        return \str_starts_with($uri, 'https');
    }

    /**
     * Get the command secret, it is an hmac of the command parameters and the shared rpc secret.
     *
     * @param array<string, mixed> $parameters
     *
     * @throws JsonException
     */
    private function getCommandSecret(array $parameters): string
    {
        $dataToBeHashed = \json_encode(
            \array_map(
                static fn ($parameter) => \is_string($parameter)
                    ? \trim($parameter)
                    : $parameter,
                $parameters
            ),
            \JSON_THROW_ON_ERROR
        );

        return \hash_hmac(self::HASHING_ALGORITHM, $dataToBeHashed, $this->rpcServerSecret);
    }
}
