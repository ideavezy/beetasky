<?php

use App\Mcp\Servers\ProjectManagementServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP (Model Context Protocol) Routes
|--------------------------------------------------------------------------
|
| Here is where you can register MCP servers for your application.
| These servers expose tools, resources, and prompts to AI clients.
|
*/

// Project Management MCP Server
// Accessible via SSE at /mcp/projects
Mcp::web('/mcp/projects', ProjectManagementServer::class)
    ->middleware(['mcp.auth']);
