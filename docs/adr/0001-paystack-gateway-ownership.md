# Snapshot Paystack gateway ownership on every payment

Each PayGo intent records the Paystack Gateway Account that initialized it, and callbacks always verify with that same account. Customer-owned gateways reverse the existing settlement direction by paying EaseVerifier through a system-beneficiary subaccount; the application never silently falls back to system credentials after choosing a customer gateway because references and subaccounts are scoped to their creating Paystack integration.
