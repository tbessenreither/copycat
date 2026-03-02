
# PHP Copycat

## What is it?
PHP Copycat is a Composer package that provides a simple and safe way for PHP packages to automate file copying and configuration modifications in the projects that depend on them. It allows package authors to define a `CopycatConfig` class with operations like copying files, modifying JSON configurations, adding entries to `.gitignore`, and registering Symfony bundles. These operations are executed automatically when users run `composer install` or `composer update`, ensuring that necessary setup steps are performed without manual intervention.

All operations are designed to be safe and are based on a whitelist of allowed namespaces, so the project needs to explicitly allow the package to perform operations. Additionally, Copycat includes smart system validation to ensure that operations are only executed if the project matches the expected system (e.g., only copying Symfony files if the project is a Symfony app). This prevents accidental or unsafe changes in the wrong type of project.

Config files like the `composer.json` have additional protections to prevent malicious or accidental changes. For example, writes outside of the `extra` section are not allowed via json modifier, and the `copy` operation will not allow copying files from outside your package scope, ensuring safe and predictable automation. Additional to that all targets are predefined via enums in copycat, so there is no possibility to write to arbitrary paths in the project. This makes it a secure and reliable tool for package authors to enhance the user experience of their packages with necessary setup steps.

---


## Features

### Current features

- Copy files to specific targets (e.g., DDEV commands, Symfony, public, etc.)
- Add or modify JSON values at any path in a target file
- Add entries to your project's `.gitignore` in a grouped, idempotent way
- Register Symfony bundles automatically in `config/bundles.php`
- **Smart system validation:** Only runs operations if your project matches the expected system for the target (e.g., Symfony, DDEV)
- Secure: prevents copying files from outside your package scope
- Easily integrate with Composer scripts for automation
- Partial reversal of operations on package removal (removing copied files, removing bundle, removing .gitignore section)
- Adding values to .env files. All variables will be grouped by package namespace, and you can choose to overwrite existing entries or keep them to prevent accidental overwrites of user values.

### Planned Features

PHP Copycat is actively developed. Planned features include:
- Support for reversing all operations on package removal (e.g., removing copied files, removing bundle, removing services, removing .gitignore section).
- Support for modifying yaml configuration files
- Echo of messages after execution (e.g., "Package [Packagename]: To use this package, do X, Y, Z...")

### Operation support matrix

Legend: ✅ supported, 🔨 working on it, 🔴 not supported

|            | copy | jsonAdd | gitIgnoreAdd | symfonyBundleAdd | symfonyServiceYaml | envAdd |
|------------|------|---------|--------------|------------------|--------------------|--------|
| Write      | ✅   | ✅      | ✅           | ✅               | ✅                 | ✅     |
| Revert     | ✅   | ✅      | ✅           | ✅               | 🔨                 | ✅     |


---

## Setup

Require Copycat as a dependency via composer.
```bash
composer require tbessenreither/copycat
```

While you don't technically need copycat as a dependency in your package it is recommendet to at least use it as a dev dependency so you can take advantage of autocompletion and prevent errors.


### Within a package

Create a `CopycatConfig` class in the autoload root of your package that implements `CopycatConfigInterface`. This class will define the operations to be performed in the projects that depend on your package (e.g., copying files, modifying JSON, adding .gitignore entries, registering Symfony bundles). See the [Available Operations](#available-operations) section below for details and examples.

Example:
```php
<?php declare(strict_types=1);
namespace Tbessenreither\MyPackage;

use Tbessenreither\Copycat\Enum\CopyTargetEnum;
use Tbessenreither\Copycat\Interface\CopycatConfigInterface;
use Tbessenreither\Copycat\Interface\CopycatInterface;

class CopycatConfig implements CopycatConfigInterface
{
    public static function run(CopycatInterface $copycat): void
    {
        $copycat->copy(
            target: CopyTargetEnum::DDEV_COMMANDS_WEB,
            file: 'ddev/commands/web/test-command.sh',
            overwrite: false,
            gitIgnore: true,
        );
    }
}
```

### Within a project

To execute Copycat operations in a project, add the following to your `composer.json` scripts section:

```json
"scripts": {
    "post-install-cmd": [
        "Tbessenreither\\Copycat\\Runner::run"
    ],
    "post-update-cmd": [
        "Tbessenreither\\Copycat\\Runner::run"
    ],
    "pre-package-uninstall": [
        "Tbessenreither\\Copycat\\Runner::run"
    ]
}
```

On first execution Copycat will add a boilerplate Whitelist to your `composer.json` if it doesn't exist, which you can then customize to allow specific packages to perform operations in your project. This ensures that no package can perform operations without your explicit permission.

The section will look something like this after the first execution:
```json
"extra": {
    "copycat": {
        "whitelist": [
            "Tbessenreither\\"
        ]
    }
}
```

This is it. Whenever you run `composer install`, `composer update`, or `composer remove`, Copycat will automatically execute the defined operations in the `CopycatConfig` classes of your dependencies, performing necessary setup steps in a safe and system-aware manner.


Example output on execution:

```text
Running PHP Copycat...
Running copycat for namespace Tbessenreither\FeatureFlagServiceClient
    - Adding value to src/test.json at path nested.level1.level2.level3
        Loading file: /var/www/html/src/test.json
        Storing modifications for: /var/www/html/src/test.json
Running copycat for namespace Tbessenreither\MultiLevelCache
    - copy bin/mlc-make to .ddev/commands/web
    - copy bin/mlc-update to .ddev/commands/web
        copy Error - Destination file already exists: /var/www/html/.ddev/commands/web/mlc-update
    - Adding 2 entries to .gitignore:
        Loading file: /var/www/html/.gitignore
        Added 0 entries to .gitignore, skipped 2 entries that already exist.
        Storing modifications for: /var/www/html/.gitignore
    - Adding Tbessenreither\MultiLevelCache\DataCollector\MultiLevelCacheDataCollector to symfony bundles.php.
        Loading file: /var/www/html/config/bundles.php
        symfonyBundleAdd Error - Bundle class Tbessenreither\MultiLevelCache\DataCollector\MultiLevelCacheDataCollector does not implement the Symfony BundleInterface. This will not be added to bundles.php
    - Adding service Tbessenreither\Copycat\Copycat to symfony services.yaml.
        Loading file: /var/www/html/config/services.yaml
        symfonyAddServiceToYaml Error - Service Tbessenreither\Copycat\Copycat is already registered in services.yaml, skipping.

Writing buffered file modifications to disk...
    - Writing file to disk: /var/www/html/src/test.json
    - Writing file to disk: /var/www/html/.gitignore
    - Writing file to disk: /var/www/html/config/bundles.php
    - Writing file to disk: /var/www/html/config/services.yaml

PHP Copycat finished.
```

Copycat now supports partial reversal of operations on package removal.
```text
Running PHP Copycat...
Reverting copycat for namespace Tbessenreither\FeatureFlagServiceClient
    - Removing src/CopycatConfig.php from public
    - Removing .gitignore entries:
        Loading file: /var/www/html/.gitignore
        Storing modifications for: /var/www/html/.gitignore
    - Removing Tbessenreither\FeatureFlagServiceClient\Bundle\FeatureFlagClientBundle from symfony bundles.php.
        Loading file: /var/www/html/config/bundles.php
        Storing modifications for: /var/www/html/config/bundles.php

Writing buffered file modifications to disk...
    - Writing file to disk: /var/www/html/.gitignore
    - Writing file to disk: /var/www/html/config/bundles.php

PHP Copycat finished.
```

## Available Operations

Table of operations
- [copy](#copy)
- [jsonAdd](#jsonadd)
- [gitIgnoreAdd](#gitignoreadd)
- [symfonyBundleAdd](#symfonybundleadd)
- [symfonyAddServiceToYaml](#symfonyaddservicetoyaml)
- [envAdd](#envadd)

### copy

Copy a file from your package to a specific target in the project (e.g., DDEV commands, Symfony config, public directory, etc.). The operation will only be executed if the project matches the expected system for the target (e.g., only copying Symfony files if the project is a Symfony app). This ensures that files are only copied in relevant projects, preventing accidental changes in the wrong type of project.

```php

$copycat->copy(
    target: CopyTargetEnum::DDEV_COMMANDS_WEB, # The target location for the copied file. Renaming is not supported, the file will be copied with the same name to the target location.
    file: 'ddev/commands/web/test-command.sh', # Path to the file in your package from the root directory (Not the autoload path of your namespace)
    overwrite: false, # Whether to overwrite the file if it already exists in the target location. Default is false to prevent accidental overwrites.
    gitIgnore: true, # Optionally add the copied file to .gitignore. Default is false.
);
```

#### Available targets
- `CopyTargetEnum::DDEV_COMMANDS_WEB` - copies to the `.ddev/commands/web` directory of the project. Only runs if the project is a DDEV project.
- `CopyTargetEnum::DDEV_COMMANDS_HOST` - copies to the `.ddev/commands/host` directory of the project. Only runs if the project is a DDEV project.
- `CopyTargetEnum::SYMFONY_BIN` - copies to the `bin` directory of the project. Only runs if the project is a Symfony app.
- `CopyTargetEnum::SYMFONY_CONFIG_PACKAGES` - copies to the `config/packages` directory of the project. Only runs if the project is a Symfony app.
- `CopyTargetEnum::SYMFONY_CONFIG_ROUTES` - copies to the `config/routes` directory of the project. Only runs if the project is a Symfony app.
- `CopyTargetEnum::PUBLIC` - copies to the `public` directory of the project.
- `CopyTargetEnum::COPYCAT_CONFIG` - copies files to the `.copycat` directory in the project root.

### jsonAdd

Add or modify JSON values at any path in a target JSON file. The operation will only be executed if the project matches the expected system for the target (e.g., only modifying Symfony config if the project is a Symfony app). This ensures that configuration changes are only made in relevant projects, preventing accidental changes in the wrong type of project.

```php
$copycat->jsonAdd(
    target: JsonTargetEnum::COMPOSER_JSON, # The target JSON file to modify. This determines the expected system for the operation (e.g., only allowing modifications to composer.json in general projects, only allowing modifications to Symfony config files in Symfony apps, etc.). Also determines which paths are allowed to be modified (e.g., only allowing modifications in the extra section of composer.json) to ensure safe modifications.
    path: 'extra.somePackageConfig', # Dot notation of the path through the JSON structure where the value should be added or modified. If the path does not exist, it will be created.
    value: [
        'key1' => 'value1',
        'key2' => 'value2',
    ],
    overwrite: true, # Whether to overwrite the value if it already exists at the specified path. Default is false to prevent accidental overwrites.
);
```

#### Available targets
- `JsonTargetEnum::COMPOSER_JSON` - modifies the `composer.json` file of the project. Only allows modifications in the `extra` section to ensure safe changes.


### gitIgnoreAdd

Add entries to the project's `.gitignore` file grouped by package namespace so you see what packages are adding which entries. The operation will only be executed if the project is a git repository.

```php
$copycat->gitIgnoreAdd(
    entries: [ # this can also be a string if you want to add just a single entry
        'ignored-file.txt',
        'ignored-directory/*',
    ],
);
```

### symfonyBundleAdd

Register a Symfony bundle automatically in `config/bundles.php`. The operation will only be executed if the project is a Symfony app. This methods checks the given class for implementing the Symfony `BundleInterface` to prevent invalid entries in `bundles.php`. If the class does not implement the interface or other problems, the method will refuse to add the bundle and print the error in the console output.

```php
$copycat->symfonyBundleAdd(
    bundleClassName: Tbessenreither\MyPackage\MyPackageBundle::class,
);
```

### symfonyAddServiceToYaml

Register a Symfony service automatically in `config/services.yaml`. The operation will only be executed if the project is a Symfony app. This methods checks if the service is already registered to prevent duplicate entries in `services.yaml`. If the service is already registered or other problems occur, the method will refuse to add the service and print the error in the console output.

```php
$copycat->symfonyAddServiceToYaml(
    serviceClassName: Tbessenreither\MyPackage\MyPackageService::class,
    arguments: [ # Optional array of arguments to be passed to the service. This can be used to automatically wire services with their dependencies.
        '$argument1' => 'value1',
        '$argument2' => 'value2',
    ],
);
```

### envAdd

Add values to .env files. Keys will be sorted into the group that writes it, even if they are already part of the target file. This ensures that it's always clear which package is responsible for which entries in the .env file.
You can choos to overwrite existing entries by setting the overwrite flag to true, this is helpfull for managing entries in the `.env.example` file where you want to make sure that the entries are always up to date, but for the regular `.env` file it's recommended to keep overwrite set to false to prevent accidental overwrites of user values.

```php

$copycat->envAdd(
    entries: [ # this can also be a string if you want to add just a single entry
        'MY_ENV' => 'value',
        'MY_OTHER_ENV_VAR' => 'other_value',
    ],
    overwrite: false, # Whether to overwrite the value if the key already exists in the .env file. Default is false to prevent accidental overwrites.
);
```

#### Available targets
 All files will be created if they don't exist. There is no system check for this operation, as .env files are used in various types of projects, so it's up to you to make sure that you are adding entries to the right file for your project type and use case.

- `EnvTargetEnum::DOT_LOCAL` - modifies the `.env.local` file.
- `EnvTargetEnum::DOT_TEST` - modifies the `.env.test` file.
- `EnvTargetEnum::DOT_EXAMPLE` - modifies the `.env.example` file.

---

## License

MIT
