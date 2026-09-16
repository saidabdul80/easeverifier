# Payments

The payments context identifies who owns each Paystack integration and how PayGo proceeds are settled.

## Language

**Paystack Gateway Account**:
The system or customer Paystack integration whose credentials initialize and verify a payment.
_Avoid_: Paystack key, account key

**Settlement Subaccount**:
A Paystack subaccount created inside one Gateway Account for a named beneficiary.
_Avoid_: Split key, receiving account

**Settlement Strategy**:
The direction in which a PayGo payment is divided between the Gateway Account owner and a Settlement Subaccount beneficiary.
_Avoid_: Split mode
