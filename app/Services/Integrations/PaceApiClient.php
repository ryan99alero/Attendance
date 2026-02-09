<?php

namespace App\Services\Integrations;

use InvalidArgumentException;
use Exception;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncLog;
use App\Models\IntegrationQueryTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

/**
 * Pace/ePace REST API Client
 *
 * Handles communication with Pace ERP system via REST API.
 * Primary method is loadValueObjects for efficient hierarchical data retrieval.
 *
 * @see docs/INTEGRATIONS.md for usage documentation
 */
class PaceApiClient
{
    protected IntegrationConnection $connection;
    protected ?IntegrationSyncLog $currentLog = null;

    public function __construct(IntegrationConnection $connection)
    {
        if ($connection->driver !== 'pace') {
            throw new InvalidArgumentException('Connection must be a Pace integration');
        }

        $this->connection = $connection;
    }

    /**
     * Create client from connection ID
     */
    public static function fromConnection(int $connectionId): self
    {
        $connection = IntegrationConnection::findOrFail($connectionId);
        return new self($connection);
    }

    /**
     * Test the API connection using the Version endpoint
     *
     * Calls GET /Version/getVersion which returns the Pace version string.
     * This is the lightest possible call - no data access required.
     */
    public function testConnection(): array
    {
        try {
            $response = $this->get('Version/getVersion');

            $version = trim($response->body(), '"'); // Response is a quoted string

            $this->connection->markConnected();

            return [
                'success' => true,
                'message' => 'Connection successful',
                'version' => $version,
            ];
        } catch (Exception $e) {
            $this->connection->markError($e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Load value objects from Pace API
     *
     * This is the primary method for fetching data from Pace.
     * It uses the loadValueObjects endpoint which allows:
     * - Fetching specific fields using XPath selectors
     * - Including child objects in a single request
     * - Filtering and pagination
     *
     * @param string $objectName Root object type (Job, Customer, Employee, etc.)
     * @param array $fields Array of field definitions [['name' => 'fieldName', 'xpath' => '@fieldPath']]
     * @param array $children Child objects to include (empty array if none)
     * @param string|null $primaryKey Fetch a specific record by primary key
     * @param string|null $xpathFilter XPath filter expression
     * @param string|null $xpathSorts XPath sort expression
     * @param int $offset Pagination offset
     * @param int|null $limit Number of records to return (null = omit from request, 0 = 1 record)
     * @return array Response containing valueObjects array and totalRecords
     */
    public function loadValueObjects(
        string $objectName,
        array $fields,
        array $children = [],
        ?string $primaryKey = null,
        ?string $xpathFilter = null,
        ?string $xpathSorts = null,
        int $offset = 0,
        ?int $limit = null,
    ): array {
        $payload = [
            'fields' => $fields,
            'objectName' => $objectName,
            'offset' => $offset,
            'xpathFilter' => $xpathFilter,
            'xpathSorts' => $xpathSorts,
            'children' => $children,
        ];

        if ($limit !== null) {
            $payload['limit'] = $limit;
        }

        // Only include primaryKey when fetching a specific record
        if ($primaryKey !== null) {
            $payload['primaryKey'] = $primaryKey;
        }

        $response = $this->post('FindObjects/loadValueObjects', $payload);

        return $response->json();
    }

    /**
     * Load value objects using a query template
     */
    public function loadFromTemplate(
        IntegrationQueryTemplate $template,
        int $offset = 0,
        ?string $additionalFilter = null
    ): array {
        $template->recordUsage();

        $payload = $template->buildPayload($offset, $additionalFilter);

        $response = $this->post('FindObjects/loadValueObjects', $payload);

        return $response->json();
    }

    /**
     * Load all matching records.
     * Probes for totalRecords first, then fetches all in one call.
     *
     * @param string $objectName Object type
     * @param array $fields Fields to fetch
     * @param array $children Child objects (empty array if none)
     * @param string|null $xpathFilter XPath filter expression
     * @return Collection Collection of value objects
     */
    public function loadAllValueObjects(
        string $objectName,
        array $fields,
        array $children = [],
        ?string $xpathFilter = null,
    ): Collection {
        // Probe for total count
        $probe = $this->loadValueObjects(
            objectName: $objectName,
            fields: [['name' => '_id', 'xpath' => '@id']],
            xpathFilter: $xpathFilter,
        );

        $total = $probe['totalRecords'] ?? 0;
        if ($total === 0) {
            return collect();
        }

        // Fetch all records
        $response = $this->loadValueObjects(
            objectName: $objectName,
            fields: $fields,
            children: $children,
            xpathFilter: $xpathFilter,
            limit: $total,
        );

        return collect($response['valueObjects'] ?? []);
    }

    /**
     * Parse a value object into an associative array
     *
     * Converts the fields array into a simple key => value structure
     */
    public function parseValueObject(array $valueObject): array
    {
        $result = [
            '_primaryKey' => $valueObject['primaryKey'] ?? null,
            '_objectName' => $valueObject['objectName'] ?? null,
        ];

        // Parse fields
        foreach ($valueObject['fields'] ?? [] as $field) {
            $value = $field['value'];

            // Handle type conversions
            if ($field['type'] === 'Date' && $value !== null) {
                $value = Carbon::createFromTimestampMs($value);
            }

            $result[$field['name']] = $value;
        }

        // Parse children
        if (!empty($valueObject['children'])) {
            foreach ($valueObject['children'] as $childGroup) {
                $childName = $childGroup['objectName'];
                $result['_children'][$childName] = collect($childGroup['valueObjects'] ?? [])
                    ->map(fn($child) => $this->parseValueObject($child))
                    ->all();
            }
        }

        return $result;
    }

    /**
     * Parse multiple value objects
     */
    public function parseValueObjects(array $valueObjects): Collection
    {
        return collect($valueObjects)->map(fn($vo) => $this->parseValueObject($vo));
    }

    /**
     * Get available object types from the Pace swagger.json.
     * Parses the swagger file and extracts unique object names from API paths.
     * Results are cached in memory for the request lifecycle.
     */
    public function getCommonObjectTypes(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $swaggerPath = base_path('docs/Pace RestFul/swagger.json');

        if (!file_exists($swaggerPath)) {
            // Fallback if swagger file is missing
            $cache = [
                'Customer' => 'Customer',
                'Contact' => 'Contact',
                'Department' => 'Department',
                'Employee' => 'Employee',
                'Invoice' => 'Invoice',
                'Job' => 'Job',
                'JobPart' => 'JobPart',
                'PurchaseOrder' => 'PurchaseOrder',
                'Vendor' => 'Vendor',
            ];
            return $cache;
        }

        $swagger = json_decode(file_get_contents($swaggerPath), true);
        $paths = array_keys($swagger['paths'] ?? []);

        $objectNames = [];
        foreach ($paths as $path) {
            // Match patterns like /LoadValueObjects/loadEmployee, /CreateObject/createEmployee
            if (preg_match('#^/LoadValueObjects/load(\w+)$#', $path, $m)) {
                $objectNames[$m[1]] = true;
            } elseif (preg_match('#^/CreateObject/create(\w+)$#', $path, $m)) {
                $objectNames[$m[1]] = true;
            }
        }

        ksort($objectNames);

        // Format: ObjectName => ObjectName (keep it simple — CamelCase is readable enough)
        $cache = [];
        foreach (array_keys($objectNames) as $name) {
            $cache[$name] = $name;
        }

        return $cache;
    }

    /**
     * Build the HTTP client with authentication
     */
    protected function buildClient(): PendingRequest
    {
        $client = Http::baseUrl($this->connection->base_url)
            ->timeout($this->connection->timeout_seconds)
            ->retry($this->connection->retry_attempts, 100)
            ->acceptJson()
            ->asJson();

        // Add authentication
        switch ($this->connection->auth_type) {
            case 'basic':
                $client->withBasicAuth(
                    $this->connection->getCredential('username'),
                    $this->connection->getCredential('password')
                );
                break;

            case 'bearer':
                $client->withToken($this->connection->getCredential('bearer_token'));
                break;

            case 'api_key':
                $location = $this->connection->getCredential('api_key_location', 'header');
                $key = $this->connection->getCredential('api_key');
                $name = $this->connection->getCredential('api_key_name', 'Authorization');

                if ($location === 'header' || $location === 'header_custom') {
                    $client->withHeaders([$name => $key]);
                }
                // Query params handled in request methods
                break;
        }

        return $client;
    }

    /**
     * Make a POST request
     */
    protected function post(string $endpoint, array $data = []): Response
    {
        $client = $this->buildClient();

        // Add API key to query if needed
        if ($this->connection->auth_type === 'api_key' &&
            $this->connection->getCredential('api_key_location') === 'query') {
            $name = $this->connection->getCredential('api_key_name', 'api_key');
            $endpoint .= (str_contains($endpoint, '?') ? '&' : '?') .
                         $name . '=' . urlencode($this->connection->getCredential('api_key'));
        }

        $response = $client->post($endpoint, $data);

        if ($response->failed()) {
            $this->connection->markError($response->body());
            throw new Exception('Pace API Error: ' . $response->body());
        }

        $this->connection->markConnected();

        return $response;
    }

    /**
     * Make a GET request
     */
    protected function get(string $endpoint, array $query = []): Response
    {
        $client = $this->buildClient();

        // Add API key to query if needed
        if ($this->connection->auth_type === 'api_key' &&
            $this->connection->getCredential('api_key_location') === 'query') {
            $name = $this->connection->getCredential('api_key_name', 'api_key');
            $query[$name] = $this->connection->getCredential('api_key');
        }

        $response = $client->get($endpoint, $query);

        if ($response->failed()) {
            $this->connection->markError($response->body());
            throw new Exception('Pace API Error: ' . $response->body());
        }

        $this->connection->markConnected();

        return $response;
    }
}
