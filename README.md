# Dotdigital Personalization for Magento 2
[![license](https://img.shields.io/github/license/mashape/apistatus.svg)](LICENSE.md)

## Requirements
- PHP 7.4+
- Magento 2.4.2+

## Usage and support
Full support documentation and setup guides are available [here](https://personalization.help.dotdigital.com/en/articles/8548328-magento-2-extensions).

## Migration from Freshrelevance/Freshrelevanceddl
Merchants migrating from the `freshrelevance/freshrelevanceddl` module to the `dotdigital/dotdigital-magento2-extension-personalization` module should note the following:
- The `Freshrelevance/Freshrelevanceddl` PHP namespace is replaced by `Dotdigitalgroup\Personalization`
- Config keys previously stored with paths like `freshrelevance_ddl_` will be automatically migrated to matching paths like `dd_personalization_`.
