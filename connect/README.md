# Connection Tester

Checks if PHP can connect to a MongoDB server without using the driver.

The script will parse the connection string for one or more hosts and attempt to
connect to each and execute an `isMaster` command. If the connection string
specifies `ssl=true`, each host's TLS peer certificate will additionally be
captured and validated.

Note that this script uses the `openssl` extension, which may use a different
configuration than the TLS library used by the `mongodb` extension and
libmongoc.

## Usage

    $ php connect.php [uri]

The connection string defaults to "'mongodb://127.0.0.1'" if not specified. The
port for each host defaults to 27017 if not specified.

## Testing

    $ php connect.php 'mongodb://buildfest-apac-shard-00-00.p1u76.mongodb.net:27017,buildfest-apac-shard-00-01.p1u76.mongodb.net:27017,buildfest-apac-shard-00-02.p1u76.mongodb.net:27017/?ssl=true&replicaSet=atlas-kmsp7z-shard-0'

## Todo Items

 * Resolve SRV records for `mongodb+srv://` connection strings
 * Check that the certificate common name (CN) matches the hostname
