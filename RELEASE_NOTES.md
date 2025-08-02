# Release Notes

## Version 2.0.0

### Added
- Interface bindings support
- Singleton instances caching
- Specific container exceptions
- Strict type declarations
- PSR-11 compliant dependency injection container
- Automatic dependency resolution
- Constructor injection
- Service factory support

### Changed
- Service factory wrapped in closure
- Modified constructor behavior
- Improved dependency resolution
- Enhanced error handling
- Optimized dependency resolution with better error handling
- Improved type hints and documentation
- Enhanced exception messages with more context
- Better separation of concerns in code structure
- Moved source code to `src/` directory following PSR-4 standards

### Development Tools
- Added comprehensive test suite with PHPUnit
- Integrated PHP CodeSniffer for PSR-12 compliance
- Added development scripts for testing and code style

### Upgrade Notes
- Ensure factories are callable and update exception handling
- Update namespace from `Solo\` to `Solo\Container\`
- Update autoloading path to use `src/` directory

### Requirements
- PHP 8.1 or higher
- PSR Container 2.0 or higher 