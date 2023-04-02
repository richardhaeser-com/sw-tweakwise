# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [Unreleased]

### Fixed
- In the template of the feed, the price was rounded while this was already done in the generation of data which could lead to wrongly rounded prices. In the template we don't do any rounding of prices anymore.

## [1.0.1] - 31-03-2023

### Fixed
- Search is now only searching in the root category of the current shop

### Changed
- Labels on the result pages are now translated based on the language of the domain
- By changing a bit of code, we are now supporting Shopware 6.4.10 and higher
- Some inline Javascript is moved towards a dedicated JS file


## [1.0.0] - 28-03-2023

### Added
- Added automatic checks to find bugs earlier
- Made ready for Shopware store

## [0.9.6] - 28-03-2023

### Changed
- **[BREAKING]** The extension key is changed from RhTweakwise to RhaeTweakwise to comply to the rules of the Shopware store. Please make sure to install and activate the right plugin after you update the extension. 

## [0.9.5] - 27-03-2023

### Fixed
- Added right annotation for translator to prevent errors in logs 

## [0.9.4] - 27-03-2023

### Added
- Added translator to make sure labels can be translated even when the feed is built on CLI

## [0.9.3] - 27-03-2023

### Changed
- Generation of the feed is done different now. The feed is not based on a product comparison feed, but it is based on code within the plugin. If you are using the old way, this will still work, but please be aware of the new possibility of the feed. See the documentation for more information.

### Fixed
- Added some checks if we can add the custom fields of the sales channel to the page

## [0.9.2] - 07-03-2023

### Added
- A label property is now added to the product feed

### Fixed
- Fixed issue that was hiding results when submitting search form with basic javascript integration
- Resolved an issue with the subscriber throwing an error on async reloads of the frontend

## [0.9.1] - 03-03-2023

### Added
- Possibility to enable standard javascript implementation
- Added attributes new, discount and topseller to product feed

### Changed
- Added link to this changelog to the readme of the extension
- **[BREAKING]** Changed extension key from Tweakwise to RhTweakwise

### Fixed
- Exclude property group when one of the properties of that group was already set as a variant option.

## [0.9.0] - 24-02-2023

### Added
- Sales channel templates for Tweakwise product feed
