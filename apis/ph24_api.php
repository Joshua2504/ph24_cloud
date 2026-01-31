<?php
/**
 * PH24 Cloud API Wrapper
 *
 * @package ph24_cloud
 */
class Ph24Api
{
    /**
     * @var string The API URL
     */
    private $api_url;

    /**
     * @var string The API key
     */
    private $api_key;

    /**
     * @var array cURL options
     */
    private $curl_options = [];

    /**
     * Initialize the API
     *
     * @param string $api_key The API key
     * @param string $api_url The API URL (default: https://ph24.io/service)
     */
    public function __construct($api_key, $api_url = 'https://ph24.io/service')
    {
        $this->api_key = $api_key;
        $this->api_url = rtrim($api_url, '/');
    }

    /**
     * Set cURL options
     *
     * @param array $options An array of cURL options
     */
    public function setCurlOptions(array $options)
    {
        $this->curl_options = $options;
    }

    /**
     * Make an API request
     *
     * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $endpoint The API endpoint
     * @param array $data The data to send
     * @return stdClass Response object with 'code' and 'data' properties
     */
    private function request($method, $endpoint, $data = null)
    {
        $url = $this->api_url . $endpoint;
        
        $ch = curl_init($url);
        
        // Set HTTP method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Set headers
        $headers = [
            // Use no space after the colon to match curl examples (PH24-API-KEY:ph24_...)
            'PH24-API-KEY:' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set request body for POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && $data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        // Apply custom cURL options
        foreach ($this->curl_options as $option => $value) {
            curl_setopt($ch, $option, $value);
        }
        
        // Execute request
        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        // Handle cURL errors
        if ($curl_error) {
            return (object)[
                'code' => 0,
                'data' => (object)['error' => $curl_error]
            ];
        }
        
        // Parse response
        $response_data = json_decode($response_body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $response_data = (object)['raw' => $response_body];
        }
        
        return (object)[
            'code' => $http_code,
            'data' => $response_data
        ];
    }

    /**
     * Get all projects
     *
     * @return stdClass Response object
     */
    public function getProjects()
    {
        return $this->request('GET', '/v1/cloud/project');
    }

    /**
     * Get cloud info (for API validation)
     *
     * @return stdClass Response object
     */
    public function getInfo()
    {
        return $this->request('GET', '/v1/cloud/info');
    }

    /**
     * Get availability zones
     *
     * @return stdClass Response object
     */
    public function getAvailabilityZones()
    {
        return $this->request('GET', '/v1/cloud/availability-zones');
    }

    /**
     * Get available instance flavors
     *
     * @return stdClass Response object
     */
    public function getFlavors()
    {
        return $this->request('GET', '/v1/cloud/instance/flavor');
    }

    /**
     * Get images for a specific project
     *
     * @param string $project_id The project ID
     * @return stdClass Response object
     */
    public function getProjectImages($project_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}/image");
    }

    /**
     * Create a new project
     *
     * @param string $name The project name
     * @return stdClass Response object
     */
    public function createProject($name)
    {
        return $this->request('POST', '/v1/cloud/project', [
            'name' => $name
        ]);
    }

    /**
     * Get a specific project
     *
     * @param string $project_id The project ID
     * @return stdClass Response object
     */
    public function getProject($project_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}");
    }

    /**
     * Delete a project
     *
     * @param string $project_id The project ID
     * @return stdClass Response object
     */
    public function deleteProject($project_id)
    {
        return $this->request('DELETE', "/v1/cloud/project/{$project_id}");
    }

    /**
     * Get all networks in a project
     *
     * @param string $project_id The project ID
     * @return stdClass Response object
     */
    public function getNetworks($project_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}/network");
    }

    /**
     * Create a network in a project
     *
     * @param string $project_id The project ID
     * @param string $name The network name
     * @return stdClass Response object
     */
    public function createNetwork($project_id, $name)
    {
        return $this->request('POST', "/v1/cloud/project/{$project_id}/network", [
            'name' => $name
        ]);
    }

    /**
     * Get all firewalls in a project
     *
     * @param string $project_id The project ID
     * @return stdClass Response object
     */
    public function getFirewalls($project_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}/firewall");
    }

    /**
     * Create a firewall in a project
     *
     * @param string $project_id The project ID
     * @param string $name The firewall name
     * @return stdClass Response object
     */
    public function createFirewall($project_id, $name)
    {
        return $this->request('POST', "/v1/cloud/project/{$project_id}/firewall", [
            'name' => $name
        ]);
    }

    /**
     * Get all servers in a project
     *
     * @param string $project_id The project ID
     * @return stdClass Response object
     */
    public function getServers($project_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}/server");
    }

    /**
     * Get a specific server
     *
     * @param string $project_id The project ID
     * @param string $server_id The server ID
     * @return stdClass Response object
     */
    public function getServer($project_id, $server_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}/server/{$server_id}");
    }

    /**
     * Create a new server
     *
     * @param string $project_id The project ID
     * @param array $params Server parameters
     * @return stdClass Response object
     */
    public function createServer($project_id, array $params)
    {
        return $this->request('POST', "/v1/cloud/project/{$project_id}/server", $params);
    }

    /**
     * Update a server
     *
     * @param string $project_id The project ID
     * @param string $server_id The server ID
     * @param array $params Update parameters
     * @return stdClass Response object
     */
    public function updateServer($project_id, $server_id, array $params)
    {
        return $this->request('PUT', "/v1/cloud/project/{$project_id}/server/{$server_id}", $params);
    }

    /**
     * Delete a server
     *
     * @param string $project_id The project ID
     * @param string $server_id The server ID
     * @return stdClass Response object
     */
    public function deleteServer($project_id, $server_id)
    {
        return $this->request('DELETE', "/v1/cloud/project/{$project_id}/server/{$server_id}");
    }

    /**
     * Perform an action on a server
     *
     * @param string $project_id The project ID
     * @param string $server_id The server ID
     * @param string $action The action (START, STOP, REBOOT, FORCE_REBOOT, RESCALE, REBUILD)
     * @param string|null $flavor_id The flavor ID (required for RESCALE action)
     * @return stdClass Response object
     */
    public function serverAction($project_id, $server_id, $action, $flavor_id = null)
    {
        $data = ['action' => $action];
        
        if ($flavor_id !== null) {
            $data['flavorId'] = $flavor_id;
        }
        
        return $this->request('POST', "/v1/cloud/project/{$project_id}/server/{$server_id}/action", $data);
    }

    /**
     * Get server console access
     *
     * @param string $project_id The project ID
     * @param string $server_id The server ID
     * @return stdClass Response object
     */
    public function getServerConsole($project_id, $server_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}/server/{$server_id}/console");
    }

    /**
     * Get server logs
     *
     * @param string $project_id The project ID
     * @param string $server_id The server ID
     * @return stdClass Response object
     */
    public function getServerLogs($project_id, $server_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}/server/{$server_id}/log");
    }

    /**
     * Get server network ports
     *
     * @param string $project_id The project ID
     * @param string $server_id The server ID
     * @return stdClass Response object
     */
    public function getServerNetworkPorts($project_id, $server_id)
    {
        return $this->request('GET', "/v1/cloud/project/{$project_id}/server/{$server_id}/network-port");
    }

    /**
     * Get available VPS plans
     *
     * @return stdClass Response object
     */
    public function getPlans()
    {
        return $this->request('GET', '/v1/vps/plans');
    }

    /**
     * Get available OS images/templates
     *
     * @return stdClass Response object
     */
    public function getImages()
    {
        return $this->request('GET', '/v1/vps/images');
    }

    /**
     * Get available addons
     *
     * @return stdClass Response object
     */
    public function getAddons()
    {
        return $this->request('GET', '/v1/vps/addons');
    }
}
