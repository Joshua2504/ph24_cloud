# PH24 Cloud Blesta Module

A Blesta provisioning module for automating virtual server management on the PH24 Cloud platform.

## Features

- **Automated Provisioning**: Automatically create and provision virtual servers when orders are placed
- **Per-Client Projects**: Creates isolated projects for each customer with customizable naming templates
- **OS Template Selection**: Allow clients to choose their operating system during order or set it at the package level
- **Lifecycle Management**: Full support for suspension, unsuspension, and termination
- **Network & Firewall Auto-Configuration**: Automatically creates default networks and firewalls for new projects
- **API Integration**: Uses PH24 Cloud API v1 with X-API-KEY authentication

## Installation

1. Copy the `ph24_cloud` folder to your Blesta installation:
   ```
   /path/to/blesta/components/modules/ph24_cloud/
   ```

2. Log in to your Blesta admin panel

3. Navigate to **Settings > Modules**

4. Click the **Available** tab

5. Find "PH24 Cloud" and click **Install**

## Configuration

### 1. Add API Server

After installation, you need to add at least one API server configuration:

1. Navigate to **Settings > Modules**
2. Find "PH24 Cloud" and click **Manage**
3. Click **Add API Server**
4. Fill in the form:
   - **Account Name**: A friendly name for this connection (e.g., "PH24 Production")
   - **API URL**: `https://ph24.io/service` (default)
   - **API Key**: Your PH24 Cloud API key
   - **Project Name Template**: Template for customer projects (default: `cust-{id}`)
     - Use `{id}` as a placeholder for the client ID
     - Example: `cust-{id}` → `cust-123` for client ID 123

5. Click **Add Server**

The module will test the connection to verify your API credentials.

### 2. Create Packages

Create packages (products) to sell virtual servers:

1. Navigate to **Packages > Browse Packages**
2. Click **Create Package**
3. Fill in basic package information (name, description, pricing, etc.)
4. In the **Module** section:
   - **Module**: Select "PH24 Cloud"
   - **Module Group**: Select your API server
   - **Plan/Flavor ID**: Enter the PH24 flavor ID (e.g., `vc2-2c-4gb`)
   - **Availability Zone**: Enter the data center location (e.g., `fra1`)
   - **Template Selection**:
     - **Admin Only**: You specify the OS template in the package
     - **Client Selectable**: Clients choose the OS during ordering
   - **Image ID**: Only required if "Template Selection" is set to "Admin Only"

5. Save the package

### 3. Assign to Order Forms

Assign your packages to order forms so clients can purchase virtual servers.

## API Credentials

To obtain your PH24 Cloud API key:

1. Log in to your PH24 Cloud account at https://ph24.io
2. Navigate to API settings
3. Generate or copy your API key
4. Use this key when configuring the Blesta module

## Project Management

The module creates one project per Blesta client:

- **First Service**: When a client orders their first VPS, the module creates a new project in PH24 Cloud
- **Project Naming**: Uses the template defined in module row settings (default: `cust-{id}`)
- **Subsequent Services**: All additional VPS orders from the same client are provisioned in their existing project
- **Auto-Configuration**: Default network and firewall are automatically created for new projects

## Service Lifecycle

### Provisioning

When a client orders a VPS:

1. Module checks if client has an existing PH24 project
2. If not, creates a new project with customized name
3. Verifies or creates default network and firewall
4. Creates the virtual server with specified configuration
5. Returns server details (IP addresses, credentials) to Blesta

### Suspension

When a service is suspended in Blesta:

- Module sends a STOP action to the server via PH24 API
- Server is powered off but data remains intact

### Unsuspension

When a service is unsuspended:

- Module sends a START action to the server
- Server is powered back on

### Termination

When a service is canceled:

- Module permanently deletes the server from PH24 Cloud
- Project remains for potential future services

## Troubleshooting

### Connection Test Fails

**Error**: "The API connection could not be established."

**Solutions**:
- Verify your API key is correct
- Ensure the API URL is `https://ph24.io/service`
- Check your server can reach the PH24 API (firewall rules)

### No Resources Available

**Error**: "No networks or firewalls are available in the project."

**Cause**: The module tried to provision a server but found no network resources.

**Solutions**:
- The module should auto-create these resources
- Verify your API key has permission to create networks and firewalls
- Manually create a network and firewall in the PH24 dashboard

### Server Creation Fails

**Error**: "Failed to create server"

**Possible Causes**:
- Invalid flavor ID (plan doesn't exist)
- Invalid availability zone
- Invalid image ID (OS template doesn't exist)
- Insufficient resources in the project

**Solutions**:
- Verify flavor IDs using the PH24 API: `GET /v1/vps/plans`
- Verify image IDs using the PH24 API: `GET /v1/vps/images`
- Check availability zones are correct
- Review API logs in Blesta module logs

### Template Selection Not Showing

**Issue**: Clients can't select OS template during order

**Solution**:
- Edit your package
- Set "Template Selection" to "Client Selectable"
- Save the package

## API Endpoints Used

The module interacts with the following PH24 API endpoints:

- `GET /v1/cloud/project` - List projects
- `POST /v1/cloud/project` - Create project
- `GET /v1/cloud/project/{projectId}/network` - List networks
- `POST /v1/cloud/project/{projectId}/network` - Create network
- `GET /v1/cloud/project/{projectId}/firewall` - List firewalls
- `POST /v1/cloud/project/{projectId}/firewall` - Create firewall
- `POST /v1/cloud/project/{projectId}/server` - Create server
- `GET /v1/cloud/project/{projectId}/server/{serverId}` - Get server details
- `DELETE /v1/cloud/project/{projectId}/server/{serverId}` - Delete server
- `POST /v1/cloud/project/{projectId}/server/{serverId}/action` - Server actions (START, STOP, REBOOT)
- `GET /v1/vps/plans` - List available plans
- `GET /v1/vps/images` - List available OS templates

## Module Files

```
ph24_cloud/
├── config.json                    # Module metadata
├── ph24_cloud.php                 # Main module class
├── apis/
│   └── ph24_api.php               # API wrapper
├── language/
│   └── en_us/
│       └── ph24_cloud.php         # English translations
└── views/
   └── default/
      ├── add_row.pdt           # Add API server form
      ├── edit_row.pdt          # Edit API server form
      └── manage.pdt            # Manage module page
```

## Requirements

- Blesta 5.0 or higher
- PHP 7.4 or higher
- PHP cURL extension
- Valid PH24 Cloud account with API access

## Support

For issues with:
- **The module**: Check Blesta logs at **Tools > Logs > Module Logs**
- **PH24 Cloud API**: Contact PH24 support at https://ph24.io

## Version History

### 1.0.0 (2026-01-24)
- Initial release
- Basic server provisioning
- Per-client project management
- Suspension/unsuspension support
- Client-selectable OS templates
- Auto-configuration of networks and firewalls

## License

Copyright © 2024-2026 PH24.io
