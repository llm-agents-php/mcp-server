# PHP MCP Server SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/llm/mcp-server.svg?style=flat-square)](https://packagist.org/packages/llm/mcp-server)
[![Total Downloads](https://img.shields.io/packagist/dt/llm/mcp-server.svg?style=flat-square)](https://packagist.org/packages/llm/mcp-server)
[![License](https://img.shields.io/packagist/l/llm/mcp-server.svg?style=flat-square)](LICENSE)

**A PHP SDK for building [Model Context Protocol (MCP)](https://modelcontextprotocol.io/introduction)
servers. Create production-ready MCP servers in PHP with modern architectur, and flexible transport
options.**

This SDK enables you to expose your PHP application's functionality as standardized MCP **Tools**, **Resources**, and *
*Prompts**, allowing AI assistants (like Anthropic's Claude, Cursor IDE, OpenAI's ChatGPT, etc.) to interact with your
backend using the MCP standard.

## 🚀 Key Features

- **🏗️ Modern Architecture**: Built with PHP 8.3+ features, PSR standards, and modular design
- **📡 Multiple Transports**: Supports `stdio`, `http+sse`, and new **streamable HTTP** with resumability
- **🔧 Flexible Handlers**: Support for closures, class methods, static methods, and invokable classes
- **⚡ Session Management**: Advanced session handling with multiple storage backends
- **🔄 Event-Driven**: ReactPHP-based for high concurrency and non-blocking operations
- **📊 Batch Processing**: Full support for JSON-RPC batch requests
- **🧪 Completion Providers**: Built-in support for argument completion in tools and prompts
- **🔌 Dependency Injection**: Full PSR-11 container support with auto-wiring
- **📋 Comprehensive Testing**: Extensive test suite with integration tests for all transports

This package supports the **2025-03-26** version of the Model Context Protocol with backward compatibility.

## 📋 Requirements

- **PHP** >= 8.3
- **Composer**
- **For HTTP Transport**: An event-driven PHP environment (CLI recommended)
- **Extensions**: `json`, `mbstring`, `pcre` (typically enabled by default)

## 📦 Installation

```bash
composer require llm/mcp-server
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## 📄 License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

## 🙏 Acknowledgments

- Built on the [Model Context Protocol](https://modelcontextprotocol.io/) specification
- Powered by [ReactPHP](https://reactphp.org/) for async operations
- Uses [PSR standards](https://www.php-fig.org/) for maximum interoperability
