# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
