<?php

declare(strict_types=1);

namespace Mcp\Server;

enum RequestMethod: string
{
    case Initialize = 'initialize';
    case Ping = 'ping';
    case NotificationsInitialized = 'notifications/initialized';
    case CompletionComplete = 'completion/complete';
    case LoggingSetLevel = 'logging/setLevel';
    case PromptsList = 'prompts/list';
    case PromptsGet = 'prompts/get';
    case ResourcesList = 'resources/list';
    case ResourcesTemplatesList = 'resources/templates/list';
    case ResourcesRead = 'resources/read';
    case ResourcesSubscribe = 'resources/subscribe';
    case ResourcesUnsubscribe = 'resources/unsubscribe';
    case ToolsList = 'tools/list';
    case ToolsCall = 'tools/call';
}
