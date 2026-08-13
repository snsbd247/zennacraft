<?php

namespace App\Modules\Theme\Http\Controllers;

use App\Modules\Media\Services\MediaService;
use App\Modules\Theme\Services\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Website Setup pages (Header, Footer, Theme Color, Font Family) — data-driven
 * forms stored via ThemeService, i.e. the exact theme keys the storefront layout
 * already reads, so saving here updates the live site (colors + font are wired
 * into the layout's CSS variables).
 */
class WebsiteSetupController extends Controller
{
    public function __construct(private ThemeService $theme, private MediaService $media) {}

    public function show(string $page): View
    {
        $cfg = $this->pages()[$page] ?? abort(404);
        $values = [];
        $images = [];
        foreach ($this->fields($cfg) as $f) {
            if (($f['type'] ?? 'text') === 'image') {
                $images[$f['key']] = $this->theme->mediaUrl($f['key']);
            } else {
                $values[$f['key']] = $this->theme->get($f['key'], $f['default'] ?? '');
            }
        }

        return view('studio.website.form', ['page' => $page, 'cfg' => $cfg, 'values' => $values, 'images' => $images]);
    }

    public function save(Request $request, string $page): RedirectResponse
    {
        $cfg = $this->pages()[$page] ?? abort(404);
        foreach ($this->fields($cfg) as $f) {
            $key = $f['key'];
            $type = $f['type'] ?? 'text';
            if ($type === 'image') {
                if ($request->hasFile($key)) {
                    $this->theme->set($key, $this->media->upload($request->file($key), $key, null, 'theme')->id);
                }
            } elseif ($type === 'checkbox') {
                $this->theme->set($key, $request->boolean($key), 'boolean');
            } else {
                $this->theme->set($key, (string) $request->input($key, ''));
            }
        }

        return redirect()->route('website.'.$page)->with('success', $cfg['title'].' saved.');
    }

    private function fields(array $cfg): array
    {
        return collect($cfg['sections'])->flatMap(fn ($s) => $s['fields'])->all();
    }

    private function pages(): array
    {
        return [
            'header' => ['title' => 'Header', 'sections' => [
                ['title' => 'Branding', 'fields' => [
                    ['key' => 'brand_name', 'label' => 'Store name', 'type' => 'text'],
                    ['key' => 'brand_slogan', 'label' => 'Slogan / tagline', 'type' => 'text', 'default' => 'Quality you can trust'],
                    ['key' => 'search_placeholder', 'label' => 'Search box placeholder', 'type' => 'text', 'default' => 'Search for products…'],
                    ['key' => 'site_logo', 'label' => 'Logo', 'type' => 'image', 'hint' => '240 × 60 px · transparent PNG (shows in header & footer)'],
                    ['key' => 'site_favicon', 'label' => 'Favicon', 'type' => 'image', 'hint' => '64 × 64 px · PNG or ICO (browser tab icon)'],
                ]],
                ['title' => 'Announcement bar', 'desc' => 'The thin strip above the header. Clear a message to hide it.', 'fields' => [
                    ['key' => 'announce_1', 'label' => 'Message 1', 'type' => 'text', 'default' => 'Free delivery over ৳3000'],
                    ['key' => 'announce_2', 'label' => 'Message 2', 'type' => 'text', 'default' => 'Cash on delivery across Bangladesh'],
                    ['key' => 'announce_3', 'label' => 'Message 3', 'type' => 'text', 'default' => '100% genuine products, guaranteed'],
                ]],
                ['title' => 'Contact', 'fields' => [
                    ['key' => 'contact_phone', 'label' => 'Contact phone', 'type' => 'text'],
                    ['key' => 'contact_email', 'label' => 'Contact email', 'type' => 'text'],
                    ['key' => 'contact_address', 'label' => 'Contact address', 'type' => 'text'],
                ]],
            ]],
            'footer' => ['title' => 'Footer', 'sections' => [
                ['title' => 'Footer content', 'fields' => [
                    ['key' => 'footer_description', 'label' => 'About text', 'type' => 'textarea', 'default' => 'Quality products delivered straight to your door — trusted service, fair prices, cash on delivery across Bangladesh.'],
                    ['key' => 'footer_copyright', 'label' => 'Copyright line', 'type' => 'text'],
                ]],
                ['title' => 'Why-us checklist', 'desc' => 'The four ticks in the footer. Clear one to hide it.', 'fields' => [
                    ['key' => 'footer_why_1', 'label' => 'Point 1', 'type' => 'text', 'default' => '100% genuine products'],
                    ['key' => 'footer_why_2', 'label' => 'Point 2', 'type' => 'text', 'default' => 'Cash on delivery'],
                    ['key' => 'footer_why_3', 'label' => 'Point 3', 'type' => 'text', 'default' => 'Inspect before you pay'],
                    ['key' => 'footer_why_4', 'label' => 'Point 4', 'type' => 'text', 'default' => 'Easy 7-day exchange'],
                ]],
                ['title' => 'Social links', 'fields' => [
                    ['key' => 'social_facebook', 'label' => 'Facebook URL', 'type' => 'text'],
                    ['key' => 'social_instagram', 'label' => 'Instagram URL', 'type' => 'text'],
                    ['key' => 'social_youtube', 'label' => 'YouTube URL', 'type' => 'text'],
                    ['key' => 'social_whatsapp', 'label' => 'WhatsApp number', 'type' => 'text'],
                    ['key' => 'social_whatsapp_message', 'label' => 'WhatsApp default message', 'type' => 'textarea', 'help' => 'Pre-filled in the customer\'s chat box when they tap the WhatsApp button. Leave blank for the built-in default.'],
                ]],
            ]],
            'homepage' => ['title' => 'Homepage Text', 'sections' => [
                ['title' => 'Hero (shown when no hero slider is set)', 'desc' => 'The big banner at the top of the homepage. Once you add a Hero Slider, that takes over.', 'fields' => [
                    ['key' => 'hero_kicker', 'label' => 'Small kicker text', 'type' => 'text', 'default' => 'Featured collection'],
                    ['key' => 'hero_title', 'label' => 'Big headline', 'type' => 'text', 'default' => 'Everything you need, delivered to your door'],
                    ['key' => 'hero_subtitle', 'label' => 'Sub-line', 'type' => 'text', 'default' => 'আপনার পছন্দের পণ্য এখন আপনার ঘরে — আজই অর্ডার করুন।'],
                    ['key' => 'hero_button', 'label' => 'Button text', 'type' => 'text', 'default' => 'Shop the collection'],
                ]],
                ['title' => 'Why-shop badges', 'desc' => 'The four promise cards below the hero.', 'fields' => [
                    ['key' => 'trust_1_title', 'label' => 'Badge 1 title', 'type' => 'text', 'default' => '100% Genuine'],
                    ['key' => 'trust_1_text', 'label' => 'Badge 1 subtitle', 'type' => 'text', 'default' => 'Authentic products, quality checked'],
                    ['key' => 'trust_2_title', 'label' => 'Badge 2 title', 'type' => 'text', 'default' => 'Cash on Delivery'],
                    ['key' => 'trust_2_text', 'label' => 'Badge 2 subtitle', 'type' => 'text', 'default' => 'Pay only after you inspect it'],
                    ['key' => 'trust_3_title', 'label' => 'Badge 3 title', 'type' => 'text', 'default' => 'Fast Delivery'],
                    ['key' => 'trust_3_text', 'label' => 'Badge 3 subtitle', 'type' => 'text', 'default' => '2–4 days Dhaka, 3–6 nationwide'],
                    ['key' => 'trust_4_title', 'label' => 'Badge 4 title', 'type' => 'text', 'default' => 'Easy Exchange'],
                    ['key' => 'trust_4_text', 'label' => 'Badge 4 subtitle', 'type' => 'text', 'default' => '7-day hassle-free returns'],
                ]],
                ['title' => 'Section headings', 'fields' => [
                    ['key' => 'heading_categories', 'label' => 'Categories row', 'type' => 'text', 'default' => 'Shop by category'],
                    ['key' => 'heading_top_selling', 'label' => 'Top selling row', 'type' => 'text', 'default' => 'Top selling products'],
                    ['key' => 'heading_for_you', 'label' => '"For you" row', 'type' => 'text', 'default' => 'Just for you'],
                    ['key' => 'heading_reviews', 'label' => 'Reviews row', 'type' => 'text', 'default' => 'Loved by our customers'],
                ]],
            ]],
            'theme' => ['title' => 'Set Theme Color', 'button' => 'Change Theme', 'sections' => [
                ['title' => 'Primary', 'fields' => [
                    ['key' => 'primary_color', 'label' => 'Primary Color', 'type' => 'color', 'default' => '#1f7a3d'],
                    ['key' => 'primary_text_color', 'label' => 'Primary Text Color', 'type' => 'color', 'default' => '#ffffff'],
                    ['key' => 'primary_hover_color', 'label' => 'Primary Hover Color', 'type' => 'color', 'default' => '#f2a20c'],
                ]],
                ['title' => 'Menu', 'fields' => [
                    ['key' => 'menu_bg_color', 'label' => 'Menu Background Color', 'type' => 'color', 'default' => '#155e2e'],
                    ['key' => 'menu_text_color', 'label' => 'Menu Text Color', 'type' => 'color', 'default' => '#ffffff'],
                    ['key' => 'menu_hover_color', 'label' => 'Menu Hover Color', 'type' => 'color', 'default' => '#f2a20c'],
                ]],
                ['title' => 'Add To Cart', 'fields' => [
                    ['key' => 'cart_bg_color', 'label' => 'Add To Cart Background', 'type' => 'color', 'default' => '#1f7a3d'],
                    ['key' => 'cart_text_color', 'label' => 'Add To Cart Text Color', 'type' => 'color', 'default' => '#ffffff'],
                    ['key' => 'cart_border_color', 'label' => 'Add To Cart Border Color', 'type' => 'color', 'default' => '#1f7a3d'],
                    ['key' => 'cart_hover_color', 'label' => 'Add To Cart Hover Color', 'type' => 'color', 'default' => '#155e2e'],
                    ['key' => 'cart_hover_text_color', 'label' => 'Add To Cart Hover Text Color', 'type' => 'color', 'default' => '#ffffff'],
                ]],
                ['title' => 'Discount', 'fields' => [
                    ['key' => 'discount_price_color', 'label' => 'Discount Price Color', 'type' => 'color', 'default' => '#e0483a'],
                ]],
                ['title' => 'Footer', 'fields' => [
                    ['key' => 'footer_bg_color', 'label' => 'Footer Background Color', 'type' => 'color', 'default' => '#12271a'],
                    ['key' => 'footer_text_color', 'label' => 'Footer Text Color', 'type' => 'color', 'default' => '#cfe0d4'],
                    ['key' => 'footer_hover_color', 'label' => 'Footer Hover Color', 'type' => 'color', 'default' => '#f2a20c'],
                ]],
                ['title' => 'Default', 'fields' => [
                    ['key' => 'default_border_color', 'label' => 'Default Border Color', 'type' => 'color', 'default' => '#e8e1d3'],
                    ['key' => 'default_box_shadow', 'label' => 'Default Box Shadow', 'type' => 'text', 'default' => '0 2px 6px rgba(24,37,28,.06)'],
                ]],
                ['title' => 'Single Page Add To Cart', 'fields' => [
                    ['key' => 'sp_cart_bg_color', 'label' => 'Background Color', 'type' => 'color', 'default' => '#1f7a3d'],
                    ['key' => 'sp_cart_text_color', 'label' => 'Text Color', 'type' => 'color', 'default' => '#ffffff'],
                    ['key' => 'sp_cart_border_color', 'label' => 'Border Color', 'type' => 'color', 'default' => '#1f7a3d'],
                    ['key' => 'sp_cart_hover_bg_color', 'label' => 'Hover Background Color', 'type' => 'color', 'default' => '#f2a20c'],
                    ['key' => 'sp_cart_hover_text_color', 'label' => 'Hover Text Color', 'type' => 'color', 'default' => '#3a2600'],
                    ['key' => 'sp_cart_hover_border_color', 'label' => 'Hover Border Color', 'type' => 'color', 'default' => '#f2a20c'],
                ]],
            ]],
            'font' => ['title' => 'Font Family', 'sections' => [
                ['title' => 'Typography', 'desc' => 'The Google font used across the storefront.', 'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'type' => 'select', 'default' => 'Plus Jakarta Sans',
                        'options' => ['Plus Jakarta Sans', 'Inter', 'Poppins', 'Roboto', 'Montserrat', 'Open Sans', 'Lato', 'Hind Siliguri', 'Noto Sans Bengali']],
                ]],
            ]],
            'promotions' => ['title' => 'Promotions', 'sections' => [
                ['title' => 'Countdown / Flash Sale bar', 'desc' => 'A live countdown strip below the header. Turn it on for an event, set when it ends and where it links. It hides itself automatically once the time runs out.', 'fields' => [
                    ['key' => 'countdown_enabled', 'label' => 'Show countdown bar', 'type' => 'checkbox'],
                    ['key' => 'countdown_title', 'label' => 'Title', 'type' => 'text', 'default' => '⚡ Flash Sale ends in'],
                    ['key' => 'countdown_ends_at', 'label' => 'Ends at', 'type' => 'datetime'],
                    ['key' => 'countdown_cta', 'label' => 'Button text', 'type' => 'text', 'default' => 'Shop now'],
                    ['key' => 'countdown_link', 'label' => 'Link (where the bar goes when clicked)', 'type' => 'text', 'default' => '/products'],
                ]],
                ['title' => 'Welcome popup', 'desc' => 'A popup shown once per visit. Add an offer image and/or text; clicking it opens your link, clicking outside or the ✕ closes it.', 'fields' => [
                    ['key' => 'popup_enabled', 'label' => 'Show welcome popup', 'type' => 'checkbox'],
                    ['key' => 'popup_title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Welcome! 🎉'],
                    ['key' => 'popup_text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Enjoy special offers on your first order.'],
                    ['key' => 'popup_image', 'label' => 'Image (optional)', 'type' => 'image', 'hint' => '600 × 600 px (square) or 600 × 700 px portrait'],
                    ['key' => 'popup_cta', 'label' => 'Button text', 'type' => 'text', 'default' => 'Shop the offer'],
                    ['key' => 'popup_link', 'label' => 'Link', 'type' => 'text', 'default' => '/products'],
                ]],
            ]],
        ];
    }
}
