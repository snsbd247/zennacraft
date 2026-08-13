@php
    // Grows as pages come back online after the 2026-07-24 Studio rebuild.
    // Each item is either a flat link (has 'route') or a group (has
    // 'children'). Patterns may be a string or an array for request()->routeIs().
    $studioNav = [
        ['type' => 'link', 'label' => 'Dashboard', 'route' => 'studio.dashboard', 'pattern' => 'studio.dashboard', 'icon' => 'dashboard'],
        ['type' => 'link', 'label' => 'POS (Point of Sale)', 'route' => 'pos.index', 'pattern' => 'pos.*', 'icon' => 'pos', 'permission' => 'order.create'],
        [
            'type' => 'group',
            'label' => 'Orders',
            'icon' => 'orders',
            'pattern' => ['orders.*', 'courier.*'],
            'children' => [
                ['label' => 'Manage Order', 'route' => 'orders.index', 'pattern' => ['orders.index', 'orders.show', 'orders.status']],
                ['label' => 'Add Exchange Order', 'route' => 'orders.exchange.create', 'pattern' => 'orders.exchange.*'],
                ['label' => 'Order Processing Report', 'route' => 'orders.processing-report', 'pattern' => 'orders.processing-report'],
                ['label' => 'Order Source', 'route' => 'orders.source', 'pattern' => 'orders.source'],
                ['label' => 'Order Processing Note', 'route' => 'orders.notes.index', 'pattern' => 'orders.notes.*'],
                ['label' => 'Courier', 'route' => 'courier.index', 'pattern' => 'courier.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Products',
            'icon' => 'products',
            'pattern' => ['products.*', 'stock.*'],
            'children' => [
                ['label' => 'Manage Products', 'route' => 'products.index', 'pattern' => 'products.index'],
                ['label' => 'Stock', 'route' => 'stock.index', 'pattern' => 'stock.*'],
                ['label' => 'Attribute/Size', 'route' => 'products.attributes.index', 'pattern' => 'products.attributes.*'],
                ['label' => 'Variant', 'route' => 'products.variants.index', 'pattern' => 'products.variants.*'],
                ['label' => 'Products Review', 'route' => 'products.reviews.index', 'pattern' => 'products.reviews.*'],
                ['label' => 'Product View Report', 'route' => 'products.view-report.index', 'pattern' => 'products.view-report.*'],
                ['label' => 'Damage Products', 'route' => 'products.damages.index', 'pattern' => 'products.damages.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Purchase',
            'icon' => 'purchase',
            'pattern' => ['purchases.*', 'suppliers.*'],
            'children' => [
                ['label' => 'Add', 'route' => 'purchases.create', 'pattern' => 'purchases.create'],
                ['label' => 'Manage', 'route' => 'purchases.index', 'pattern' => 'purchases.index'],
                ['label' => 'Suppliers', 'route' => 'suppliers.index', 'pattern' => 'suppliers.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Category',
            'icon' => 'category',
            'pattern' => ['categories.*'],
            'children' => [
                ['label' => 'Main Category', 'route' => 'categories.main.index', 'pattern' => 'categories.main.*'],
                ['label' => 'Sub Category', 'route' => 'categories.sub.index', 'pattern' => 'categories.sub.*'],
                ['label' => 'Sub Sub Category', 'route' => 'categories.subsub.index', 'pattern' => 'categories.subsub.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Slider',
            'icon' => 'slider',
            'pattern' => ['sliders.*'],
            'children' => [
                ['label' => 'Hero Slider', 'route' => 'sliders.hero.index', 'pattern' => 'sliders.hero.*'],
                ['label' => 'Side Banner', 'route' => 'sliders.side.index', 'pattern' => 'sliders.side.*'],
                ['label' => 'Promo Banner', 'route' => 'sliders.promo.index', 'pattern' => 'sliders.promo.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Brand',
            'icon' => 'brand',
            'pattern' => ['brands.*'],
            'children' => [
                ['label' => 'Brands', 'route' => 'brands.index', 'pattern' => 'brands.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Accounts',
            'icon' => 'accounts',
            'pattern' => ['accounts.*'],
            'children' => [
                ['label' => 'Income', 'route' => 'accounts.income.index', 'pattern' => 'accounts.income.*'],
                ['label' => 'Expense', 'route' => 'accounts.expense.index', 'pattern' => 'accounts.expense.*'],
                ['label' => 'Due', 'route' => 'accounts.due.index', 'pattern' => 'accounts.due.*'],
                ['label' => 'Balance', 'route' => 'accounts.balance.index', 'pattern' => 'accounts.balance.*'],
                ['label' => 'Fund Transfer', 'route' => 'accounts.transfer.index', 'pattern' => 'accounts.transfer.*'],
                ['label' => 'Account Purpose', 'route' => 'accounts.purpose.index', 'pattern' => 'accounts.purpose.*'],
                ['label' => 'Employee Salary', 'route' => 'accounts.salary.index', 'pattern' => 'accounts.salary.*'],
                ['label' => 'Bill Statements', 'route' => 'accounts.bill.index', 'pattern' => 'accounts.bill.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Landing Page',
            'icon' => 'landing',
            'pattern' => ['landing.*'],
            'children' => [
                ['label' => 'Manage', 'route' => 'landing.index', 'pattern' => 'landing.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Offer',
            'icon' => 'offer',
            'pattern' => ['offers.*', 'website.promotions'],
            'children' => [
                ['label' => 'Offers', 'route' => 'offers.index', 'pattern' => 'offers.*'],
                ['label' => 'Countdown & Popup', 'route' => 'website.promotions', 'pattern' => 'website.promotions*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Coupon',
            'icon' => 'coupon',
            'pattern' => ['coupons.*'],
            'children' => [
                ['label' => 'Coupons', 'route' => 'coupons.index', 'pattern' => 'coupons.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Campaign/Offer',
            'icon' => 'campaign',
            'pattern' => ['offer-banners.*', 'combos.*', 'free-delivery.*'],
            'children' => [
                ['label' => 'Offer Banner', 'route' => 'offer-banners.index', 'pattern' => 'offer-banners.*'],
                ['label' => 'Combo Products', 'route' => 'combos.index', 'pattern' => 'combos.*'],
                ['label' => 'Free Delivery Products', 'route' => 'free-delivery.index', 'pattern' => 'free-delivery.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Customer',
            'icon' => 'customer',
            'pattern' => ['customers.*', 'recoveries.*'],
            'children' => [
                ['label' => 'Customers', 'route' => 'customers.index', 'pattern' => 'customers.*'],
                ['label' => 'Incomplete Orders', 'route' => 'recoveries.index', 'pattern' => 'recoveries.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Admin',
            'icon' => 'admin',
            'pattern' => ['admins.*'],
            'children' => [
                ['label' => 'Add', 'route' => 'admins.create', 'pattern' => 'admins.create'],
                ['label' => 'Manage', 'route' => 'admins.index', 'pattern' => ['admins.index', 'admins.edit', 'admins.permissions']],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Website Setup',
            'icon' => 'website',
            'pattern' => ['website.*', 'pages.*'],
            'children' => [
                ['label' => 'Header', 'route' => 'website.header', 'pattern' => 'website.header*'],
                ['label' => 'Footer', 'route' => 'website.footer', 'pattern' => 'website.footer*'],
                ['label' => 'Homepage Text', 'route' => 'website.homepage', 'pattern' => 'website.homepage*'],
                ['label' => 'Theme Color', 'route' => 'website.theme', 'pattern' => 'website.theme*'],
                ['label' => 'Font Family', 'route' => 'website.font', 'pattern' => 'website.font*'],
                ['label' => 'Pages', 'route' => 'pages.index', 'pattern' => 'pages.*'],
            ],
        ],
        [
            'type' => 'group',
            'label' => 'Setting & Configuration',
            'icon' => 'settings',
            'pattern' => ['config.*', 'cities.*', 'subcities.*'],
            'children' => [
                ['label' => 'Marketing / Pixels', 'route' => 'config.marketing', 'pattern' => 'config.marketing*'],
                ['label' => 'Courier API', 'route' => 'config.courier', 'pattern' => 'config.courier*'],
                ['label' => 'Payment Gateway', 'route' => 'config.payment', 'pattern' => 'config.payment*'],
                ['label' => 'SMS Gateway', 'route' => 'config.sms', 'pattern' => 'config.sms*'],
                ['label' => 'Email (SMTP)', 'route' => 'config.email', 'pattern' => 'config.email*'],
                ['label' => 'Google & reCAPTCHA', 'route' => 'config.google', 'pattern' => 'config.google*'],
                ['label' => 'Order Verification Call', 'route' => 'config.verification', 'pattern' => 'config.verification*'],
                ['label' => 'Social Login', 'route' => 'config.social', 'pattern' => 'config.social*'],
                ['label' => 'Invoice Address', 'route' => 'config.invoice', 'pattern' => 'config.invoice*'],
                ['label' => 'Delivery Charge', 'route' => 'config.delivery', 'pattern' => 'config.delivery*'],
                ['label' => 'Order Number', 'route' => 'config.order', 'pattern' => 'config.order*'],
                ['label' => 'Content Protection', 'route' => 'config.protection', 'pattern' => 'config.protection*'],
                ['label' => 'City', 'route' => 'cities.index', 'pattern' => 'cities.*'],
                ['label' => 'Sub City', 'route' => 'subcities.index', 'pattern' => 'subcities.*'],
            ],
        ],
        ['type' => 'link', 'label' => 'License & Updates', 'route' => 'license.index', 'pattern' => 'license.*', 'icon' => 'license', 'permission' => 'settings.view'],
    ];

    $studioNavIcon = function (string $name): string {
        $paths = match ($name) {
            'dashboard' => '<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9h12v-9"/><path d="M10 19v-5h4v5"/>',
            'orders' => '<path d="M6 8h12l-1 12H7z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
            'pos' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8"/><rect x="8" y="15" width="8" height="3" rx="1"/>',
            'products' => '<path d="M20 8v9a1 1 0 0 1-.6.9l-6.6 3a2 2 0 0 1-1.6 0l-6.6-3A1 1 0 0 1 4 17V8"/><path d="M2.5 7 12 3l9.5 4-9.5 4z"/>',
            'purchase' => '<path d="M6 6h15l-1.6 9.2a2 2 0 0 1-2 1.8H9.6a2 2 0 0 1-2-1.7L6 3H3"/><circle cx="9" cy="20" r="1.2"/><circle cx="18" cy="20" r="1.2"/>',
            'category' => '<path d="M20.6 13.4 10.6 3.4a2 2 0 0 0-1.4-.6H4a1 1 0 0 0-1 1v5.2a2 2 0 0 0 .6 1.4l10 10a2 2 0 0 0 2.8 0l4.2-4.2a2 2 0 0 0 0-2.8Z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
            'slider' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 6"/><circle cx="8.5" cy="9.5" r="1.3"/>',
            'brand' => '<path d="M12 2.5 4 6v5.5c0 4.6 3.2 7.5 8 9 4.8-1.5 8-4.4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'accounts' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M12 8.5c-1.2 0-2 .7-2 1.6 0 2 4 1 4 3 0 .9-.9 1.6-2 1.6s-2-.7-2-1.6"/><path d="M12 7v1.2M12 15.4V17"/>',
            'landing' => '<path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2 0-2.8a2 2 0 0 0-3 0Z"/><path d="M12 15 9 12c.5-3 2.5-7 8.5-8.5C18 8.5 15 11 12 15Z"/><path d="M9 12H4l2.5-3.5c1-1 2.5-1 4-.5"/><path d="M12 15v5l3.5-2.5c1-1 1-2.5.5-4"/>',
            'offer' => '<path d="M20.6 13.4 12 22l-9-9V4a1 1 0 0 1 1-1h9z" transform="translate(1 -1)"/><circle cx="7.5" cy="7.5" r="1.4"/><path d="M9 14l5-5"/>',
            'coupon' => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M9 8v8" stroke-dasharray="2 2"/>',
            'campaign' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l3.5 4V6L6 10H4a1 1 0 0 0-1 1Z"/><path d="M14 7a5 5 0 0 1 0 10"/><path d="M17 4a9 9 0 0 1 0 16"/>',
            'customer' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 6.2a3 3 0 0 1 0 5.6"/><path d="M17.5 14.4A5.5 5.5 0 0 1 20.5 19"/>',
            'admin' => '<circle cx="12" cy="7.5" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/><path d="m18 3 2.5 1v2.6c0 1.7-1 3-2.5 3.6-1.5-.6-2.5-1.9-2.5-3.6V4z"/>',
            'website' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 0 1-4 0v-.1A1.6 1.6 0 0 0 7 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H1a2 2 0 0 1 0-4h.1A1.6 1.6 0 0 0 2.6 7a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H7a1.6 1.6 0 0 0 1-1.5V1a2 2 0 0 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V7a1.6 1.6 0 0 0 1.5 1H23a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
            'license' => '<path d="M12 2.5 4 6v5.5c0 4.6 3.2 7.5 8 9 4.8-1.5 8-4.4 8-9V6z"/><circle cx="12" cy="10.5" r="2"/><path d="M12 12.5v3"/>',
            'chevron' => '<path d="M9 6l6 6-6 6"/>',
            default => '<circle cx="12" cy="12" r="1.5"/>',
        };

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
    };
@endphp

<nav class="space-y-1" aria-label="Studio navigation">
    @foreach ($studioNav as $item)
        @if ($item['type'] === 'link')
            <a
                href="{{ route($item['route']) }}"
                class="studio-nav-link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}"
                title="{{ $item['label'] }}"
                @if (request()->routeIs($item['pattern'])) aria-current="page" @endif
            >
                <span class="studio-nav-link__icon">{!! $studioNavIcon($item['icon']) !!}</span>
                <span class="studio-nav-link__label">{{ $item['label'] }}</span>
            </a>
        @else
            @php $groupActive = request()->routeIs($item['pattern']); @endphp
            <details class="studio-nav-group" @if ($groupActive) open @endif>
                <summary class="studio-nav-link {{ $groupActive ? 'is-active' : '' }}">
                    <span class="studio-nav-link__icon">{!! $studioNavIcon($item['icon']) !!}</span>
                    <span class="studio-nav-link__label">{{ $item['label'] }}</span>
                    <span class="studio-nav-group__chevron">{!! $studioNavIcon('chevron') !!}</span>
                </summary>
                <div class="studio-nav-group__children">
                    @foreach ($item['children'] as $child)
                        @if (! empty($child['route']))
                            <a
                                href="{{ route($child['route']) }}"
                                class="studio-nav-link studio-nav-link--child {{ request()->routeIs($child['pattern']) ? 'is-active' : '' }}"
                                title="{{ $child['label'] }}"
                                @if (request()->routeIs($child['pattern'])) aria-current="page" @endif
                            >
                                <span class="studio-nav-link__dot" aria-hidden="true"></span>
                                <span class="studio-nav-link__label">{{ $child['label'] }}</span>
                            </a>
                        @else
                            <span class="studio-nav-link studio-nav-link--child studio-nav-link--soon" title="{{ $child['label'] }} — coming soon">
                                <span class="studio-nav-link__dot" aria-hidden="true"></span>
                                <span class="studio-nav-link__label">{{ $child['label'] }}</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</nav>
