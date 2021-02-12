<?php

namespace Sminnee\StitchData;

use transit\JSONReader;
use transit\JSONWriter;
use transit\Transit;

class StitchApi
{
    private $lastSequenceNumber = 0;

    private $clientId = null;

    private $accessToken = null;

    public function __construct($clientId, $accessToken)
    {
        $this->clientId = $clientId;
        $this->accessToken = $accessToken;
    }

    /**
     * Validates that the connection is working and that your credentials are correct.
     *
     * @throws LogicException if there's an issue
     */
    public function validate(array $data = null, $includeClientId = true)
    {
        if ($data === null) {
            $data = [[
                'action' => 'upsert',
                'sequence' => $this->getSequenceNumber(),
                'table_name' => 'test',
                'key_names' => [ 'id' ],
                'data' => ['id' => 10, 'test_field' => 'foo' ],
            ]];
        }

        return $this->apiCall('import/validate', $data, $includeClientId);
    }


    /**
     * Pushes a number of records to the API in 1 or more API calls.
     *
     * @param string $tableName The name of the table to upsert to
     * @param array $keyNames The names of the keys to update
     * @param array $records An array-of-maps representing the data to upsert
     * @param int $batchSize The max number of records to send in a single API call. Null/0 for no limit
     * @param callable(array): void $onBatchSent Call this after a batch is successfully sent, passing the batch data as an argument
     * @return The result of the final apiCall request.
     * @throws LogicException if there's a failed API call
     */
    public function pushRecords(string $tableName, array $keyNames, iterable $records, $batchSize = 100, ?callable $onBatchSent = null)
    {
        $pushCommands = function ($commands) use ($onBatchSent) {
            $result = $this->apiCall('import/push', $commands, true);
            if ($onBatchSent !== null) {
                $onBatchSent(array_map(
                    function ($command) {
                        return $command['data'];
                    },
                    $commands
                ));
            }
            return $result;
        };

        $commands = [];
        $result = null;

        foreach ($records as $record) {
            $commands[] = [
                'action' => 'upsert',
                'sequence' => $this->getSequenceNumber(),
                'table_name' => $tableName,
                'key_names' => $keyNames,
                'data' => $record,
            ];

            if ($batchSize && sizeof($commands) >= $batchSize) {
                $result = $pushCommands($commands);
                $commands = [];
            }
        }

        if ($commands) {
            $result = $pushCommands($commands);
        }

        return $result;
    }

    /**
     * Run an API call
     */
    public function apiCall($subUrl, array $data, $includeClientID = true)
    {
        $s = curl_init();
        $headers = [];

        $f = tmpfile();

        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($s, CURLOPT_URL, "https://api.stitchdata.com/v2/" . $subUrl);
        curl_setopt($s, CURLOPT_HTTPHEADER, [
            'Content-Type: application/transit+json',
            //'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        curl_setopt($s, CURLOPT_STDERR, $f);

        if ($includeClientID) {
            foreach ($data as $i => $record) {
                $data[$i]['client_id']  = (int)$this->clientId;
            }
        }

        $payload = null;
        if ($data) {
            if (!is_array($data)) {
                throw new \LogicException("bad data: " . var_export($data, true));
            }
            $transit = new Transit(new JSONReader(true), new JSONWriter(true));
            $payload = $transit->write($data);
            //$payload = json_encode($data);
            curl_setopt($s, CURLOPT_POSTFIELDS, $payload);
        }

        $content = curl_exec($s);
        $status = curl_getinfo($s, CURLINFO_HTTP_CODE);

        curl_close($s);

        if ($status != 200 && $status != 201 && $status != 202) {
            throw new \LogicException("StitchData API $subUrl returned HTTP $status: $content\n----\n" . $payload);
        }

        return json_decode($content, true);
    }

    /**
     * Get a sequence number.
     * Will return 1 more than the last sequence number, or time() * 1000, whichever is greater
     */
    public function getSequenceNumber()
    {
        $next = max($this->lastSequenceNumber + 1, time() * 1000);
        $this->lastSequenceNumber = $next;
        return $next;
    }
}
