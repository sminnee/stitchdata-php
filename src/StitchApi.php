<?php

use transit\JSONReader;
use transit\JSONWriter;
use transit\Transit;

class StitchApi
{
    private $lastSequenceNumber = 0;
    private $seqOffset = null;

    private $seqBase = null;
    private $seqOffset = null;

    public function __construct($clientId, $accessToken)
    {
        $this->clientId = $clientId;
        $this->accessToken = $accessToken;
    }

    /**
     * Validates that the connection is working and that your credentials are correct.
     * @throws LogicException if there's an issue
     */
    public function validate(array $data = null, bool $includeClientId = true)
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
     * Pushes a number of records to the API in a single batch request.
     */
    public function pushRecords(string $tableName, array $keyNames, array $records)
    {

        $commands = [];
        foreach ($records as $record) {
            $commands[] = [
                'action' => 'upsert',
                'sequence' => $this->connector->getSequenceNumber(),
                'table_name' => $tableName,
                'key_names' => $keyNames,
                'data' => $record,
            ];
        }

        return $this->apiCall('import/push', $commands, true);
    }

    /**
     * Run an API call
     */
    public function apiCall($subUrl, $data, bool $includeClientID = true)
    {
        $s = curl_init();
        $headers = [];

        $f = tmpfile();

        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($s, CURLOPT_URL, "https://api.stitchdata.com/v2/" . $subUrl);
        curl_setopt($s, CURLOPT_HTTPHEADER, [
            'Content-Type: application/transit+json',
            //'Content-Type: application/json',
            'Authorization: Bearer ' . $this->params['access-token'],
        ]);

        curl_setopt($s, CURLOPT_STDERR, $f);

        if ($includeClientID) {
            foreach ($data as $i => $record) {
                $data[$i]['client_id']  = (int)$this->params['client-id'];
            }
        }

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

        if ($status != 200 && $status != 201) {
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
        this->lastSequenceNumber = $next;
        return $next;
    }
}
