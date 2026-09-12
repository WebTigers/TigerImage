<!-- tiger:doc
header: true
order: 30
title: Budgets
visibility: admin
-->

# Budgets

Image calls cost orders of magnitude more than text, and the point of this module is to let an **agent**
issue them in a loop. So the budget is checked **before** the provider is contacted — a cap you discover
by going over it is not a cap — and it is **hard by default**, because an agent does not read warnings.

## Two ceilings

| Setting | What it limits |
|---|---|
| `tigerimage.spend.monthly_cap` | USD per calendar month for the whole organisation. Unset = uncapped. |
| `tigerimage.spend.token_cap` | USD per month for **any** caller using an access key. |
| `tigerimage.spend.token_cap_for.<key id>` | Overrides the blanket key cap for one specific key. |
| `tigerimage.spend.enforce` | `hard` refuses; `soft` allows and reports. Hard by default. |

The organisation cap protects your wallet. The **key** cap protects it from a single access key: a
scoped token handed to an agent should not be able to spend the whole budget just because the agent is
entitled to spend *some* of it.

**Both are checked and the tighter one wins.** A refusal tells you *which* one stopped you, so you know
whether to raise the organisation cap or widen one key.

Signing in through the browser is not subject to the key cap — a person clicking Generate is not the
runaway risk this exists for.

## The gauge

The studio shows a small bar of the budget still available. It always shows the ceiling that will
actually stop your next call, so it can never look healthy while a refusal is one click away.

It runs **green down to 40% remaining**, then shifts steadily toward **red at 10%** and below. The colour
moves smoothly rather than in steps, because what it is reporting is a budget draining, not a series of
thresholds being crossed. An uncapped install shows no gauge at all — there is no ceiling to draw.

## These are estimates

Providers do not return a price with an image, and their published rates move. Every figure here is an
**estimate**, labelled as one. It exists to stop a runaway loop and show you roughly where the money
went — not to reconcile an invoice. Check your provider's dashboard for what you were actually billed.

An unrecognised model is charged at an unknown-cost rate, never at zero, so a newly released model is
never the one thing your cap cannot stop.
