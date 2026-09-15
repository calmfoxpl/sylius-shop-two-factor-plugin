# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-09-15

### Added

- Two-factor authentication for shop customers: passkey (WebAuthn), authenticator app (TOTP) or a 6-digit code by e-mail.
- *My account → Login security* page: each method is turned on only after a successful first use and turned off with the current password; e-mail codes are throttled.
- Second-factor step at login in the shop look, with the most convenient method first and switching between methods.
- Policy (required, optional, turned off) set in *Configuration → Two-factor authentication for customers*; "required" locks the account area only, never shopping or checkout.
- Customer card in the panel with separate *Reset 2FA* and *Turn off 2FA*, and `calmfox:shop:2fa:reset`.
- Access to the 2FA pages granted by route name; the policy condition, form renderers and the `shop_passkey` provider apply to shop users only.
- English and Polish translations.

[Unreleased]: https://github.com/calmfoxpl/sylius-shop-two-factor-plugin/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/calmfoxpl/sylius-shop-two-factor-plugin/releases/tag/v1.0.0
