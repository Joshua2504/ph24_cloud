<?php
/**
 * en_us language for the PH24 Cloud module
 */

// Module information
$lang['Ph24Cloud.name'] = 'PH24 Cloud';
$lang['Ph24Cloud.description'] = 'Provision and manage virtual servers on the PH24 Cloud platform.';

// Module row labels
$lang['Ph24Cloud.module_row'] = 'API Server';
$lang['Ph24Cloud.module_rows'] = 'API Servers';
$lang['Ph24Cloud.module_group'] = 'Server Group';

// Module row meta labels
$lang['Ph24Cloud.row_meta.account_name'] = 'Account Name';
$lang['Ph24Cloud.row_meta.api_url'] = 'API URL';
$lang['Ph24Cloud.row_meta.api_key'] = 'API Key';
$lang['Ph24Cloud.row_meta.project_name_template'] = 'Project Name Template';
$lang['Ph24Cloud.row_meta.skip_validation'] = 'Skip API Connection Test';
$lang['Ph24Cloud.row_meta.master_project_id'] = 'Master Project ID';
$lang['Ph24Cloud.row_meta.use_master_project'] = 'Use Master Project';

// Tooltips
$lang['Ph24Cloud.!tooltip.account_name'] = 'A friendly name for this API connection (for internal use only).';
$lang['Ph24Cloud.!tooltip.api_url'] = 'The full URL to the PH24 Cloud API (default: https://ph24.io/service).';
$lang['Ph24Cloud.!tooltip.api_key'] = 'Your PH24 Cloud API key for authentication.';
$lang['Ph24Cloud.!tooltip.project_name_template'] = 'Template for naming customer projects. Use {id} as a placeholder for the client ID (e.g., "cust-{id}").';
$lang['Ph24Cloud.!tooltip.skip_validation'] = 'Check this box to skip the API connection test when adding/editing this server. Useful if the API is temporarily unavailable or has IP restrictions.';
$lang['Ph24Cloud.!tooltip.master_project_id'] = 'A project ID from which OS images/templates will be listed for orders.';
$lang['Ph24Cloud.!tooltip.use_master_project'] = 'When enabled, all new services will be provisioned into the Master Project instead of creating a project per customer.';

// Add row page
$lang['Ph24Cloud.add_row.box_title'] = 'Add PH24 Cloud API Server';
$lang['Ph24Cloud.add_row.basic_title'] = 'API Credentials';
$lang['Ph24Cloud.add_row.add_btn'] = 'Add Server';

// Edit row page
$lang['Ph24Cloud.edit_row.box_title'] = 'Edit PH24 Cloud API Server';
$lang['Ph24Cloud.edit_row.basic_title'] = 'API Credentials';
$lang['Ph24Cloud.edit_row.edit_btn'] = 'Update Server';

// Manage module page
$lang['Ph24Cloud.manage.module_rows_title'] = 'API Servers';
$lang['Ph24Cloud.manage.add_server'] = 'Add API Server';
$lang['Ph24Cloud.manage.account_name'] = 'Account Name';
$lang['Ph24Cloud.manage.api_url'] = 'API URL';
$lang['Ph24Cloud.manage.project_template'] = 'Project Template';
$lang['Ph24Cloud.manage.options'] = 'Options';
$lang['Ph24Cloud.manage.edit'] = 'Edit';
$lang['Ph24Cloud.manage.delete'] = 'Delete';
$lang['Ph24Cloud.manage.delete_row'] = 'Are you sure you want to delete this server?';
$lang['Ph24Cloud.manage.no_module_rows'] = 'No API servers have been added yet.';

// Package fields
$lang['Ph24Cloud.package_fields.flavor_id'] = 'Plan/Flavor ID';
$lang['Ph24Cloud.package_fields.availability_zone'] = 'Availability Zone';
$lang['Ph24Cloud.package_fields.image_id'] = 'Image ID (OS Template)';
$lang['Ph24Cloud.package_fields.set_template'] = 'Template Selection';
$lang['Ph24Cloud.package_fields.set_template_admin'] = 'Admin Only';
$lang['Ph24Cloud.package_fields.set_template_client'] = 'Client Selectable';

// Package field tooltips
$lang['Ph24Cloud.package_fields.tooltip.flavor_id'] = 'The ID of the server plan/flavor (e.g., "vc2-2c-4gb").';
$lang['Ph24Cloud.package_fields.tooltip.availability_zone'] = 'The data center location (e.g., "fra1").';
$lang['Ph24Cloud.package_fields.tooltip.image_id'] = 'The OS template ID (only used if Template Selection is set to "Admin Only").';
$lang['Ph24Cloud.package_fields.tooltip.set_template'] = 'Choose whether the OS template is set by admin or selectable by the client during order.';
$lang['Ph24Cloud.package_fields.set_facility'] = 'Facility Selection';
$lang['Ph24Cloud.package_fields.set_facility_admin'] = 'Admin Only';
$lang['Ph24Cloud.package_fields.set_facility_client'] = 'Client Selectable';
$lang['Ph24Cloud.package_fields.facility_id'] = 'Facility ID';
$lang['Ph24Cloud.package_fields.tooltip.set_facility'] = 'Choose whether the facility is set by admin or selectable by the client during order.';
$lang['Ph24Cloud.package_fields.tooltip.facility_id'] = 'The facility/data center ID (only used if Facility Selection is set to "Admin Only").';
$lang['Ph24Cloud.package_fields.placeholder_no_module'] = '- No API server configured -';

// Service fields
$lang['Ph24Cloud.service_field.hostname'] = 'Hostname';
$lang['Ph24Cloud.service_field.image_id'] = 'Operating System';
$lang['Ph24Cloud.service_field.operating_system'] = 'Operating System';
$lang['Ph24Cloud.service_field.facility_id'] = 'Facility / Data Center';
$lang['Ph24Cloud.service_field.placeholder_no_images'] = '- No images available -';

// Tabs
$lang['Ph24Cloud.tab_actions'] = 'Server Actions';
$lang['Ph24Cloud.tab_stats'] = 'Server Statistics';
$lang['Ph24Cloud.tab_client_actions'] = 'Actions';
$lang['Ph24Cloud.tab_client_stats'] = 'Statistics';

// Client Actions (manage server from client area)
$lang['Ph24Cloud.client_actions.heading'] = 'Server Overview';
$lang['Ph24Cloud.client_actions.no_server'] = 'No server information is available for this service.';
$lang['Ph24Cloud.client_actions.field_status'] = 'Status';
$lang['Ph24Cloud.client_actions.field_power'] = 'Power State';
$lang['Ph24Cloud.client_actions.field_ips'] = 'IP Addresses';
$lang['Ph24Cloud.client_actions.button_start'] = 'Start Server';
$lang['Ph24Cloud.client_actions.button_stop'] = 'Stop Server';
$lang['Ph24Cloud.client_actions.button_reboot'] = 'Reboot Server';
$lang['Ph24Cloud.client_actions.action_success'] = 'Action %1$s accepted.';
$lang['Ph24Cloud.client_actions.action_failed'] = 'Action %1$s failed.';
$lang['Ph24Cloud.client_actions.status_unavailable'] = 'Unable to retrieve server status at this time.';
$lang['Ph24Cloud.client_actions.unexpected_error'] = 'An unexpected error occurred while performing this action.';
$lang['Ph24Cloud.client_actions.server_not_found'] = 'Server details not found for this service.';
$lang['Ph24Cloud.client_actions.edit'] = 'Edit';
$lang['Ph24Cloud.client_actions.change_hostname'] = 'Change Hostname';
$lang['Ph24Cloud.client_actions.hostname'] = 'Hostname';
$lang['Ph24Cloud.client_actions.hostname_placeholder'] = 'Enter new hostname';
$lang['Ph24Cloud.client_actions.action_change_hostname'] = 'Save Hostname';
$lang['Ph24Cloud.client_actions.hostname_required'] = 'Please enter a hostname.';
$lang['Ph24Cloud.client_actions.hostname_updated'] = 'Hostname updated.';
$lang['Ph24Cloud.client_actions.hostname_update_failed'] = 'Failed to update hostname.';
$lang['Ph24Cloud.client_actions.cancel'] = 'Cancel';
$lang['Ph24Cloud.client_actions.power_options'] = 'Power Options';

// Order options
$lang['Ph24Cloud.order_options.first'] = 'First Available Server';

// Errors - Module Row
$lang['Ph24Cloud.!error.account_name.empty'] = 'Please enter an account name.';
$lang['Ph24Cloud.!error.api_url.empty'] = 'Please enter an API URL.';
$lang['Ph24Cloud.!error.api_url.valid'] = 'The API URL is not valid.';
$lang['Ph24Cloud.!error.api_key.empty'] = 'Please enter an API key.';
$lang['Ph24Cloud.!error.api_key.valid'] = 'The API connection could not be established. Please verify your API key and URL.';
$lang['Ph24Cloud.!error.project_name_template.empty'] = 'Please enter a project name template.';

// Errors - Service
$lang['Ph24Cloud.!error.hostname.format'] = 'The hostname must be a valid domain name or IP address.';

// Errors - API
$lang['Ph24Cloud.!error.api.project_create'] = 'Failed to create project';
$lang['Ph24Cloud.!error.api.project_id'] = 'Project was created but no project ID was returned.';
$lang['Ph24Cloud.!error.api.networks_fetch'] = 'Failed to retrieve networks from the project.';
$lang['Ph24Cloud.!error.api.firewalls_fetch'] = 'Failed to retrieve firewalls from the project.';
$lang['Ph24Cloud.!error.api.no_resources'] = 'No networks or firewalls are available in the project. Please configure these resources in the PH24 Cloud dashboard first.';
$lang['Ph24Cloud.!error.api.image_id_missing'] = 'No OS template selected. Please select an operating system.';
$lang['Ph24Cloud.!error.api.server_create'] = 'Failed to create server';
$lang['Ph24Cloud.!error.api.server_id'] = 'Server was created but no server ID was returned.';
$lang['Ph24Cloud.!error.api.server_delete'] = 'Failed to delete server';
$lang['Ph24Cloud.!error.api.server_suspend'] = 'Failed to suspend server';
$lang['Ph24Cloud.!error.api.server_unsuspend'] = 'Failed to unsuspend server';

// Success messages
$lang['Ph24Cloud.!success.server_created'] = 'Server successfully created.';
$lang['Ph24Cloud.!success.server_deleted'] = 'Server successfully deleted.';
$lang['Ph24Cloud.!success.server_suspended'] = 'Server successfully suspended.';
$lang['Ph24Cloud.!success.server_unsuspended'] = 'Server successfully unsuspended.';
