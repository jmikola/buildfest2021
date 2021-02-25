<?php

$uri = $argv[1] ?? 'mongodb://127.0.0.1';

$getHosts = function(string $uri) : array {
    if (false === strpos($uri, '://')) {
        return [$uri];
    }

    $parsed = parse_url($uri);

    if (isset($parsed['scheme']) && $parsed['scheme'] !== 'mongodb') {
        // TODO: Resolve SRV records (https://github.com/mongodb/specifications/blob/master/source/initial-dns-seedlist-discovery/initial-dns-seedlist-discovery.rst)
        throw new RuntimeException('Unsupported scheme: ' . $parsed['scheme']);
    }

    $hosts = sprintf('%s:%d', $parsed['host'], $parsed['port'] ?? 27017);

    return explode(',', $hosts);
};

$hosts = $getHosts($uri);
$ssl = stripos(parse_url($uri, PHP_URL_QUERY) ?? '', 'ssl=true') !== false;

$streamWrite = function($stream, string $data) : int {
    for ($written = 0; $written < strlen($data); $written += $fwrite) {
        $fwrite = fwrite($stream, substr($data, $written));

        if (false === $fwrite) {
            return $written;
        }
    }
    return $written;
};

$streamRead = function($stream, $length) : string {
    $contents = '';

    while (!feof($stream) && strlen($contents) < $length) {
        $fread = fread($stream, min($length - strlen($contents), 8192));

        if (false === $fread) {
            return $contents;
        }

        $contents .= $fread;
    }

    return $contents;
};

$connect = function(string $host) use ($ssl, $streamRead, $streamWrite) {
    $uri = sprintf('%s://%s', $ssl ? 'ssl' : 'tcp', $host);
    $context = stream_context_create($ssl ? ['ssl' => ['capture_peer_cert' => true]] : []);
    $client = @stream_socket_client($uri, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);

    if ($client === false) {
        printf("Could not connect to %s: %s\n", $host, $errstr);
        return;
    }

    if ($ssl) {
        $peerCertificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;

        if (!isset($peerCertificate)) {
            printf("Could not capture peer certificate for %s\n", $host);
            return;
        }

        $certificateProperties = openssl_x509_parse($peerCertificate);

        // TODO: Check that the certificate common name (CN) matches the hostname
        $now = new DateTime;
        $validFrom = DateTime::createFromFormat('U', $certificateProperties['validFrom_time_t']);
        $validTo = DateTime::createFromFormat('U', $certificateProperties['validTo_time_t']);
        $isValid = $now >= $validFrom && $now <= $validTo;

        printf("Peer certificate for %s is %s\n", $host, $isValid ? 'valid' : 'expired');

        if (!$isValid) {
            printf("  Valid from %s to %s\n", $validFrom->format('c'), $validTo->format('c'));
        }
    }

    $request = pack(
            'Va*xVVa*',
            1 << 2 /* slaveOk */,
            'admin.$cmd', /* namespace */
            0, /* numberToSkip */
            1, /* numberToReturn */
            hex2bin('130000001069734d6173746572000100000000'), /* { "isMaster": 1 } */
    );
    $requestlength = 16 /* MsgHeader length */ + strlen($request);
    $header = pack('V4', $requestlength, 0 /* requestID */, 0 /* responseTo */, 2004 /* OP_QUERY */);

    if ($requestlength !== $streamWrite($client, $header . $request)) {
        printf("Could not write request to %s\n", $host);
        return;
    }

    $data = $streamRead($client, 4);

    if (false === $data || 4 !== strlen($data)) {
        printf("Could not read response header from %s\n", $host);
        return;
    }

    list(,$responseLength) = unpack('V', $data);

    $data = $streamRead($client, $responseLength - 4);

    if (false === $data || ($responseLength - 4) !== strlen($data)) {
        printf("Could not read response from %s\n", $host);
        return;
    }

    // $reply = MongoDB\BSON\toPHP(substr($data, 12 /* remaining MsgHeader fields */ + 20 /* OP_REPLY fields */));
    printf("Successfully received response from %s\n", $host);
};

printf("Found %d host(s) in the URI. Will attempt to connect to each.\n", count($hosts));

foreach ($hosts as $host) {
    echo "\n";
    $connect($host);
}
