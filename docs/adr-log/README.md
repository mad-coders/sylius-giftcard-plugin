# Architectural decision log

Load-bearing decisions for this plugin, each with its context, the decision itself, and the rules
it puts on future changes. Read the relevant ADR **before** changing the area it governs, and don't
introduce a second way of doing the same thing.

New decision? Copy the next number and follow the same shape (Status / Context / Decision /
Consequences / Rules).

| # | Decision |
|---|---|
| [0001](0001-sylius-plugin-resource-model.md) | Built on the Sylius resource model |
| [0002](0002-doctrine-xml-mapped-superclasses.md) | Doctrine mapping as XML mapped superclasses |
| [0003](0003-service-wiring.md) | Service wiring in XML, modular by concern |
| [0004](0004-gift-card-redemption-as-order-adjustment.md) | Redemption as an order adjustment |
| [0005](0005-two-customer-links-and-transaction-ledger.md) | Purchaser + redeemer links and a balance ledger |
| [0006](0006-quality-tooling.md) | Quality tooling: PHPStan, ECS, Rector, PHPUnit, Behat via Make |
| [0007](0007-github-actions-ci.md) | CI on GitHub Actions with a Sylius/Symfony/database matrix |
| [0008](0008-conventional-commits.md) | Conventional Commits and the trunkless `1.0` branch model |
| [0009](0009-no-pdf-in-1-0.md) | No PDF gift cards in 1.0 |
| [0010](0010-gift-card-as-tender.md) | A gift card is tender, not a discount (supersedes 0004's mechanism) |
| [0011](0011-symfony-workflow-only.md) | Symfony Workflow only, no winzou wiring (supersedes 0004's wiring) |
| [0012](0012-rate-limiting-gift-card-redemption.md) | Redemption rate limited on the client address, one message for every refusal |
| [0013](0013-gift-card-sale-mode.md) | Selling gift cards is a per-channel mode, enforced twice |
