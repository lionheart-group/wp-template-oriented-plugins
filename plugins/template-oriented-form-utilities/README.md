# TOFU

Template-Oriented Form Utilities

## Description

This plugin provides a set of utilities for creating and managing template-oriented forms in WordPress. It allows developers to create forms that are based on templates, making it easier to manage and maintain form layouts and functionality.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/template-oriented-form-utilities` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.

## Usage

First, you need to setup your form settings.

```php
<?php
// functions.php

add_action('init', function () {
    \TofuPlugin\Helpers\Form::setup(new \TofuPlugin\Structure\FormConfig(
        key: 'form',
        inputPath: '/contact/',
        confirmPath: '/contact/confirm/',
        resultPath: '/contact/result/',
    ));
});
```
