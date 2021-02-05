# stitchdata-php
PHP SDK for StitchData.com's [Import API](https://www.stitchdata.com/docs/integrations/import-api).

## Installation

This is a [Composer](https://getcomposer.org/) library, and can be installed with `composer require`. The current release is `^0.1`

```
composer require sminnee/stitchdata-php:^0.1
```

## Usage

### new StitchApi(string $clientId, string $accessToken)

The `Sminnee\StitchData\StitchApi` class provides access to the API. Its constructor takes 2 arguments, which are described in the [Import API documentation](https://www.stitchdata.com/docs/integrations/import-api#auth).

```php
use Sminnee\StitchData\StitchApi;
$api = new StitchApi(getenv('STITCHDATA_CLIENT_ID'), getenv('STITCHDATA_ACCESS_TOKEN'));
```

### StitchApi::validate(array $data = null, $includeClientId = true)

Validates that the connection is working and that your credentials are correct.

 * Validates the content (`$data`) of a request. It must be an array of maps.
 * If `$data` is omitted, a dummy request will be provided, including the client ID
 * If `$includeClientId` is true, then a `client_id` property will be added to every record in data, unless
   it is already specified.

```php
// Throws an exception if there's an issue with our connection
$api->validate();
```

### StitchApi::pushRecords(string $tableName, array $keyNames, iterable $records, int $batchSize = 100, ?callable $onBatchSent = null)

Pushes a number of records to the API in 1 or more batches.

 * `$tableName` is the name of the table in your data warehouse that you wnat StitchData to create and/or write to. It will populate `table_name` in requests to the API.
 * `$keyNames` is an array of record keys that you want to collectively use are your primary key. It will populate `key_names` in requests to the API.
 * `$records` is an array or iterator of records, where each record is a map. This is the data to upsert.
 * `$batchSize`, which defaults to 100, is the maximum number of records ot include in a single API request.
 * `$onBatchSent`, a callback that will be called, passing an array of all sent records, once per successful API call. This can be especially helpful when passing a generator for the data, as you can make subsequent use of the data without separately iterating on it.

The Data types you can use are determined by the [Transit library's default type mapping](https://github.com/honzabrecka/transit-php#default-type-mapping). Notably, you should use `Datetime` objects to represents dates/times.

```php
// Push 2 records to the StitchData, upserting in your data warehouse, and creating the test_peopel table if needed
$api->pushRecords(
    'test_people', 
    [' id' ],
    [
        [
            "id" => 1,
            "first_name" => "Sam",
            "last_name" => "Minnee",
            "num_visits" => 3,
            "date_last_visit" => new Datetime("2018-06-26"),
        ],
        [
            "id" => 2,
            "first_name" => "Ingo",
            "last_name" => "Schommer",
            "num_visits" => 6,
            "date_last_visit" => new Datetime("2018-06-27"),
        ]
   ]
);

```

### StitchApi::apiCall($subUrl, $data, $includeClientId = true)

Raw call to the StitchData REST API. Where possible we recommend that you use the above methods, but this allows for accessing new API behaviour not yet supported the SDK.

## Sequence generation

Sequence IDs are an important part of interacting with the API. `pushRecords()` automatically generates sequence IDs by the following mechanism.

 * The first request will use a sequence ID of `time() * 1000`.
 * Subquent requests will use the greater of sequence ID + 1 or time() * 1000.

If you wish to manually generate your own sequnce IDs you have to use the apiCall method instead, but please raise a github issue describing your use-case!
