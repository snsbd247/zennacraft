# Profit Definitions — Post Phase B.3

This is the single source of truth for what "profit" means across Zenna Craft's admin panel, written after Phase B.3 (D1/D2) unified the order-status scope that had drifted apart across dashboards. See `docs/profit-scope-analysis.md` for the forensic before-state this phase fixed — this document describes the current, intended state and is meant to stay current as the code changes.

## The rule

**This is a Cash-on-Delivery store: money is only real once an order is delivered.** A pending, confirmed, processing, shipped, cancelled, or returned order has generated zero collected revenue.

> Any figure labelled "profit" that a human reads as money received must be delivered-only (`status = 'delivered'`). If you need a pre-delivery figure — for merchandising, pipeline forecasting, or anything else that legitimately wants to look ahead of the courier — name it "estimated" or "pipeline," never "profit."

Two categories exist in this codebase, and they must never be blurred into one label:

### 1. Realized profit — always `status = 'delivered'`

Every order-level or aggregate "profit"/"gross profit"/"net profit"/"margin" figure that represents money the business has actually collected is scoped to delivered orders only. This includes headline KPIs, executive/marketing/customer dashboards, coupon and segment performance, and customer lifetime value. Two cost formulas legitimately coexist within this category — they are not an inconsistency, because they describe different questions and are internally documented as such (see `tests/Feature/Finance/ProfitConsistencyTest.php`):

- **Gross profit** = revenue − product cost − courier cost (the `gross_profit` column, or an equivalent live recomputation)
- **Net profit** = gross profit − ad spend − business expense

### 2. Estimated / pipeline margin — deliberately broader than delivered

A small number of product-level signals exist to answer "is this product structurally healthy," a merchandising question that shouldn't have to wait for a courier to finish a delivery cycle. These stay on a broader "not cancelled/returned" scope on purpose, and are labelled "Estimated Margin" / "estimated" in every place they're displayed — never "profit."

## The after picture

| Figure | File:Method | Scope | Formula | Label |
|---|---|---|---|---|
| Finance dashboard "Net Profit" | `FinanceService::profitEngine()` | Delivered | revenue − product − courier − ads − expense | Net Profit |
| Marketing command center `profit_after_ads` / `net_business_profit` | `MarketingAnalyticsService::profitSummary()` via `deliveredOrdersQuery()` | Delivered | revenue − product − courier − ads − expense | Profit After Ads |
| Reports → Marketing ROI "Net Profit" | `ReportService::marketingRoiReport()` | Delivered | revenue − product − courier − ads − expense | Net Profit |
| Finance dashboard "Gross Profit" | `FinanceService::summary()` | Delivered | `SUM(gross_profit)` | Gross Profit |
| Reports → Profit report "Gross Profit" | `ReportService::profitReport()` | Delivered | `SUM(gross_profit)` | Gross Profit |
| Executive headline `today/seven_day/thirty_day_profit`, `net_profit` KPI, `profitAnalytics()` breakdown | `ExecutiveAnalyticsService::profitFor()`/`profitBreakdown()` | Delivered (Phase B.3) | revenue − product − courier | Profit / Net Profit |
| Executive Intelligence → Finance trend `profit`/`margin` | `ExecutiveAnalyticsService::financeIntelligence()` | Delivered (Phase B.3) | `SUM(gross_profit)`; margin ÷ delivered revenue | Profit / Margin |
| Executive Intelligence → Customers `lifetime_profit` | `ExecutiveAnalyticsService::customerIntelligence()` | Delivered (Phase B.3) | `SUM(gross_profit)` | Lifetime Profit |
| Executive Intelligence → Time ranges `profit`/`margin` | `ExecutiveAnalyticsService::timeAnalytics()` | Delivered (Phase B.3) | `SUM(gross_profit)`; margin ÷ delivered revenue | Profit / Margin |
| Marketing command center coupon `profit_estimate` | `MarketingAnalyticsService::couponPerformance()` | Delivered (Phase B.3) | `SUM(gross_profit)` | Profit Estimate |
| Marketing command center segment `profit_estimate` | `MarketingAnalyticsService::segmentPerformance()` | Delivered (Phase B.3) | `SUM(gross_profit)` | Profit Estimate |
| Marketing command center source `profit_after_ads` | `MarketingAnalyticsService::sourceRow()` | Delivered (Phase B.3) | `SUM(gross_profit)` − ad spend | Profit After Ads |
| Customer 360 `gross_profit_total`/`profit_margin`/`profitability_tier`/`average_profit_per_order` | `Customer360Service::financialMetrics()` | Delivered (Phase B.3) | `SUM(gross_profit)`; margin & average ÷ delivered figures | Gross Profit / Margin / Tier |
| Coupon detail page `gross_profit` | `CouponService::usageStats()` | Delivered (Phase B.3) | `SUM(gross_profit)` | Gross Profit |
| Customer list `gross_profit_total` column | `CustomerController::index()` | Delivered (Phase B.3) | `SUM(gross_profit)` | Gross Profit |
| Per-order profit/margin (Recent Financial Orders, per-order marketing profile) | `FinanceService::orderProfit()`, `MarketingAnalyticsService::orderMarketingProfile()` | Single order — its own status is shown alongside it | `order.gross_profit` | Profit (row shows its own status) |
| Finance "Estimated Margin by Product" | `FinanceService::productEconomics()` | Not cancelled/returned (pipeline, unchanged) | item revenue − item product cost | **Estimated Margin** (Phase B.3 relabel) |
| Executive "High Revenue / Low Estimated Margin" insight & panel | `ExecutiveAnalyticsService::highRevenueLowProfitProducts()` | Not cancelled/returned (pipeline, unchanged) | item revenue − item product cost | **Estimated Margin** (Phase B.3 relabel) |
| Marketing `profit_after_estimated_marketing_cost` | `MarketingAnalyticsService::productMarketingPerformance()` | Not cancelled/returned (pipeline, unchanged) | item revenue − item product cost | Not currently rendered in any view |

**Known, deliberately deferred gaps** (not fixed in Phase B.3 — each is its own follow-up):

- `MarketingAnalyticsService::roiByDate()` and `ExecutiveAnalyticsService::marketingSummary()` are delivered-only but their formula omits business expense — one field short of the "net profit" definition above. This is a formula bug, not a scope bug; tracked separately from this phase.
- The `orders.gross_profit` column is written once at checkout and re-synced to include courier cost from exactly one call site (`CourierService::syncFinanceForOrder()`), wrapped in a try/catch that only logs on failure. Every figure in this document that reads the stored column inherits that freshness risk. This is a data-integrity question, not a scope question — deliberately out of scope here.

## The rule for the next developer

**Any figure labelled "profit" that a human reads as money received must be delivered-only. If you need a pre-delivery figure, name it "estimated" or "pipeline" — never "profit."**
