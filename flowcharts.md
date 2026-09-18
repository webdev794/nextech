# NexTech — Major Event Flowcharts

Diagrams of the software's key end-to-end flows, using standard flowchart
notation. Rendered automatically by GitHub, GitLab, and most Markdown viewers
that support Mermaid; open in an editor otherwise for the raw diagram source.

## Legend

```mermaid
flowchart LR
    A([Start / End]) --> B[Process step]
    B --> C{Decision}
    C --> D[\Manual action/]
    D --> E[/Data typed in or shown/]
    E --> F[(Data saved)]
```

| Shape | Meaning |
| --- | --- |
| Stadium `([...])` | Start or end of a flow |
| Rectangle `[...]` | An automated / system process step |
| Diamond `{...}` | A decision or branch point |
| Trapezoid `[\...+/]` | A manual action — someone taps a button or does something physical |
| Parallelogram `[/.../]` | Data typed in, or a message shown/posted |
| Cylinder `[(...)]` | A value saved to the database |

---

## 1. Order lifecycle

```mermaid
flowchart TD
    A([Customer checks out]) --> B{Payment method?}
    B -->|Card| C[pending_payment]
    B -->|Cash on delivery| D[confirmed]
    C --> C1{Stripe payment succeeds?}
    C1 -->|Yes| D
    C1 -->|Never completed| Z1([cancelled])
    D --> E[packing]
    E --> F[ready_for_delivery]
    F --> G[out_for_delivery]
    G --> H[completed]
    D -->|Customer or admin cancels| Z1
    E -->|Customer or admin cancels| Z1
    F -->|Customer or admin cancels| Z1
    G -->|Rider reports refused C.O.D.| Z2([cancelled — refused C.O.D.])
    H --> I[/Receipt + PDF bill emailed/]
    I --> Z3([Order complete])
```

---

## 2. Rider delivery offer

```mermaid
flowchart TD
    A([Order becomes ready_for_delivery]) --> B[Auto-assign nearest on-shift rider, or admin assigns one]
    B --> C[/Offer shown to rider — 60s countdown + repeating alarm/]
    C --> D[\Rider responds/]
    D -->|Accept| E[Rider accepted]
    D -->|Reject| F[Declined]
    D -->|Timer runs out| F
    F --> G{Another eligible rider?}
    G -->|Yes| C
    G -->|No| H[Falls to the shared pickup pool]
    H --> I[\Any rider taps Pick up/]
    E --> J([out_for_delivery])
    I --> J
```

---

## 3. Cash-on-delivery cash flow

```mermaid
flowchart TD
    A([Order out_for_delivery, C.O.D.]) --> B{Customer pays cash at the door?}
    B -->|Yes| C[\Rider taps Cash collected/]
    C --> D[(payment_status = paid)]
    D --> E[\Rider marks it Delivered/]
    E --> F[Cash sits with the rider — cod_holding_cents]
    F --> G[\Admin taps Confirm cash returned/]
    G --> H[(cash_settled_at stamped)]
    H --> Z1([Cleared from rider dashboard])
    B -->|No, refuses to pay| I[/Rider enters a required note/]
    I --> J[\Rider taps Customer refused to pay/]
    J --> K[(status = cancelled, cancelled_by = rider)]
    K --> L[/Admin bell + rider dashboard flag: items still with rider/]
    L --> M[\Admin taps Items returned to store/]
    M --> N[(items_returned_at stamped)]
    N --> Z2([Reminder clears])
```

---

## 4. Refund and store-credit issuance

```mermaid
flowchart TD
    A([Admin opens an order or chat thread]) --> B{Refund type?}
    B -->|Stripe refund| C[/Admin picks items or types an amount + reason/]
    C --> D[\Admin taps Refund via Stripe/]
    D --> E[(Stripe refund issued)]
    E --> F[/Chat: "Refund of $X issued." — customer sees this/]
    E --> G[/Chat: "Refund reason: ..." — internal note, staff only/]
    B -->|Store credit| H[/Admin picks items or types an amount + reason/]
    H --> I{Gift card already issued for this order?}
    I -->|Yes| X([Blocked — one gift card per order, ever])
    I -->|No| J[\Admin taps Issue store credit/]
    J --> K[(Gift card code + one-time password created)]
    K --> L[/Chat: code + password — customer sees this/]
    K --> M[/Chat: "Reason: ..." — internal note, staff only/]
    L --> N[\Customer or admin applies the card to an order/]
    N --> O{Card belongs to that order's own customer?}
    O -->|No| Y([Rejected — ownership enforced])
    O -->|Yes| P[(Balance applied, order total reduced)]
    P --> Q{Balance now $0?}
    Q -->|Yes| R[(Card deactivated)]
    Q -->|No| S([Remainder stays on the card])
```

---

## 5. Cancelling an order with a gift card involved

```mermaid
flowchart TD
    A([Order is cancelled]) --> B{Did a gift card cover part of it at checkout?}
    B -->|No| Z1([Nothing to reverse])
    B -->|Yes| C[(Balance credited back to the card)]
    C --> D[(Redemption marked reversed)]
    D --> E{Was the gift card covering the whole order?}
    E -->|Yes| F[(payment_status = refunded, automatically)]
    F --> Z2([Nothing left for admin to do])
    E -->|No — real money also involved| G[(payment_status = refund_pending)]
    G --> Z3([Admin must still issue a manual refund])
```

---

## 6. Admin notifications

```mermaid
flowchart TD
    A([Admin console polling loop — every 10s]) --> B[GET /admin/notifications]
    B --> C[New orders awaiting packing]
    B --> D[Orders cancelled for refused C.O.D.]
    B --> E[Riders holding cash from a day other than today]
    B --> F[Negative feedback — last 7 days]
    B --> G[Refunds and gift cards issued — last 7 days]
    C --> H[/🔔 Bell: badge count + one-time ring on a new item/]
    D --> H
    E --> H
    F --> H
    G --> H
    H --> H1[\Admin taps ✕ to dismiss a row/]
    A --> I[GET /admin/orders + /admin/support/threads]
    I --> J[/🔊 Speaker: new orders + new chat messages/]
    A --> K[recent_ratings — any score, good or bad]
    K --> L[/Toast popup/]
    L --> Z([Fades on its own after 6s])
```
