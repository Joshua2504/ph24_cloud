<?php
/**
 * PH24.io Cloud Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.ph24_cloud
 * @copyright Copyright (c) 2026, Joshua Tobias Treudler
 * @link https://github.com/joshua2504
 */
class Ph24Cloud extends Module
{
    /**
     * Initializes the module
     */
    public function __construct()
    {
        // Load configuration required by this module
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this module
        Loader::loadComponents($this, ['Input', 'Record']);
        
        // Load helpers
        Loader::loadHelpers($this, ['Html', 'Form']);
        
        // Load the language required by this module
        Language::loadLang('ph24_cloud', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns all tabs to display to an admin when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title
     */
    public function getAdminTabs($package)
    {
        return [
            'tabActions' => Language::_('Ph24Cloud.tab_actions', true),
            'tabStats' => Language::_('Ph24Cloud.tab_stats', true),
        ];
    }

    /**
     * Returns all tabs to display to a client when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title
     */
    public function getClientTabs($package)
    {
        return [
            'tabClientActions' => Language::_('Ph24Cloud.tab_client_actions', true),
            'tabClientStats' => Language::_('Ph24Cloud.tab_client_stats', true),
        ];
    }

    /**
     * Client tab: Actions - show server status and allow start/stop actions
     *
     * @param stdClass $package The package
     * @param stdClass $service The service
     * @return string HTML content for the tab
     */
    public function tabClientActions($package, $service)
    {
        // Load view
        $this->view = new View('client_actions', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'ph24_cloud' . DS);

        // Load helpers required for this view (Html used in template)
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        // Prepare defaults
        $message = null;
        $server = null;
        $status = null;
        $power_state = null;

        // Get module row and API
        try {
            $row = $this->getModuleRow();
            if (!$row && method_exists($this, 'getModuleRows')) {
                $rows = $this->getModuleRows();
                if (is_array($rows) && !empty($rows)) {
                    $row = $rows[0];
                }
            }

            if (!$row || empty($row->meta->api_key)) {
                $message = Language::_('Ph24Cloud.!error.api.no_configured_row', true);
            } else {
                $api = $this->getApi($row->meta->api_key, $row->meta->api_url ?? null);

                // Convert service fields to object
                $fields = $this->serviceFieldsToObject($service->fields);
                $project_id = $fields->project_id ?? null;
                $server_id = $fields->server_id ?? null;

                // Prepare variables for the view similar to the Hetzner module
                $service_fields = $fields;
                $server_status = null;
                $server_specs = null;
                $traffic_data = null;
                $server_created = null;
                $server_ip_addresses = [];
                $server_details = [];

                if (!$project_id || !$server_id) {
                    $message = Language::_('Ph24Cloud.!error.api.server_not_found', true);
                } else {
                    // Handle POSTed action (start/stop)
                    if (!empty($_POST['ph24_action'])) {
                        $action = strtoupper($_POST['ph24_action']);
                        try {
                            if ($action === 'CHANGE_HOSTNAME') {
                                $new_name = trim($_POST['new_hostname'] ?? '');
                                if ($new_name === '') {
                                    $message = Language::_('Ph24Cloud.client_actions.hostname_required', true);
                                } else {
                                    $resp = $api->updateServer($project_id, $server_id, ['name' => $new_name]);
                                    if ($resp->code >= 200 && $resp->code < 300) {
                                        $message = Language::_('Ph24Cloud.client_actions.hostname_updated', true);
                                        $fields->hostname = $new_name;
                                        $service_fields->hostname = $new_name;

                                        // Persist the hostname in service meta
                                        try {
                                            $this->setServiceFields($service->id, [
                                                ['key' => 'hostname', 'value' => $new_name, 'encrypted' => 0]
                                            ]);
                                        } catch (Exception $e) {
                                            // Ignore meta persistence errors; API update already succeeded
                                        }
                                    } else {
                                        $message = Language::_('Ph24Cloud.client_actions.hostname_update_failed', true) . ' ' .
                                                   ($resp->data->message ?? '');
                                    }
                                }
                            } else {
                                $resp = $api->serverAction($project_id, $server_id, $action);
                                if ($resp->code >= 200 && $resp->code < 300) {
                                    $message = Language::_('Ph24Cloud.client_actions.action_success', true, $action);
                                } else {
                                    $message = Language::_('Ph24Cloud.client_actions.action_failed', true, $action) . ' ' .
                                               ($resp->data->message ?? '');
                                }
                            }
                        } catch (Exception $e) {
                            $message = Language::_('Ph24Cloud.client_actions.action_failed', true, $action) . ' ' . $e->getMessage();
                        }
                    }

                    // Fetch current server info
                    try {
                        $server_resp = $api->getServer($project_id, $server_id);
                        if ($server_resp->code >= 200 && $server_resp->code < 300) {
                            $server = $server_resp->data;
                            $status = $server->status ?? null;
                            $power_state = $server->powerState ?? null;

                            // Map API status/power to simplified server_status used in the view
                            $lower_status = strtolower($status ?? ($power_state ?? ''));
                            if (strpos($lower_status, 'run') !== false || strpos($lower_status, 'active') !== false) {
                                $server_status = 'running';
                            } elseif (strpos($lower_status, 'stop') !== false || strpos($lower_status, 'off') !== false) {
                                $server_status = 'off';
                            } else {
                                $server_status = 'unknown';
                            }

                            // Extract IPs from API response if available
                            if (isset($server->ipAddresses) && is_array($server->ipAddresses)) {
                                $server_ip_addresses = $server->ipAddresses;
                                foreach ($server->ipAddresses as $ip) {
                                    if (is_string($ip)) {
                                        if (strpos($ip, ':') !== false) {
                                            $service_fields->ipv6_address = $service_fields->ipv6_address ?? $ip;
                                        } else {
                                            $service_fields->ipv4_address = $service_fields->ipv4_address ?? $ip;
                                        }
                                    }
                                }
                            }

                            // Populate server specs if available
                            if (isset($server->flavor)) {
                                $server_specs = [
                                    'name' => $server->flavor->name ?? null,
                                    'flavorId' => $server->flavor->flavorId ?? null,
                                    'cores' => $server->flavor->meta->cores ?? null,
                                    'memory' => $server->flavor->meta->memory ?? null,
                                    'swap' => $server->flavor->meta->swap ?? null,
                                    'disk' => $server->flavor->meta->disk ?? null
                                ];
                            }

                            if (isset($server->createdAt)) {
                                $server_created = $server->createdAt;
                            }

                                $az = $server->availabilityZone ?? null;
                                $az_label = $az ? $this->availabilityZoneLabel($az) : null;

                                $server_details = [
                                'id' => $server->id ?? null,
                                'name' => $server->name ?? null,
                                'flavorId' => $server->flavorId ?? null,
                                    'availabilityZone' => $az_label ?? $az,
                                'status' => $server->status ?? null,
                                'powerState' => $server->powerState ?? null,
                                'createdAt' => $server->createdAt ?? null,
                                'imageId' => $server->image->id ?? null,
                                'imageName' => $server->image->name ?? null,
                                'imageDistro' => $server->image->distro ?? null,
                                'imageArch' => $server->image->architecture ?? null
                            ];

                            if (!empty($server_details['createdAt']) && is_numeric($server_details['createdAt'])) {
                                $server_details['createdAtHuman'] = date('Y-m-d H:i:s', (int)$server_details['createdAt']);
                            }
                            // power_state already set above
                        } else {
                            $message = $message ?: Language::_('Ph24Cloud.client_actions.status_unavailable', true);
                        }
                    } catch (Exception $e) {
                        $message = $message ?: Language::_('Ph24Cloud.client_actions.status_unavailable', true) . ' ' . $e->getMessage();
                    }
                }
            }
        } catch (Exception $e) {
            $message = Language::_('Ph24Cloud.client_actions.unexpected_error', true) . ' ' . $e->getMessage();
        }

        $this->view->set('message', $message);
        $this->view->set('server', $server ?? null);
        $this->view->set('status', $status ?? null);
        $this->view->set('power_state', $power_state ?? null);
        $this->view->set('service_fields', $service_fields ?? null);
        $this->view->set('server_status', $server_status ?? null);
        $this->view->set('server_specs', $server_specs ?? null);
        $this->view->set('traffic_data', $traffic_data ?? null);
        $this->view->set('server_created', $server_created ?? null);
        $this->view->set('server_ip_addresses', $server_ip_addresses ?? []);
        $this->view->set('server_details', $server_details ?? []);
        $this->view->set('package', $package);
        $this->view->set('service', $service);

        return $this->view->fetch();
    }

    /**
     * Performs any necessary bootstraping actions
     *
     * @param int $module_id The ID of the module being installed
     */
    public function install($module_id = null)
    {
        // Nothing to install
    }

    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version
     *
     * @param string $current_version The current installed version of this module
     * @param int $module_id The ID of the module being upgraded
     */
    public function upgrade($current_version, $module_id = null)
    {
        // Nothing to upgrade
    }

    /**
     * Performs any necessary cleanup actions
     *
     * @param int $module_id The ID of the module being uninstalled
     * @param bool $last_instance True if $module_id is the last instance
     *  across all companies for this module, false otherwise
     */
    public function uninstall($module_id, $last_instance)
    {
        // Nothing to uninstall
    }

    /**
     * Returns the rendered view of the manage module page
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the manager module
     *  page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the manager module page
     */
    public function manageModule($module, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('manage', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'ph24_cloud' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->view->set('module', $module);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the add module row page
     *
     * @param array $vars An array of post data submitted to or on the add module
     *  row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the add module row page
     */
    public function manageAddRow(array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('add_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'ph24_cloud' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the edit module row page
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of post data submitted to or on the edit module
     *  row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the edit module row page
     */
    public function manageEditRow($module_row, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('edit_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'ph24_cloud' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = $module_row->meta;
        }

        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Adds the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being added. Returns a set of data, which may be
     * a subset of $vars, that is stored for this module row
     *
     * @param array $vars An array of module info to add
     * @return array A numerically indexed array of meta fields for the module row containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function addModuleRow(array &$vars)
    {
        $meta_fields = ['account_name', 'api_url', 'api_key', 'project_name_template', 'master_project_id', 'use_master_project'];
        $encrypted_fields = ['api_key'];

        // Validate the module row
        $this->Input->setRules($this->getModuleRowRules($vars));

        // Validate module row
        if ($this->Input->validates($vars)) {
            // Build the meta data for this row
            $meta = [];
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = [
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    ];
                }
            }

            return $meta;
        }
    }

    /**
     * Deletes the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being deleted
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     */
    public function deleteModuleRow($module_row)
    {
        // Nothing to delete
        return null;
    }

    /**
     * Returns an array of available service delegation order methods
     *
     * @return array An array of order methods in key/value pairs
     */
    public function getGroupOrderOptions()
    {
        return [
            'first' => Language::_('Ph24Cloud.order_options.first', true)
        ];
    }

    /**
     * Returns all fields used when adding/editing a package, including any
     * javascript to execute when the page is rendered with these fields
     *
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render
     *  as well as any additional HTML markup to include
     */
    public function getPackageFields($vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();
        
        // Debug: Check what vars we're getting
        error_log('PH24Cloud getPackageFields called with vars: ' . print_r($vars, true));
        
        // Extract values - check multiple possible locations
        $flavor_id_value = null;
        $availability_zone_value = null;
        $set_template_value = 'admin';
        $image_id_value = null;
        
        if (isset($vars->meta)) {
            // If meta is an object with properties
            if (is_object($vars->meta)) {
                $flavor_id_value = $vars->meta->flavor_id ?? null;
                $availability_zone_value = $vars->meta->availability_zone ?? null;
                $set_template_value = $vars->meta->set_template ?? 'admin';
                $image_id_value = $vars->meta->image_id ?? null;
            }
            // If meta is an array
            elseif (is_array($vars->meta)) {
                $flavor_id_value = $vars->meta['flavor_id'] ?? null;
                $availability_zone_value = $vars->meta['availability_zone'] ?? null;
                $set_template_value = $vars->meta['set_template'] ?? 'admin';
                $image_id_value = $vars->meta['image_id'] ?? null;
            }
        }
        // Check if values are at root level of $vars
        if (is_object($vars)) {
            $flavor_id_value = $flavor_id_value ?? ($vars->flavor_id ?? null);
            $availability_zone_value = $availability_zone_value ?? ($vars->availability_zone ?? null);
            $set_template_value = ($vars->set_template ?? null) ? $vars->set_template : $set_template_value;
            $image_id_value = $image_id_value ?? ($vars->image_id ?? null);
        }
        
        error_log('PH24Cloud getPackageFields extracted values: flavor_id=' . $flavor_id_value . ', availability_zone=' . $availability_zone_value);

        // Attempt to load flavors and availability zones from the PH24 API
        $flavor_options = [];
        $availability_options = [];
        $image_options = [];
            // Try to get a configured module row. When editing packages there
            // may not be a single "current" module row, so fall back to the
            // first available module row if possible.
            $row = null;
            if (method_exists($this, 'getModuleRow')) {
                $row = $this->getModuleRow();
            }

            if (!$row && method_exists($this, 'getModuleRows')) {
                $rows = $this->getModuleRows();
                if (is_array($rows) && !empty($rows)) {
                    // rows are typically objects; pick the first
                    $row = $rows[0];
                }
            }

            if ($row && isset($row->meta->api_key) && $row->meta->api_key) {
                $api = $this->getApi($row->meta->api_key, $row->meta->api_url ?? null);

                // Flavors
                $flavors_response = $api->getFlavors();
                if ($flavors_response->code >= 200 && $flavors_response->code < 300 && is_array($flavors_response->data)) {
                    foreach ($flavors_response->data as $fl) {
                        $id = $fl->flavorId ?? $fl->id ?? null;
                        $name = $fl->name ?? ($fl->label ?? $id);
                        if ($id) {
                            $flavor_options[$id] = $name;
                        }
                    }
                }

                // Availability zones
                $az_response = $api->getAvailabilityZones();
                if ($az_response->code >= 200 && $az_response->code < 300 && is_array($az_response->data)) {
                    foreach ($az_response->data as $az) {
                        $id = $az->identifier ?? $az->id ?? null;
                        if ($id) {
                            $label = $this->availabilityZoneLabel($id, $az);
                            if (isset($az->available)) {
                                $label .= ($az->available ? ' (available)' : ' (unavailable)');
                            }
                            $availability_options[$id] = $label;
                        }
                    }
                }

            }

            error_log('PH24Cloud getPackageFields availability_options: ' . print_r($availability_options, true));

            // Ensure flavor options present (show placeholder if none)
            if (empty($flavor_options)) {
                $flavor_options = ['' => Language::_('Ph24Cloud.package_fields.placeholder_no_module', true) ?: '- Select -'];
            }

            // Add flavor select to package fields
            $flavor_label = $fields->label(Language::_('Ph24Cloud.package_fields.flavor_id', true), 'flavor_id');
            $flavor_label->attach(
                $fields->fieldSelect('flavor_id', $flavor_options, $flavor_id_value, ['id' => 'flavor_id'])
            );
            $fields->setField($flavor_label);

            // Availability Zone is selected at order time (live-loaded), not at package level.

            return $fields;
    }

    /**
     * Validates input data when attempting to add a package, returns the meta
     * data to save when adding a package
     *
     * @param array $vars An array of key/value pairs used to add a package
     * @return array A numerically indexed array of meta fields to be stored for this package
     */
    public function addPackage(array $vars = null)
    {
        // Debug logging to PHP error log
        error_log('PH24Cloud addPackage called with vars: ' . print_r($vars, true));
        
        // Check if fields are in $vars directly or in $vars['meta']
        $fields = isset($vars['meta']) && is_array($vars['meta']) ? $vars['meta'] : (isset($vars) && is_array($vars) ? $vars : []);
        
        error_log('PH24Cloud addPackage extracted fields: ' . print_r($fields, true));
        
        $meta = [];
        $meta_fields = ['flavor_id'];
        
        foreach ($meta_fields as $field) {
            if (isset($fields[$field])) {
                $meta[] = [
                    'key' => $field,
                    'value' => $fields[$field],
                    'encrypted' => 0
                ];
            }
        }
        
        error_log('PH24Cloud addPackage returning: ' . print_r($meta, true));
        return $meta;
    }

    /**
     * Validates input data when attempting to edit a package, returns the meta
     * data to save when editing a package
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of key/value pairs used to edit a package
     * @return array A numerically indexed array of meta fields to be stored for this package
     */
    public function editPackage($package, array $vars = null)
    {
        // Debug logging to PHP error log
        error_log('PH24Cloud editPackage called with vars: ' . print_r($vars, true));
        
        // Check if fields are in $vars directly or in $vars['meta']
        $fields = isset($vars['meta']) && is_array($vars['meta']) ? $vars['meta'] : (isset($vars) && is_array($vars) ? $vars : []);
        
        error_log('PH24Cloud editPackage extracted fields: ' . print_r($fields, true));
        
        $meta = [];
        $meta_fields = ['flavor_id'];
        
        foreach ($meta_fields as $field) {
            if (isset($fields[$field])) {
                $meta[] = [
                    'key' => $field,
                    'value' => $fields[$field],
                    'encrypted' => 0
                ];
            }
        }
        
        error_log('PH24Cloud editPackage returning: ' . print_r($meta, true));
        return $meta;
    }

    /**
     * Returns all validation rules for adding/editing a module row
     *
     * @param array $vars An array of key/value data pairs
     * @return array An array of validation rules
     */
    protected function getModuleRowRules(array &$vars)
    {
        return [
            'account_name' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Ph24Cloud.!error.account_name.empty', true)
                ]
            ],
            'api_url' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Ph24Cloud.!error.api_url.empty', true)
                ],
                'valid' => [
                    'rule' => function($url) {
                        return filter_var($url, FILTER_VALIDATE_URL) !== false;
                    },
                    'message' => Language::_('Ph24Cloud.!error.api_url.valid', true)
                ]
            ],
            'api_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Ph24Cloud.!error.api_key.empty', true)
                ],
                'valid' => [
                    'if_set' => true,
                        'rule' => function($api_key) use ($vars) {
                            // Skip validation if checkbox is checked
                            if (!empty($vars['skip_validation'])) {
                                return true;
                            }

                            // Validate by calling the availability-zones endpoint as a lightweight auth check
                            try {
                                $api = $this->getApi($api_key, $vars['api_url'] ?? null);
                                $response = $api->getAvailabilityZones();
                                return ($response->code >= 200 && $response->code < 300);
                            } catch (Exception $e) {
                                return false;
                            }
                        },
                    'message' => Language::_('Ph24Cloud.!error.api_key.valid', true)
                ]
            ],
            'project_name_template' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Ph24Cloud.!error.project_name_template.empty', true)
                ]
            ]
        ];
    }

    /**
     * Validates that the given connection details are correct
     *
     * @param string $api_key The API key
     * @param string $api_url The API URL
     * @return bool True if the connection is valid, false otherwise
     */
    public function validateConnection($api_key, $api_url = null)
    {
        try {
            $api = $this->getApi($api_key, $api_url);
            // Use the lightweight info endpoint to validate credentials and reachability
            $response = $api->getInfo();

            return ($response->code >= 200 && $response->code < 300);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Initializes the API and returns an instance of that object
     *
     * @param string $api_key The API key
     * @param string $api_url The API URL
     * @return Ph24Api The Ph24Api instance
     */
    protected function getApi($api_key, $api_url = null)
    {
        if (!$api_url) {
            $api_url = 'https://ph24.io/service';
        }

        Loader::load(dirname(__FILE__) . DS . 'apis' . DS . 'ph24_api.php');

        return new Ph24Api($api_key, $api_url);
    }

    /**
     * Return a human-friendly label for an availability zone identifier
     *
     * @param string $identifier The AZ identifier (e.g., 'fra1')
     * @param stdClass|null $az Optional AZ object from the API
     * @return string The label to display
     */
    protected function availabilityZoneLabel($identifier, $az = null)
    {
        $map = [
            'fra1' => 'Frankfurt am Main 🇩🇪 (Maincubes)',
            'fra' => 'Frankfurt am Main 🇩🇪 (Maincubes)',
            'ams1' => 'Amsterdam, Netherlands',
            'nyc1' => 'New York, USA',
            'lon1' => 'London, UK',
        ];

        if (isset($map[$identifier])) {
            return $map[$identifier];
        }

        if ($az && isset($az->name)) {
            return $az->name;
        }

        return $identifier;
    }

    /**
     * Converts a list of fields to a stdClass object
     *
     * @param array $fields An array of fields
     * @return stdClass An stdClass object with keys set to the field keys and values set to the field values
     */
    protected function serviceFieldsToObject(array $fields)
    {
        $obj = new stdClass();

        foreach ($fields as $field) {
            $obj->{$field->key} = $field->value;
        }

        return $obj;
    }

    /**
     * Generates a random password
     *
     * @param int $length The length of the password (default: 16)
     * @return string The generated password
     */
    protected function generatePassword($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        
        return $password;
    }

    /**
     * Returns all fields to display to an admin attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render
     */
    public function getAdminAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        error_log('PH24Cloud getAdminAddFields called with package: ' . print_r($package, true));
        error_log('PH24Cloud getAdminAddFields called with vars: ' . print_r($vars, true));

        // Operating System (images) + Hostname
        $image_options = [];
        try {
            $row = $this->getModuleRow();
            if (!$row && method_exists($this, 'getModuleRows')) {
                $rows = $this->getModuleRows();
                if (is_array($rows) && !empty($rows)) {
                    $row = $rows[0];
                }
            }

            $api = null;
            if ($row && isset($row->meta->api_key) && $row->meta->api_key) {
                $api = $this->getApi($row->meta->api_key, $row->meta->api_url ?? null);
            }

            if ($api && !empty($row->meta->master_project_id) && !empty($row->meta->use_master_project)) {
                $imgs = $api->getProjectImages($row->meta->master_project_id);
                if ($imgs->code >= 200 && $imgs->code < 300 && is_array($imgs->data)) {
                    foreach ($imgs->data as $im) {
                        $id = $im->id ?? $im->imageId ?? null;
                        $name = $im->name ?? ($im->label ?? $id);
                        if ($id) {
                            $image_options[$id] = $name;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('PH24Cloud getAdminAddFields image fetch error: ' . $e->getMessage());
        }

        // Availability zones (live-loaded)
        $availability_options = [];
        try {
            if ($api && method_exists($api, 'getAvailabilityZones')) {
                $az_resp = $api->getAvailabilityZones();
                if ($az_resp->code >= 200 && $az_resp->code < 300 && is_array($az_resp->data)) {
                    foreach ($az_resp->data as $az) {
                        $id = $az->identifier ?? $az->id ?? null;
                        if ($id) {
                            $availability_options[$id] = $this->availabilityZoneLabel($id, $az);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('PH24Cloud getAdminAddFields AZ fetch error: ' . $e->getMessage());
        }

        error_log('PH24Cloud getAdminAddFields availability_options: ' . print_r($availability_options, true));

        if (empty($image_options)) {
            $image_options = ['' => Language::_('Ph24Cloud.service_field.placeholder_no_images', true) ?: '- Select -'];
        }

        $os_label = $fields->label(Language::_('Ph24Cloud.service_field.operating_system', true), 'ph24_operating_system');
        $os_label->attach(
            $fields->fieldSelect('ph24_operating_system', $image_options, ($vars->ph24_operating_system ?? null), ['id' => 'ph24_operating_system'])
        );
        $fields->setField($os_label);

        if (empty($availability_options)) {
            $availability_options = ['' => Language::_('Ph24Cloud.package_fields.placeholder_no_module', true) ?: '- Select -'];
        }
        $az_label = $fields->label(Language::_('Ph24Cloud.package_fields.availability_zone', true), 'availability_zone');
        $az_label->attach(
            $fields->fieldSelect('availability_zone', $availability_options, ($vars->availability_zone ?? null), ['id' => 'availability_zone'])
        );
        $fields->setField($az_label);

        // Hostname
        $hostname = $fields->label(Language::_('Ph24Cloud.service_field.hostname', true), 'hostname');
        $hostname->attach(
            $fields->fieldText('hostname', (isset($vars->hostname) ? $vars->hostname : null), ['id' => 'hostname'])
        );
        $fields->setField($hostname);

        return $fields;
    }

    /**
     * Returns all fields to display to a client attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render
     */
    public function getClientAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        error_log('PH24Cloud getClientAddFields called with package: ' . print_r($package, true));
        error_log('PH24Cloud getClientAddFields called with vars: ' . print_r($vars, true));

        // Operating System (images) + Hostname for client order form
        $image_options = [];
        try {
            $row = $this->getModuleRow();
            if (!$row && method_exists($this, 'getModuleRows')) {
                $rows = $this->getModuleRows();
                if (is_array($rows) && !empty($rows)) {
                    $row = $rows[0];
                }
            }

            if ($row && !empty($row->meta->master_project_id) && !empty($row->meta->use_master_project)) {
                $api = $this->getApi($row->meta->api_key, $row->meta->api_url ?? null);
                $imgs = $api->getProjectImages($row->meta->master_project_id);
                if ($imgs->code >= 200 && $imgs->code < 300 && is_array($imgs->data)) {
                    foreach ($imgs->data as $im) {
                        $id = $im->id ?? $im->imageId ?? null;
                        $name = $im->name ?? ($im->label ?? $id);
                        if ($id) {
                            $image_options[$id] = $name;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('PH24Cloud getClientAddFields image fetch error: ' . $e->getMessage());
        }

        // Availability zones (live-loaded)
        $availability_options = [];
        try {
            if (isset($api) && method_exists($api, 'getAvailabilityZones')) {
                $az_resp = $api->getAvailabilityZones();
                if ($az_resp->code >= 200 && $az_resp->code < 300 && is_array($az_resp->data)) {
                    foreach ($az_resp->data as $az) {
                        $id = $az->identifier ?? $az->id ?? null;
                        if ($id) {
                            $availability_options[$id] = $this->availabilityZoneLabel($id, $az);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('PH24Cloud getClientAddFields AZ fetch error: ' . $e->getMessage());
        }

        if (empty($image_options)) {
            $image_options = ['' => Language::_('Ph24Cloud.service_field.placeholder_no_images', true) ?: '- Select -'];
        }

        $os_label = $fields->label(Language::_('Ph24Cloud.service_field.operating_system', true), 'ph24_operating_system');
        $os_label->attach(
            $fields->fieldSelect('ph24_operating_system', $image_options, ($vars->ph24_operating_system ?? null), ['id' => 'ph24_operating_system'])
        );
        $fields->setField($os_label);

        if (empty($availability_options)) {
            $availability_options = ['' => Language::_('Ph24Cloud.package_fields.placeholder_no_module', true) ?: '- Select -'];
        }
        $az_label = $fields->label(Language::_('Ph24Cloud.package_fields.availability_zone', true), 'availability_zone');
        $az_label->attach(
            $fields->fieldSelect('availability_zone', $availability_options, ($vars->availability_zone ?? null), ['id' => 'availability_zone'])
        );
        $fields->setField($az_label);

        // Hostname
        $hostname = $fields->label(Language::_('Ph24Cloud.service_field.hostname', true), 'hostname');
        $hostname->attach(
            $fields->fieldText('hostname', (isset($vars->hostname) ? $vars->hostname : null), ['id' => 'hostname'])
        );
        $fields->setField($hostname);

        return $fields;
    }

    /**
     * Attempts to validate service info. This is the method that should attempt to check
     * connectivity. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @return bool True if the service validates, false otherwise. Sets Input errors when false.
     */
    public function validateService($package, array $vars = null)
    {
        $this->Input->setRules($this->getServiceRules($vars));
        return $this->Input->validates($vars);
    }

    /**
     * Adds the service to the remote server. Sets Input errors on failure,
     * preventing the service from being added.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being added (if the current service is an addon service
     *  service and parent service has already been provisioned)
     * @param string $status The status of the service being added. These include:
     *  - active
     *  - canceled
     *  - pending
     *  - suspended
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addService(
        $package,
        array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        // Only provision during activation, not during order
        if ($status !== 'active') {
            return [
                [
                    'key' => 'hostname',
                    'value' => $vars['hostname'] ?? '',
                    'encrypted' => 0
                ]
            ];
        }
        
        $row = $this->getModuleRow();
        $api = $this->getApi($row->meta->api_key, $row->meta->api_url);

        // Load the client meta model
        Loader::loadModels($this, ['ModuleClientMeta', 'Clients']);
        
        $client_id = $vars['client_id'];
        $module = $this->getModule();

        // Determine project to use. If a master project is configured on the module
        // row, use that as a shared project for all customers. Otherwise use the
        // per-customer project stored in ModuleClientMeta or create one.
        $project_id = null;
        if (!empty($row->meta->master_project_id) && !empty($row->meta->use_master_project)) {
            $project_id = $row->meta->master_project_id;
            error_log('PH24Cloud addService using master_project_id for all customers: ' . $project_id);
        } else {
            // Get or create project for this client
            $project_data = $this->ModuleClientMeta->get($client_id, 'ph24_project_id', $module->id);
            
            if (!$project_data) {
                // Get client information for project naming
                $client = $this->Clients->get($client_id);
                $project_name_template = $row->meta->project_name_template ?? 'cust-{id}';
                $project_name = str_replace('{id}', $client_id, $project_name_template);
                
                // Create new project
                $project_response = $api->createProject($project_name);
                
                if ($project_response->code < 200 || $project_response->code >= 300) {
                    // Log detailed response for debugging
                    error_log('PH24Cloud createProject failed. Request name: ' . $project_name);
                    error_log('PH24Cloud createProject response code: ' . $project_response->code);
                    error_log('PH24Cloud createProject response body: ' . print_r($project_response->data, true));

                    // Build a more informative error message
                    $err_msg = Language::_('Ph24Cloud.!error.api.project_create', true);
                    if (isset($project_response->data->message)) {
                        $err_msg .= ': ' . $project_response->data->message;
                    } else {
                        // Include any returned payload for diagnostics
                        $payload = is_scalar($project_response->data) ? $project_response->data : json_encode($project_response->data);
                        if ($payload) {
                            $err_msg .= ' (response: ' . $payload . ')';
                        }
                    }

                    $this->Input->setErrors([
                        'api' => [
                            'response' => $err_msg
                        ]
                    ]);

                    return;
                }
                
                $project_id = $project_response->data->id ?? $project_response->data->uuid ?? null;
                
                if (!$project_id) {
                    $this->Input->setErrors([
                        'api' => ['response' => Language::_('Ph24Cloud.!error.api.project_id', true)]
                    ]);
                    return;
                }
                
                // Store project ID
                $this->ModuleClientMeta->set(
                    $client_id,
                    $module->id,
                    0,
                    [['key' => 'ph24_project_id', 'value' => $project_id, 'encrypted' => 0]]
                );
                
                // Auto-create default network if none exist
                $networks_response = $api->getNetworks($project_id);
                if ($networks_response->code >= 200 && $networks_response->code < 300 && 
                    is_array($networks_response->data) && empty($networks_response->data)) {
                    $api->createNetwork($project_id, 'default-network');
                }
                
                // Auto-create default firewall if none exist
                $firewalls_response = $api->getFirewalls($project_id);
                if ($firewalls_response->code >= 200 && $firewalls_response->code < 300 && 
                    is_array($firewalls_response->data) && empty($firewalls_response->data)) {
                    $api->createFirewall($project_id, 'default-firewall');
                }
            } else {
                $project_id = $project_data->value;
            }
        }

        // Get networks and firewalls for server creation
        $networks_response = $api->getNetworks($project_id);
        $firewalls_response = $api->getFirewalls($project_id);
        
        if ($networks_response->code < 200 || $networks_response->code >= 300) {
            $this->Input->setErrors([
                'api' => ['response' => Language::_('Ph24Cloud.!error.api.networks_fetch', true)]
            ]);
            return;
        }
        
        if ($firewalls_response->code < 200 || $firewalls_response->code >= 300) {
            $this->Input->setErrors([
                'api' => ['response' => Language::_('Ph24Cloud.!error.api.firewalls_fetch', true)]
            ]);
            return;
        }
        
        $networks = is_array($networks_response->data) ? $networks_response->data : [];
        $firewalls = is_array($firewalls_response->data) ? $firewalls_response->data : [];
        
        if (empty($networks) && empty($firewalls)) {
            $this->Input->setErrors([
                'api' => ['response' => Language::_('Ph24Cloud.!error.api.no_resources', true)]
            ]);
            return;
        }

        // Prepare server parameters
        $network_ids = !empty($networks) ? [($networks[0]->id ?? $networks[0]->uuid)] : [];
        $firewall_ids = !empty($firewalls) ? [($firewalls[0]->id ?? $firewalls[0]->uuid)] : [];
        
        // Get image_id and facility_id from configurable options
        // Look for options named 'ph24_operating_system' and 'ph24_facility'
        $image_id = null;
        $facility_id = null;
        
        // Debug: Log all configurable options
        error_log('PH24Cloud addService configoptions: ' . print_r($vars['configoptions'] ?? 'none', true));
        
        if (isset($vars['configoptions']) && is_array($vars['configoptions'])) {
            foreach ($vars['configoptions'] as $option_id => $option_value) {
                // Option value can be the ID or we need to look up the option name
                // Store for now - we'll check option names via the option_id
                if (stripos($option_id, 'operating_system') !== false || stripos($option_id, 'ph24_operating_system') !== false) {
                    $image_id = $option_value;
                    error_log('PH24Cloud found operating_system: ' . $option_value);
                }
                if (stripos($option_id, 'facility') !== false || stripos($option_id, 'ph24_facility') !== false) {
                    $facility_id = $option_value;
                    error_log('PH24Cloud found facility: ' . $option_value);
                }
            }
        }
        
        // Also check if they're directly in $vars
        $image_id = $image_id ?? ($vars['ph24_operating_system'] ?? null);
        $facility_id = $facility_id ?? ($vars['ph24_facility'] ?? null);
        
        if (!$image_id) {
            $this->Input->setErrors([
                'api' => ['response' => Language::_('Ph24Cloud.!error.api.image_id_missing', true)]
            ]);
            return;
        }
        
        // Determine facility ID (from client selection or package)
        $facility_id = $vars['facility_id'] ?? $package->meta->facility_id ?? null;
        
        // Generate root password
        $root_password = $this->generatePassword(16);
        
        $server_params = [
            'name' => $vars['hostname'],
            'flavorId' => $package->meta->flavor_id,
            'availabilityZone' => ($vars['availability_zone'] ?? $package->meta->availability_zone ?? null),
            'imageId' => $image_id,
            'networkIds' => $network_ids,
            'firewallIds' => $firewall_ids,
            'count' => 1
        ];
        
        // Add facility if specified
        if ($facility_id) {
            $server_params['facilityId'] = $facility_id;
        }
        
        // Log the request
        $this->log($row->meta->api_url . '|server-create', serialize($server_params), 'input', true);
        
        // Create server
        $server_response = $api->createServer($project_id, $server_params);
        
        // Log the response
        $success = ($server_response->code >= 200 && $server_response->code < 300);
        $this->log($row->meta->api_url . '|server-create', serialize($server_response->data), 'output', $success);
        
        if (!$success) {
            $this->Input->setErrors([
                'api' => [
                    'response' => Language::_('Ph24Cloud.!error.api.server_create', true) . 
                                 (isset($server_response->data->message) ? ': ' . $server_response->data->message : '')
                ]
            ]);
            return;
        }
        
        $server = $server_response->data;
        $server_id = $server->id ?? $server->uuid ?? null;
        
        if (!$server_id) {
            $this->Input->setErrors([
                'api' => ['response' => Language::_('Ph24Cloud.!error.api.server_id', true)]
            ]);
            return;
        }
        
        // Return service fields
        return [
            [
                'key' => 'server_id',
                'value' => $server_id,
                'encrypted' => 0
            ],
            [
                'key' => 'hostname',
                'value' => $vars['hostname'],
                'encrypted' => 0
            ],
            [
                'key' => 'project_id',
                'value' => $project_id,
                'encrypted' => 0
            ],
            [
                'key' => 'ip_addresses',
                'value' => json_encode($server->ipAddresses ?? []),
                'encrypted' => 0
            ],
            [
                'key' => 'password',
                'value' => $root_password,
                'encrypted' => 1
            ],
            [
                'key' => 'flavor_id',
                'value' => $server->flavorId ?? $package->meta->flavor_id,
                'encrypted' => 0
            ],
            [
                'key' => 'image_id',
                'value' => $image_id,
                'encrypted' => 0
            ],
            [
                'key' => 'status',
                'value' => $server->status ?? 'ACTIVE',
                'encrypted' => 0
            ],
            [
                'key' => 'power_state',
                'value' => $server->powerState ?? 'RUNNING',
                'encrypted' => 0
            ]
        ];
    }

    /**
     * Cancels the service on the remote server. Sets Input errors on failure,
     * preventing the service from being canceled.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being canceled (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically
     *  indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {
        $row = $this->getModuleRow();
        $api = $this->getApi($row->meta->api_key, $row->meta->api_url);
        
        $service_fields = $this->serviceFieldsToObject($service->fields);
        
        if (!isset($service_fields->server_id) || !isset($service_fields->project_id)) {
            return null;
        }
        
        // Delete the server
        $response = $api->deleteServer($service_fields->project_id, $service_fields->server_id);
        
        // Log the request
        $this->log(
            $row->meta->api_url . '|server-delete',
            serialize(['project_id' => $service_fields->project_id, 'server_id' => $service_fields->server_id]),
            'input',
            true
        );
        
        $success = ($response->code >= 200 && $response->code < 300);
        
        // Log the response
        $this->log($row->meta->api_url . '|server-delete', serialize($response->data), 'output', $success);
        
        if (!$success) {
            $this->Input->setErrors([
                'api' => [
                    'response' => Language::_('Ph24Cloud.!error.api.server_delete', true) . 
                                 (isset($response->data->message) ? ': ' . $response->data->message : '')
                ]
            ]);
        }
        
        return null;
    }

    /**
     * Suspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being suspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being suspended (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically
     *  indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        $row = $this->getModuleRow();
        $api = $this->getApi($row->meta->api_key, $row->meta->api_url);
        
        $service_fields = $this->serviceFieldsToObject($service->fields);
        
        if (!isset($service_fields->server_id) || !isset($service_fields->project_id)) {
            return null;
        }
        
        // Stop the server
        $response = $api->serverAction($service_fields->project_id, $service_fields->server_id, 'STOP');
        
        // Log the request
        $this->log(
            $row->meta->api_url . '|server-action-stop',
            serialize(['project_id' => $service_fields->project_id, 'server_id' => $service_fields->server_id]),
            'input',
            true
        );
        
        $success = ($response->code >= 200 && $response->code < 300);
        
        // Log the response
        $this->log($row->meta->api_url . '|server-action-stop', serialize($response->data), 'output', $success);
        
        if (!$success) {
            $this->Input->setErrors([
                'api' => [
                    'response' => Language::_('Ph24Cloud.!error.api.server_suspend', true) . 
                                 (isset($response->data->message) ? ': ' . $response->data->message : '')
                ]
            ]);
        }
        
        return null;
    }

    /**
     * Unsuspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being unsuspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being unsuspended (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically
     *  indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        $row = $this->getModuleRow();
        $api = $this->getApi($row->meta->api_key, $row->meta->api_url);
        
        $service_fields = $this->serviceFieldsToObject($service->fields);
        
        if (!isset($service_fields->server_id) || !isset($service_fields->project_id)) {
            return null;
        }
        
        // Start the server
        $response = $api->serverAction($service_fields->project_id, $service_fields->server_id, 'START');
        
        // Log the request
        $this->log(
            $row->meta->api_url . '|server-action-start',
            serialize(['project_id' => $service_fields->project_id, 'server_id' => $service_fields->server_id]),
            'input',
            true
        );
        
        $success = ($response->code >= 200 && $response->code < 300);
        
        // Log the response
        $this->log($row->meta->api_url . '|server-action-start', serialize($response->data), 'output', $success);
        
        if (!$success) {
            $this->Input->setErrors([
                'api' => [
                    'response' => Language::_('Ph24Cloud.!error.api.server_unsuspend', true) . 
                                 (isset($response->data->message) ? ': ' . $response->data->message : '')
                ]
            ]);
        }
        
        return null;
    }

    /**
     * Returns all validation rules for adding/editing a service
     *
     * @param array $vars An array of key/value data pairs
     * @return array An array of validation rules
     */
    protected function getServiceRules(array $vars)
    {
        return [
            'hostname' => [
                'format' => [
                    'rule' => function($hostname) {
                        return preg_match('/^[a-z0-9\.\-]+$/i', $hostname);
                    },
                    'message' => Language::_('Ph24Cloud.!error.hostname.format', true)
                ]
            ]
        ];
    }
}
