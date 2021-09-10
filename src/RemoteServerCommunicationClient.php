<?php

namespace Qbil\RpcClient;

interface RemoteServerCommunicationClient
{
    public function query(string $route, ?array $parameters = null): array;
}